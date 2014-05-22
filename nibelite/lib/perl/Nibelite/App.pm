package Nibelite::App;

use 5.8.0;
use strict;
use warnings;

use base qw(NetSDS::App);
use constant CONF_DIR => '/opt/nibelite/etc';

=head1 NAME

Nibelite::App - easy to use development API for Nibelite

=head1 SYNOPSIS

	use base 'Nibelite::App';

	sub process {
		my ($this) = @_;

		print $this->app_conf_param('secret_param');

	}

=head1 DESCRIPTION

C<Nibelite::App> module provides easy to use framework for writing
Nibelite applications.

Nibelite is an old school mobile VAS application server developed
to provide premium rate SMS services in short time. Later it was
updated to handle MMS, WAP and WWW services as well.

=cut

use NetSDS::DBI;
use Nibelite::Core;

use version; our $VERSION = '0.001';

#===============================================================================

=head1 CLASS METHODS

=over

=item B<dbh()> - DBMS handler

	# Do platform suicide ;-)
	$app->dbh->call('delete from core.apps');

=cut

#-----------------------------------------------------------------------

__PACKAGE__->mk_accessors('dbh');

#===============================================================================

=item B<app_id()> - get application ID

	my $app_id = $this->app_id()

=cut

#-----------------------------------------------------------------------

__PACKAGE__->mk_accessors('app_id');    # Optional

#===============================================================================

=item B<app_name()> - get/set application name

	my $app_name = $this->app_name()

=cut

#-----------------------------------------------------------------------

__PACKAGE__->mk_accessors('app_name');    # Optional

#===============================================================================

=item B<core()> - Nibelite core API

=cut

#-----------------------------------------------------------------------

__PACKAGE__->mk_accessors('core');

#-----------------------------------------------------------------------

sub config_file {
	my ( $this, $file_name ) = @_;
	my $name = $this->SUPER::config_file($file_name);
	unless ( -f $name && -r $name ) {
		$name = $this->CONF_DIR . "/nibelite.conf";
	}
	return $name;
}

sub initialize {

	my ( $this, %attrs ) = @_;

	$this->SUPER::initialize(%attrs);

	# Initialize DBMS connection
	$this->dbh( NetSDS::DBI->new( %{ $this->conf->{db}->{main} } ) );

	unless ( $this->dbh->dbh ) {
		die "Can't connect to DBMS.\n";
	}

	$this->core( Nibelite::Core->new( dbh => $this->dbh() ) );

	# Get application ID
	if ( $this->conf->{app_name} ) {
		$this->{app_name} = $this->conf->{app_name};
		$this->app_id( $this->get_app_id( $this->conf->{app_name} ) );
	} else {
		$this->log('warning', "Unnamed application");
		$this->{app_name} = undef;
		$this->app_id(undef);
	}

} ## end sub initialize

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

	my $row = $this->dbh->fetch_call( "select id from core.apps where name = ?", $app_name );

	return $row->[0]->{id} || undef;

}

#***********************************************************************

=item B<app_conf_param($tag)> - get application parameter from C<apps_conf> table

Paramters: configuration tag

Returns: configuration value

Example:

	# Get 'sendsms_url' parameter for application
	my $url = $this->app_conf_param('sendsms_url');

=cut 

#-----------------------------------------------------------------------

sub app_conf_param {

	my ( $this, $tag ) = @_;

	if ( $this->app_id ) {
		return $this->core->get_app_conf_param( $this->app_id, $tag );
	} else {
		return $this->error("Can't get parameter for unknown application ID");
	}
}

#***********************************************************************

=item B<get_app_conf($app_id)> - get application conf by id

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


=head1 BUGS

Unknown yet

=head1 SEE ALSO

None

=head1 TODO

None

=head1 AUTHOR

Michael Bochkaryov <misha@rattler.kiev.ua>

=cut


