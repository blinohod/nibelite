
=head1 NAME

Nibelite::Auth - authentication API for Nibelite

=head1 SYNOPSIS

	use Nibelite::Auth;

	my $db = Nibelite::Auth->new(
		dsn    => 'dbi:Pg:dbname=test_netsds;host=192.168.1.50;port=5432',
		login  => 'netsds',
		passwd => '',
		schema => 'auth',
	);

	my $user_id = $db->auth_passwd( 'misha', 'test' );


=head1 DESCRIPTION

C<Nibelite::Auth> module provides Perl5 API for accessing this
subsystem from applications.

=cut

package Nibelite::Auth;

use 5.8.0;
use strict;
use warnings;

use version; our $VERSION = '1.000';

use Data::Dumper;

use NetSDS::Util::Misc;

use base 'NetSDS::DBI';

use constant DEFAULT_TTL => 86400;    # Default session TTL is one day

#***********************************************************************

=head1 CLASS API

=over

=item B<new(%params)> - constructor

Paramters: the same as NetSDS::DBI and also C<schema> 

=cut 

#-----------------------------------------------------------------------

sub new {

	my ( $class, %params ) = @_;

	my $self = $class->SUPER::new(
		%params,
	);

	# Set default schema for DBMS
	$self->{schema} = $params{schema} || 'core';

	return $self;

}

#***********************************************************************

=item B<auth_passwd($login, $passwd)> - authenticate by password

Paramters: login, password (plain text)

Returns: in scalar context return is user_id (integer), in list context
return user_id and session key. Or undef if cant authenticate.

	# Authenticate user by password
	$user_id = $auth->auth_passwd('petya', 'topSecret');

	# Authenticate and create new session if authenticated
	($user_id, $sess) = $auth->auth_passwd('vasya', 'topSecret',
		make_session => 1,
		ttl => 3600, # one hour session TTL
	);

=cut 

#-----------------------------------------------------------------------

sub auth_passwd {

	my ( $self, $login, $passwd, %params ) = @_;
	my ($user_id) = $self->call( "select " . $self->{schema} . ".auth_passwd(?,?)", $login, $passwd )->fetchrow_array;

	my $session_key = undef;

	if ($user_id) {
		if ( $params{make_session} ) {
			my $ttl = $params{ttl} ? $params{ttl} + 0 : DEFAULT_TTL;
			($session_key) = $self->call( "select " . $self->{schema} . ".create_session(?,?)", $user_id, $ttl )->fetchrow_array;
		}
	}

	return wantarray ? ( $user_id, $session_key ) : $user_id;
}

#***********************************************************************

=item B<auth_session($session)> - authenticate by session key

Paramters: session key string, optional parameters (hash)

Optional paramters as hash:

	* update - if 1 then prolongate session
	* ttl - session TTL prolongation in sesconds

Returns: user_id (integer) or null

	# Just authenticate by session
	$user_id = $auth->auth_session($sess_key);

	# Authenticate and prolongate session for one hour
	$user_id = $auth->auth_session($sess_key, update => 1, ttl => 3600);

=cut 

#-----------------------------------------------------------------------

sub auth_session {

	my ( $self, $session, %params ) = @_;

	# Try to authenticate user against session key
	my ($user_id) = $self->call( "select " . $self->{schema} . ".auth_session(?)", $session )->fetchrow_array;

	# Try to prolongate session if required
	if ($user_id) {
		if ( $params{update} ) {
			my $ttl = $params{ttl} ? $params{ttl} + 0 : DEFAULT_TTL;
			$self->call( "select " . $self->{schema} . ".update_session(?,?)", $session, $ttl );
		}
	}

	return $user_id;
}

#***********************************************************************

=item B<delete_session($session)> - drop session

Paramters: session key (string)

This method just drop session by it's key.

=cut 

#-----------------------------------------------------------------------

sub delete_session {

	my ( $self, $session ) = @_;
	return $self->call( "select " . $self->{schema} . ".delete_session(?)", $session )->fetchrow_array;

}

#***********************************************************************

=item B<clear_sessions()> - drop all outdated sessions

	# Clear sessions table
	$auth->clear_session()

=cut 

#-----------------------------------------------------------------------

sub clear_sessions {

	my ($self) = @_;

	$self->call( "select " . $self->{schema} . ".clear_sessions()" );

	return 1;
}

#***********************************************************************

=item B<authorize($user_id, $service, $action)> - authorize user

Paramters: user Id (int), service (string), action (string)

Returns: true if user allowed to execute this action

	if ($auth->authorize($uid, 'smsgw', 'sendsms')) {
		send_sms_messages();
	} else {
		warn_and_log();
	}

=cut 

#-----------------------------------------------------------------------

sub authorize {

	my ( $self, $user_id, $service, $action ) = @_;

	return $self->call( "select " . $self->{schema} . ".authorize(?,?,?)", $user_id, $service, $action )->fetchrow_array;
}

#***********************************************************************

=item B<get_user()> - get user record by id

Paramters: user id (integer)

Returns: user info as hash reference

	my $user = $auth->get_user($user_id);
	print "Login: " . $user->{login};

=cut 

#-----------------------------------------------------------------------

sub get_user {

	my ( $self, $uid ) = @_;

	return $self->call( "select * from " . $self->{schema} . ".users where id=?", $uid )->fetchrow_hashref;

}

1;

=head1 TODO

None

=head1 AUTHOR

Michael Bochkaryov <misha@rattler.kiev.ua>

=cut

