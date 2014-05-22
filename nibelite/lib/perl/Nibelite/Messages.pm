package Nibelite::Messages;

use 5.8.0;
use strict;
use warnings;

use lib '/opt/nibelite/lib/perl';

use base qw(NetSDS::Class::Abstract);

=head1 NAME

Nibelite::Messages - API to Nibelite message queue

=head1 SYNOPSIS

	use Nibelite::Messages;

=head1 DESCRIPTION

C<Nibelite::Messages> module provides simple API to Nibelite
messaging subsystem.

=cut

use JSON;
use Time::HiRes qw( usleep );
use NetSDS::Util::String;
use NetSDS::Util::Convert;

use Nibelite::SMS;

use version; our $VERSION = '0.001';

#===============================================================================

=head1 CLASS API

=over

=item B<new([...])>

Common constructor for all objects inherited from Wono.

    my $object = Wono::SomeClass->new(%options);

=cut

#-----------------------------------------------------------------------
sub new {

	my ( $class, %params ) = @_;

	my $this = $class->SUPER::new(
		%params,
		table   => 'core.messages',
		queue   => 'core._messages_queue',
		archive => 'core._messages_archive',
	);

	unless ( $this->{dbh} ) {
		return $class->error("Can't connect to DBMS");
	}

	$this->mk_accessors('json');
	$this->json( JSON->new() );

	return $this;

} ## end sub new

__PACKAGE__->mk_accessors('dbh');

#***********************************************************************

=item B<fetch_from_queue($app_id, $limit)> - fetch routed messages from queue

Parameters: application ID, number of messages

	# Fetch next 10 messages
	my @messages = $msg->fetch_queue($app_id, 10);

NB: this method should be invoked

=cut

#-----------------------------------------------------------------------

sub fetch_from_queue {

	my ( $this, $app_id, $limit ) = @_;

	$limit ||= 10;

	my $sql = "select * from only core._messages_queue where msg_status = 'ROUTED' and dst_app_id = ? and date_received > (now() - '1 hours'::interval) order by prio desc, id limit ? for update";

	my @messages = @{ $this->dbh->fetch_call( $sql, $app_id, $limit ) };

	# Store fetched IDs to use later
	$this->{_processing_msgids} = [ map { $_->{id} } @messages ];

	# Decode message body from JSON if raw PDU retrieved
	map {
		if ( $_->{msg_type} eq 'SMS_RAW' )
		{
			$_->{msg_body}        = $this->json->decode( $_->{msg_body} );
			$_->{msg_body}->{udh} = conv_hex_str( $_->{msg_body}->{udh} );
			$_->{msg_body}->{ud}  = conv_hex_str( $_->{msg_body}->{ud} );
		}
	} @messages;

	return @messages;

} ## end sub fetch_from_queue

#***********************************************************************

=item B<processing_msg_ids()> - get IDs of fetched and not processed messages

	my @mids = $ms->processing_dmsg_ids;

=cut

sub processing_msg_ids {

	my ($this) = @_;

	return wantarray ? @{ $this->{_processing_msgids} } : scalar @{ $this->{_processing_msgids} };

}

=item B<enqueue_message(%msg)> - insert new message to queue

=cut

sub enqueue_message {

	my ( $this, %msg ) = @_;

	# Set default status for new messages
	$msg{msg_status} ||= 'NEW';

	# Process raw PDU body.
	#
	# If message body is a hashref - then convert it to JSON
	# Otherwise inset "as is" (possible pre-encoded data)
	if ( defined( $msg{msg_type} ) and ( $msg{msg_type} eq 'SMS_RAW' ) and ref( $msg{msg_body} ) ) {

		$msg{msg_body} = $this->json->encode(
			{
				udh    => conv_str_hex( $msg{msg_body}->{udh} ),
				ud     => conv_str_hex( $msg{msg_body}->{ud} ),
				coding => $msg{msg_body}->{coding},
				text   => $msg{msg_body}->{text} ? $msg{msg_body}->{text} : '',
			}
		);

	} else {

		# Determine encoding and number of PDU
		unless ( $msg{qty} ) {
			$msg{qty} = scalar string_to_pdu( $msg{msg_body}, detect_coding( $msg{msg_body} ) );
		}

	}

	my $sql_fields = join( ',', keys %msg );
	my $placeholders = join( ',', ( ("?") x scalar( keys %msg ) ) );

	my $sql = "insert into core._messages_queue ($sql_fields) values ($placeholders) returning id";

	my $res = $this->dbh->fetch_call( $sql, values %msg );

	return $res->[0]->{id};

} ## end sub enqueue_message

=item B<update_message($msg_id, %msg)> - update message

=cut

sub update_message {

	my ( $this, $msg_id, %msg ) = @_;

	my $sql_fields = join( ',', map { "$_ = ?" } keys %msg );

	my $sql = "update core._messages_queue set $sql_fields where id = $msg_id";

	my $res = $this->dbh->call( $sql, values %msg );

}

#***********************************************************************

=item B<make_reply($msg, %params)> - prepare reply message as hash

Paramters: original message hash reference, parameters to change

Returns: reply hash

Example:

	my %reply = $this->make_reply(\%request);

=cut 

#-----------------------------------------------------------------------

sub make_reply {

	my ( $this, $msg, %params ) = @_;

	my %rep = (
		refer_id   => $msg->{id},
		src_app_id => $msg->{dst_app_id},
		dst_app_id => $msg->{src_app_id},
		src_addr   => $msg->{dst_addr},
		dst_addr   => $msg->{src_addr},
		msg_body   => '',
		msg_status => 'ROUTED',
		%params,
	);

	return \%rep;

}

#***********************************************************************

=item B<create_dlr($msg_id, $dlr_msg)> - add DLR message 

Add delivery message to queue

=cut 

#-----------------------------------------------------------------------

sub create_dlr {

	# FIXME - FULL REFACTORING
	#
	my ( $this, $msg_id, $dlr_state, $dlr_msg ) = @_;

	my $rep_body = "STATE:$dlr_state; REPORT:$dlr_msg";

	if ( my ($msg) = $this->fetch( filter => ["id = $msg_id"] ) ) {

		my %dlr = $this->insert(
			msg_type   => 'MSG_DLR',                            # FIXME - this should be a type_id (integer)
			msg_body   => $this->quote($rep_body),
			refer_id   => $this->quote( $msg->{id} ),
			src_app_id => $this->quote( $msg->{dst_app_id} ),
			dst_app_id => $this->quote( $msg->{src_app_id} ),
			src_addr   => $this->quote( $msg->{dst_addr} ),
			dst_addr   => $this->quote( $msg->{src_addr} ),
		);

	} else {
		return $this->error("Can't find original message id = $msg_id");
	}

} ## end sub create_dlr

1;

__END__

=back

=head1 EXAMPLES

See C<samples> directory.

=head1 BUGS

Unknown yet

=head1 SEE ALSO

None

=head1 TODO

None

=head1 AUTHOR

Michael Bochkaryov <misha@rattler.kiev.ua>

=cut


