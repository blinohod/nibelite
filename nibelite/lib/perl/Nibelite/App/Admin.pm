package Nibelite::App::Admin;

use strict;
use warnings;
use version; our $VERSION = 1.000;

use lib '/opt/nibelite/lib/perl';

use NetSDS::Exceptions;

use CGI::Fast;
use CGI::Cookie;

use JSON;

use NetSDS::DBI;
use NetSDS::Template;
use NetSDS::Util::String;

use Nibelite::Auth;
use Data::Dumper;

use base 'NetSDS::App';

sub new {

	my ( $class, %params ) = @_;

	my $this = $class->SUPER::new(
		daemon   => 0,
		has_conf => 1,
		cgi      => undef,
		mime     => undef,
		cookie   => undef,
		status   => undef,
		%params,
	);

	$this->mk_accessors(
		'cgi',       # CGI request
		'json',      # JSON encoder/decoder
		'tpl',       # template processor
		'dbh',       # NetSDS::DBI handler
		'authdb',    # AuthDB handler
		'template_dir',
		'remote_ip',
		'template',
		'uid',       # User ID in AuthDB
		'user',      # User structure
		'cookie',
	);

	return $this;
} ## end sub new

# Init from config, CLI etc.
#
sub initialize {

	my ( $this, %params ) = @_;
	$this->SUPER::initialize(%params);

	# Initialize JSON encoder/decoder
	$this->json( JSON->new() );
	$this->json->pretty(1);
	$this->json->utf8(1);

	# Template engine
	$this->tpl(
		NetSDS::Template->new(
			dir => '/opt/nibelite/gui/templates',
		)
	);

	# Connect to DBMS
	eval { $this->dbh( NetSDS::DBI->new( %{ $this->conf->{db}->{main} } ) ); };
	if ( my $exc = NetSDS::Exception::DBI->caught ) {
		warn "Oops";
	}

	# Connect AuthDB handler
	$this->authdb( Nibelite::Auth->new( %{ $this->conf->{db}->{main} } ) );

	$this->_init_i18n();

} ## end sub initialize

sub main_loop {

	my ( $this, %params ) = @_;

	#$CGI::Fast::Ext_Request = FCGI::Request( \*STDIN, \*STDOUT, \*STDERR, my %ENV, 0, FCGI::FAIL_ACCEPT_ON_INTR() );

	while ( $this->cgi( CGI::Fast->new() ) ) {

		$this->_clear_session();
		$this->_set_req_cookies();

		$this->_authenticate();
		$this->_set_translations();

		$this->handle_request();

	}

}

sub handle_request {

	my ($this) = @_;

	# Parse PATH_INFO to determine action and output format
	if ( $this->cgi->path_info() =~ /^\/(\w[\w\d\_]*)\.([\w\d]+)$/ ) {

		my ( $action, $out_format ) = ( $1, $2 );

		my $sub_validate = 'validate_' . $action;
		my $sub_process  = 'process_' . $action;

		# Cannot find proper action method
		unless ( $this->can($sub_process) ) {

			print $this->cgi->header( -status => '404 Document not found', -type => 'text/html', );
			print "<h1>Wrong action called!</h1>";
			return undef;

		}

		my $res = $this->$sub_process();

		# Determine what to do with return
		if ( ref($res) ) {

			if ( ref($res) eq 'CODE' ) {

				&$res;
				return 1;

			} elsif ( ref($res) eq 'HASH' ) {

				# Return JSON structure
				print $this->cgi->header( -status => '200 OK', -type => 'application/json', -charset => 'utf-8', -cookie => $this->cookie, );
				print $this->json->encode($res);
				return 1;

			} else {

				# Incorrect call
				print $this->cgi->header( -status => '404 Document not found', -type => 'text/html', );
				print "<h1>Incorrect call format!</h1>";
				return undef;

			}

		} else {

			# Scalar data
			print $this->cgi->header( -status => '200 OK', -type => 'text/html', -charset => 'utf-8', -cookie => $this->cookie, );
			print $res;
			return 1;
		}

	} else {

		# Incorrect call
		print $this->cgi->header( -status => '404 Document not found', -type => 'text/html', );
		print "<h1>Incorrect call format!</h1>";
		return undef;

	}

} ## end sub handle_request

# ============================= SUPPLEMENTARY METHODS =============================

sub set_cookie {

	my ( $this, %par ) = @_;
	push @{ $this->{cookie} }, $this->cgi->cookie( -name => $par{name}, -value => $par{value}, -expires => $par{expires} );

}

sub get_cookie {

	my ( $this, $name ) = @_;
	return $this->{_req_cookies}->{$name}->{value};

}

sub translate {

	my ( $this, $tag, $lang ) = @_;

	my ( $srv, $key ) = ( '', '' );

	if ( $tag =~ /^([^\/]+)\/([^\/]+)$/ ) {
		( $srv, $key ) = ( $1, $2 );

	} else {
		$srv = 'core';
	}

	if ( my ($trans) = grep { ( $_->{service} eq $srv ) and ( $_->{keyword} eq $key ) and ( $_->{lang} eq $this->user->{lang} ) } @{ $this->{_translations} } ) {
		return $trans->{value};
	} else {
		return $tag;
	}

} ## end sub translate

sub render {

	my ( $this, $tmpl, %params ) = @_;

	return $this->tpl->render( $tmpl, %params, %{ $this->{_tpl_trans} } );

}

sub authorize {

	my ( $this, $srv, $action ) = @_;

	return $this->authdb->authorize( $this->uid, $srv, $action );

}

# ============================= INTERNAL METHODS =============================

sub _authenticate {

	my ($this) = @_;

	my $sess_cookie = $this->get_cookie('SESSID');
	( $this->{session_key} ) = $sess_cookie ? @{$sess_cookie} : undef;

	$this->uid( $this->authdb->auth_session( $this->{session_key}, update => 1, ttl => 604800 ) );

	if ( $this->uid ) {
		$this->user( $this->authdb->get_user( $this->uid ) );
		$this->set_cookie( name => 'SESSID', value => $this->{session_key}, expires => '+7d' );
	}

}

sub _set_req_cookies {

	my ($this) = @_;
	my %cookies = CGI::Cookie->fetch();
	$this->{_req_cookies} = \%cookies;
	return 1;

}

sub _clear_session {

	my ($this) = @_;

	$this->{_req_cookies} = {};
	$this->{uid}          = undef;
	$this->{session_key}  = undef;

	$this->{user} = {
		lang => 'en',
	};

}

sub _init_i18n {

	my ($this) = @_;

	$this->{_translations} = $this->dbh->fetch_call("select * from core.translations");

}

sub _set_translations {

	my ($this) = @_;

	$this->{_tpl_trans} = {};

	foreach my $trans ( grep { $_->{lang} eq $this->user->{lang} } @{ $this->{_translations} } ) {
		$this->{_tpl_trans}->{ 't_' . $trans->{service} . '_' . $trans->{keyword} } = $trans->{value};
	}

}

1;

