
=head1 NAME

Nibelite::Core - access to core functions of Nibelite VAS platform

=head1 SYNOPSIS

	use Nibelite::Core;
	...
	my $core = Nibelite::Core->new($cbh);


=head1 DESCRIPTION

C<Nibelite::Core> module provides access to core Nibelite functionality.

=cut

package Nibelite::Core;

use 5.8.0;
use strict;
use warnings;

use base qw(NetSDS::Class::Abstract);

use version; our $VERSION = '0.001';

#===============================================================================

=head1 CONSTRUCTOR

=over

=item B<new()> - constructor

Example:

	# Initialize Nibelite core API
	$core = Nibelite::Core->new(dbh => $dbh);

=cut

sub new {

	my ( $class, %params ) = @_;

	my $this = $class->SUPER::new(%params);

	unless ( $this->{dbh} ) {
		return $class->error("No DBMS handler provided");
	}
	
	return $this;

}

#-----------------------------------------------------------------------

=back

=head1 OBJECT METHODS

=over

=item B<dbh()> - DBMS handler

	# Do platform suicide ;-)
	$app->dbh->call('delete from core.apps');

=cut

#-----------------------------------------------------------------------

__PACKAGE__->mk_accessors('dbh');

#***********************************************************************

=item B<get_app_id($app_name)> - get application id from DB

Paramters: application name 

Returns: app id

	# Get application ID for 'chan_mts_1234'
	my $app_id = $this->get_app_id('chan_mts_1234');

=cut 

#-----------------------------------------------------------------------

sub get_app_id {

	my ( $this, $app_name ) = @_;

	my ($row) = $this->dbh->fetch_call( "select id from core.apps where name = ?", $app_name );

	unless ($row) {
		$this->log( 'warning', "Cannot get application by name [$app_name]" );
	}

	return $row->{id} || undef;

}

#***********************************************************************

=item B<get_app_conf_param($app_id, $tag)> - get application parameter from C<apps_conf> table

Paramters: configuration tag

Returns: configuration value

Example:

	# Get 'sendsms_url' parameter for application
	my $url = $this->app_conf_param('sendsms_url');

=cut 

#-----------------------------------------------------------------------

sub get_app_conf_param {

	my ( $this, $app_id, $tag ) = @_;

	my ($row) = $this->dbh->fetch_call( "select value from core.apps_conf where tag = ? and app_id = ?", $tag, $app_id );

	unless ($row) {
		$this->log( 'warning', "Cannot get app_conf paramter [app_id:$app_id] [tag:$tag" );
	}

	return defined( $row->[0]->{value} ) ? $row->[0]->{value} : undef;

}

#***********************************************************************

=item B<get_app_conf($app_id)> - get application configuration by id

Paramters: application id 

Returns: application config as hash reference

	# Get config for application with id == 42
	my $app_conf = $this->get_app_conf(42);
	
	# "Douglas Adams" expected :-)
	print $app_conf->{author};

=cut 

#-----------------------------------------------------------------------

sub get_app_conf {

	my ( $this, $app_id ) = @_;

	my $res = $this->dbh->fetch_call( "select tag,value from core.apps_conf where app_id = ?", $app_id );

	my $conf = {};
	$conf->{ $_->{tag} } = $_->{value} for ( @{$res} );    # convert to hashref
	return $conf;

}

#***********************************************************************

=item B<get_channels($chan_type)> - get active channels IDs by type

Paramters: channel type

Returns: list of channels app_id

	my @kannel_channels = $app->get_channel('kannel');

=cut 

#-----------------------------------------------------------------------

sub get_channels {

	my ( $this, $chan_type ) = @_;

	my @rows = map { $_->{id} } @{ $this->dbh->fetch_call( "select core.get_active_channels(?) as id", $chan_type ) };

	return @rows;
}

1;

__END__

=back

=head1 EXAMPLES

None

=head1 BUGS

None

=head1 SEE ALSO

None

=head1 TODO

None

=head1 AUTHOR

Michael Bochkaryov <misha@rattler.kiev.ua>

=cut


