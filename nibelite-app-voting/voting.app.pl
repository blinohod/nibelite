#!/usr/bin/env perl 

use 5.8.0;
use strict;
use warnings;

NetSDSApp->run(
	daemon      => 0,
	infinite    => 0,
	has_conf    => 1,
	use_pidfile => 1,
	conf_file   => './voting.conf',
	verbose     => 1,
	debug       => 1,
);

1;

package NetSDSApp;

use lib '/opt/nibelite/lib/perl';

use base 'Nibelite::App::Service';

use NetSDS::Util::String;

my $CODE_OK   = 0;      # Successfull voting response
my $CODE_ERR  = -1;     # Error finding voting descriptor
my $CODE_FAIL = -2;     # Cannot vote multiple times
my $CODE_HELP = -10;    # Help message

sub start {

	my ($this) = @_;

	$this->speak("Starting SMS voting service");

}

sub process {

	my ($this) = @_;

	$this->dbh->begin();

	# Fetch messages from queue
	if ( my @msgs = $this->fetch_messages( $this->app_id, 1 ) ) {

		# Process each message with transport specific logic
		foreach my $msg (@msgs) {

			$this->speak( "Received: " . $msg->{msg_type} );
			$this->mq->update_message( $msg->{id}, msg_status => 'PROCESSED' );

			# Text MO SM retrieved.
			if ( $msg->{msg_type} eq 'SMS_TEXT' ) {

				# Get content response for voting
				my $response = $this->get_content( $msg->{msg_body}, $msg->{src_addr}, $msg->{dst_addr} );

				# Prepare reply message
				my $rep = $this->mq->make_reply(
					$msg,
					msg_body => $response,
				);

				$this->speak("Sent response: $response");

				# Put reply to outgoing message queue
				$this->mq->enqueue_message(%$rep);

			} elsif ( $msg->{msg_type} eq 'DLR' ) {

				$this->speak( "DLR Retrieved for MT SM: " . $msg->{msg_body} );

			}

		} ## end foreach my $msg (@msgs)

		$this->dbh->commit();

	} else {

		$this->dbh->commit();

		$this->speak("Empty MO queue for voting.");
		sleep 1;

	}

	return 1;

} ## end sub process

sub get_reply {
	my ( $this, $sn, $code, $voting_id ) = @_;

	$voting_id = 0 unless defined $voting_id;

	if ( $code > 0 ) {    # Correct answer id
		my ($reply) = @{ $this->dbh->fetch_call( "select reply from voting.replies where answer_id = ?", $code ) };
		return $reply->{reply} if defined $reply;
		# No reply for answer? Look for default later!
		$code = 0;
	}

	unless ($voting_id) {
		# Get latest active voting_id by sn
		my ($did) = @{ $this->dbh->fetch_call( "select id from voting.votings" . " where active=true" . " and sn=?" . " and now() between since and till" . " order by id desc limit 1" ) };
		$voting_id = $did->{id} if defined $did;
	}

	my ($reply) = @{
		$this->dbh->fetch_call(
			"select reply from voting.replies where voting_id=? and answer_id=?",
			$voting_id, $code
		)
	  };
	return $reply->{reply} if defined $reply;

	# Try to look for system default reply
	if ($voting_id) {
		my ($reply) = @{
			$this->dbh->fetch_call(
				"select reply from voting.replies where voting_id=0 and answer_id=?",
				$code
			)
		  };
		return $reply->{reply} if defined $reply;
	}

	# Try to look for system default reply
	($reply) = @{ $this->dbh->fetch_call("select reply from voting.replies where voting_id=0 and answer_id=-1") };

	return $reply->{reply} if defined $reply;

} ## end sub get_reply

sub get_content {

	my ( $this, $request, $msisdn, $sn ) = @_;

	# Cleanup SMS query string
	my $code = lc( str_clean($request) );

	$this->speak("Vote try: MSISDN='$msisdn'; SN='$sn', CODE='$code'");
	$this->log( "info", "Vote try: MSISDN='$msisdn'; SN='$sn', CODE='$code'" );

	# Process HELP request
	if ( $code eq 'help' ) {
		return $this->get_reply( $sn, $CODE_HELP );
	}

	# Process invalid query formats
	# FIXME - we support non numeric codes too!
	# (zmeuka) OK, there I fixed it with comments:
	# unless ( $code =~ /^\d+$/ ) {
	#	return $this->app_conf->{'msg_invalid_format'};
	# }

	# Fetch voting info
	my ($rec) = @{ $this->dbh->fetch_call( "select * from voting.find_voting_info(?, ?)", $code, $sn ) };

	# Voting found - start processing
	if ($rec) {

		my $voting_id = $rec->{'out_voting_id'};
		my $answer_id = $rec->{'out_answer_id'};
		my $multivote = $rec->{'out_multivote'};

		# Write syslog record
		$this->log( "info", "Found: code='$code'; sn='$sn' => voting_id=$voting_id; answer_id=$answer_id" );

		# If multiple voting disallowed - check for previous votes from this MSISDN
		if ( $multivote eq 'f' ) {

			# Fetch votes from this MSISDN with appropriate voting_id
			my ($rec) = @{ $this->dbh->fetch_call( "select voting.already_voted(?, ?) as voted", $msisdn, $voting_id ) };

			# Multiple voting on non-multivote service
			if ($rec) {
				return $this->get_reply( $sn, $CODE_FAIL, $voting_id );
			}

		}

		# Update average numbers for fast statistics
		$this->dbh->call( "update voting.answers set num_votes = num_votes+1 where id =?", $answer_id );

		# Add vote record
		$this->dbh->call( "insert into voting.votes (msisdn, answer_id) values (?, ?)", $msisdn, $answer_id );

		# Return text for successful voting
		return $this->get_reply( $sn, $answer_id, $voting_id );

	} else {

		$this->log( "warning", "Voting code retrieved but no proper voting found" );
		return $this->get_reply( $sn, $CODE_ERR );
	}

} ## end sub get_content

1;

