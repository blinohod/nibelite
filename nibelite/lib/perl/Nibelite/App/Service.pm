package Nibelite::App::Service;

use 5.8.0;
use strict;
use warnings;

use base qw(Nibelite::App);

=head1 NAME

Nibelite::App::Service - framework for Nibelite SMS service applications

=head1 SYNOPSIS

	ServiceApp->run(
		mode => receiver,
	);

	1;

	package ServiceApp;

	use base 'Nibelite::App::Service';

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

=head1 OBJECT METHODS

=over

=initialize() - initialization (overrides Nibelite::App)


=cut

#-----------------------------------------------------------------------

sub initialize {

	my ( $this, %attrs ) = @_;

	$this->SUPER::initialize(%attrs);

	# Initialize Message Queue
	$this->mk_accessors('mq');
	$this->mq( Nibelite::Messages->new( dbh => $this->dbh ) );

	# Config
	$this->mk_accessors('app_conf');

	if ( $this->app_id ) {
		$this->app_conf( $this->get_app_conf( $this->app_id ) );
	} else {
		$this->app_conf( {} );
		$this->log( "error", "Cannot found core.apps record for processing" );
		$this->{to_finalize} = 1;
	}

} ## end sub initialize

=item app_conf_param() - updates app_conf entry from DB

In addition to what superclass method does, updates the entry
in app_conf.

=cut

sub app_conf_param {
	my ( $this, $tag ) = @_;
	my $result = $this->SUPER::app_conf_param($tag);
	$this->app_conf->{$tag} = $result;
	return $result;
}

sub main_loop {

	my ( $this, %params ) = @_;

	# Run startup hooks
	my $ret = $this->start();

	while ( !$this->{to_finalize} ) {

		# Re-read application configuraion
		$this->app_conf( $this->get_app_conf( $this->app_id ) );

		# FIXME - implement result processing
		my $res = $this->process();

	}

	# Run finalize hooks
	$ret = $this->stop();

} ## end sub main_loop

=item fetch_messages()

Example:

	# Fetch messages from queue
	my @messages = $app->fetch_messages($app->app_id, $max_messages);

=cut

sub fetch_messages {

	my ( $this, $app_id, $limit ) = @_;

	my @msgs = $this->mq->fetch_from_queue( $app_id, $limit );

	return @msgs;

}

=item process() - hook that should be overwritten in application

=cut

sub process {

	my ( $this, $msg ) = @_;

	$this->log( "warning", "Caught MT event but no process() method defined!" );

	return $this->error("No process() method defined with MT logic");

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

