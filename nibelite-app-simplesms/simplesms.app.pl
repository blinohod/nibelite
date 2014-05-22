#!/usr/bin/env perl 

=head1 NAME simplesms.pl

=cut

use 5.8.0;
use strict;
use warnings;

NetSDSApp->run(
	daemon      => 0,
	infinite    => 0,
	has_conf    => 1,
	use_pidfile => 1,
	conf_file   => '/opt/nibelite/applications/simplesms/simplesms.conf',
);

1;

package NetSDSApp;

use lib '/opt/nibelite/lib/perl';

use Data::Dumper;

use base 'Nibelite::App::Service';

use NetSDS::Util::String;

sub process {

	my ($this) = @_;

	$this->dbh->begin();

	# Fetch messages from queue
	if ( my @msgs = $this->fetch_messages( $this->app_id, 1 ) ) {

		# Process each message with transport specific logic
		foreach my $msg (@msgs) {

			if ( $this->debug ) {
				warn "New message retrieved: " . Dumper($msg);
			}

			# Mark message as processed
			$this->mq->update_message( $msg->{id}, msg_status => 'PROCESSED' );

			# Text MO SM retrieved. Prepare response.
			if ( $msg->{msg_type} eq 'SMS_TEXT' ) {

				my $resp = $this->get_content( $msg->{msg_body}, $msg->{src_addr}, $msg->{dst_addr} );

				my $rep = $this->mq->make_reply(
					$msg,
					msg_body => $resp,
				);

				warn Dumper($resp);

				$this->mq->enqueue_message(%$rep);

			} elsif ( $msg->{msg_type} eq 'DLR' ) {
				warn "DLR PROCESSED!\n";
			}

		} ## end foreach my $msg (@msgs)

	} ## end if ( my @msgs = $this->fetch_messages...)

	$this->dbh->commit();

	sleep 1;
	return 1;

} ## end sub process

sub get_content {

	my ( $this, $request, $msisdn, $sn ) = @_;

	if ($this->debug) {
		warn "[MO SM] SN='$sn', MSISDN='$msisdn', TXT='$request'\n";
	};

	# Change request to lower case and clean extra spaces.
	$request = lc( str_clean($request) );

	# Parse MO SM request.
	# First word is a service selection keyword (regexp).
	# The rest is a topic selection keyword.
	my ( $srv_keyword, $topic_keyword ) = ( undef, undef );
	if ( $request =~ /^\s*(\S+)(\s+.*)*$/ ) {
		$srv_keyword   = $1;
		$topic_keyword = str_clean($2);
	}

	if ($this->debug) {
	        warn "[SRV: $srv_keyword] [TOP: $topic_keyword]\n";
	}

	# Process HELP service
	if ( $srv_keyword =~ /^\s*help\s*/i ) {
		$this->log( "info", "HELP request: MSISDN='$msisdn', SN='$sn'" );
		return $this->app_conf->{msg_help};
	}

	# Fetch service descriptor
	if ( my $srv = $this->get_service( $srv_keyword, $sn ) ) {

		# Work with empty (default) topic
		unless ( $topic_keyword ) {
			$topic_keyword = 'default';
		}

		# Fetch service descriptor
		if ( my $topic = $this->get_topic( $srv->{id}, $topic_keyword ) ) {

			my $resp;

			# Fetch content
			if ( $srv->{class} eq 'LAST' ) {

				$resp = $this->fetch_content_last( $topic->{id}, $msisdn );

			} elsif ( $srv->{class} eq 'RANDOM' ) {

				$resp = $this->fetch_content_random( $topic->{id}, $msisdn );
			}

			return $resp->{text};

		} else {
			$this->log( "warning", "Cannot find topic keyword='$topic_keyword'" );
		}

	} else {
		$this->log( "warning", "Cannot find service: keyword='$srv_keyword', SN='$sn'" );
	}

} ## end sub get_content

=item B<get_service(keyword, SN)> - find service descriptor

Parameters:
keyword
service number

=cut

sub get_service {

	my ( $this, $keyword, $sn ) = @_;

	my $sql = "select * from simplesms.services where ? ~* keyword and ? = sn";
	my $row = $this->get_row( $sql, $keyword, $sn );

	return $row;

}

sub get_topic {

	my ( $this, $srv_id, $keyword ) = @_;

	my $sql = "select * from simplesms.topics where ? ~* keyword and ? = service_id";
	my $row = $this->get_row( $sql, $keyword, $srv_id );

	return $row;

}

sub fetch_content_last {

	my ( $this, $topic_id, $msisdn ) = @_;

	my $sql = "select * from simplesms.content where (topic_id = ?) and (now() between since and till) order by id desc limit 1";
	my $row = $this->get_row( $sql, $topic_id );

	return $row;
}

sub fetch_content_random {

	my ( $this, $topic_id, $msisdn ) = @_;

	my $sql = "select * from simplesms.content where (topic_id = ?) and (now() between since and till) order by random() limit 1";
	my $row = $this->get_row( $sql, $topic_id );

	if ($row) {
		#$this->dbh->call("insert into ");
		return $row;
	} else {
		return $this->error("Can't fetch RANDOM content for topic_id='$topic_id'");
	}

}

sub get_row {

	my ( $this, $sql, @params ) = @_;

	if ( my ($row) = @{ $this->dbh->fetch_call( $sql, @params ) } ) {
		return $row;
	} else {
		return undef;
	}

}

1;

