package Nibelite::App::Channel;

use 5.8.0;
use strict;
use warnings;

use lib '/opt/nibelite/lib/perl';

use base qw(Nibelite::App);

=head1 NAME

Nibelite::App::Channel - framework for Nibelite channel applications

=head1 SYNOPSIS

	ChannelApp->run(
		mode => receiver,
	);

	1;

	package ChannelApp;

	use base 'Nibelite::App::Channel';

	sub process {
		my ($this) = @_;

		print $this->app_conf('secret_param');

	}

=head1 DESCRIPTION


=cut

use version; our $VERSION = '0.001';

use Nibelite::Messages;

use Time::HiRes qw(sleep alarm time);

#===============================================================================

=head1 CONSTANTS

=cut

use constant DEFAULT_BANDWIDTH       => 10;    # channel bandwidth - SMS/sec
use constant DEFAULT_RETRIES         => 5;     # maximum sending retries
use constant DEFAULT_RETRY_TIMEOUT   => 5;     # number of resend retries
use constant DEFAULT_IDLE_TIMEOUT    => 5;     # idle loop timeout
use constant DEFAULT_SKIP_ITERATIONS => 5;     # how many iteration skip on idle channel

#===============================================================================

=head1 CLASS METHODS

=over

=cut

#-----------------------------------------------------------------------

sub initialize {

	my ( $this, %attrs ) = @_;

	$this->SUPER::initialize(%attrs);

	# Initialize Message Queue
	$this->mk_accessors('mq');
	$this->mq( Nibelite::Messages->new( dbh => $this->dbh ) );

	# Get channel type
	$this->mk_accessors('chan_type');
	$this->mk_accessors('chan_conf');
	$this->mk_accessors('chan_ids');

	if ( $this->conf->{chan_type} ) {

		$this->chan_type( $this->conf->{chan_type} );
		$this->_refresh_chan_conf();

	} else {
		$this->log( "error", "Channel type not defined in configuration file (chan_type = ...)" );
		$this->{to_finalize} = 1;
	}

} ## end sub initialize

sub _refresh_chan_conf {

	my ($this) = @_;

	if ( $this->debug() ) {
		$this->log( "debug", "Refreshing channels configuration" );
	}

	# Retrieve app_id for channel type
	my @app_ids = $this->get_channels( $this->chan_type() );
	$this->chan_ids( \@app_ids );

	# Clean channels configuration
	$this->{chan_conf} = {};

	# Retrieve configuration for channels
	foreach my $app_id (@app_ids) {

		if ( $this->debug() ) {
			$this->log( "debug", "Fetch configuration for channel [id=$app_id]" );
		}

		$this->{chan_conf}->{$app_id} = $this->get_app_conf($app_id);

		# Set default parameters
		$this->{chan_conf}->{$app_id}->{bandwidth}     ||= DEFAULT_BANDWIDTH;
		$this->{chan_conf}->{$app_id}->{retries}       ||= DEFAULT_RETRIES;
		$this->{chan_conf}->{$app_id}->{retry_timeout} ||= DEFAULT_RETRY_TIMEOUT;

	}
} ## end sub _refresh_chan_conf

sub main_loop {

	my ( $this, %params ) = @_;

	# Run startup hooks
	my $ret = $this->start();

	if ( $this->{mode} eq 'receiver' ) {

		# Retrieve new incoming event (MO message or DLR)
		# in transport specific format.
		while ( !$this->{to_finalize} ) {

			$this->_refresh_chan_conf();

			# Convert message to queue compatible hash
			# and push it into queue
			if ( my $msg = $this->process() ) {
				eval { $this->mq->enqueue_message(%$msg); };

				if ($@) {
					$this->log( 'warn', sprintf( "ERROR: Cannot insert message: from='%s'; to='%s; error=%s'", $msg->{src_addr}, $msg->{dst_addr}, $@ ) );
				}

			}

		}

	} elsif ( $this->{mode} eq 'sender' ) {

		while ( !$this->{to_finalize} ) {

			# Hash reference containing number of iterations
			# to skip on channel was defined as idle.
			my $skip_list = {};

			# Set to 0 if have traffic on channels
			my $idle_iteration  = 1;
			my $iteration_start = time();

			$this->_refresh_chan_conf();

			# Loop through channels
			foreach my $chan_id ( @{ $this->chan_ids() } ) {

				# Decrement number of iterations to skip
				if ( $skip_list->{$chan_id} ) {
					$skip_list->{$chan_id}--;
					next;
				}

				$this->dbh->begin();

				# Fetch messages from queue
				if ( my @msgs = $this->fetch_messages( $chan_id, $this->chan_conf->{$chan_id}->{bandwidth} ) ) {

					$idle_iteration = 0;

					# Process each message with transport specific logic
					foreach my $msg (@msgs) {

						if ( my $res = $this->process($msg) ) {
							$this->mq->update_message( $msg->{id}, msg_status => 'SENT' );
						} else {
							$this->mq->update_message( $msg->{id}, msg_status => 'FAILED' );
						}

					}

				} else {

					# No outgoing traffic on channel.
					# Skip next few iterations to decrease system load.
					$skip_list->{$chan_id} = DEFAULT_SKIP_ITERATIONS;

				}

				$this->dbh->commit();

			} ## end foreach my $chan_id ( @{ $this...})

			# If have some time to sleep - that's good :-)
			if ( time() < ( $iteration_start + 1 ) ) {
				sleep( $iteration_start + 1 - time() );
			}

			# No any messages to send - sleep well
			if ($idle_iteration) {
				$this->log( "debug", "Outgoing queue is empty. Sleeping." );
				sleep DEFAULT_IDLE_TIMEOUT;
				$skip_list = {};    # clear idle channels
			}

		} ## end while ( !$this->{to_finalize...})

	} else {

		return $this->error("Unknown channel application mode");

	}

	# Run finalize hooks
	$ret = $this->stop();

} ## end sub main_loop

sub fetch_messages {

	my ( $this, $chan_id, $limit ) = @_;

	my @msgs = $this->mq->fetch_from_queue( $chan_id, $limit );

	return @msgs;

}

sub process {

	my ( $this, $msg ) = @_;

	$this->log( "error", "Caught MT event but no process() method defined in application!" );

	return $this->error("No process() method defined with MT logic");

}

sub determine_chan_app {

	my ( $this, $smsc, $sn ) = @_;

	unless ( defined $smsc and ($smsc) ) {
		return $this->error("Cannot lookup channel app_id without SMSC provided");
	}
	unless ( defined $sn and ($sn) ) {
		return $this->error("Cannot lookup channel app_id without Service Number provided");
	}

	# Lookig for applications with corresponding 'smsc' and 'sn' parameters
	my ($app_id) = grep { ( $this->chan_conf->{$_}->{smsc} eq $smsc ) and ( $this->chan_conf->{$_}->{sn} eq $sn ) } ( keys %{ $this->chan_conf } );

	return $app_id;

}
1;

__END__

=back

=head1 AUTHOR

Michael Bochkaryov <misha@rattler.kiev.ua>

=head1 LICENSE

Copyright (C) 2008-2010 Net Style Ltd.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

=cut

