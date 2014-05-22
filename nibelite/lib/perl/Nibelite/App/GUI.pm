package Nibelite::App::GUI;

=head1 NAME

Nibelite::App::GUI - service runner class

=head1 SYNOPSIS

NetSDS::Portal::App->run(
	use_pidfile => 1,
	debug => 0,
	...
);

=head1 DESCRIPTION

The runner is loaded from the executable runner script. It looks up
service class passed in the conffile or from the command line (command line
takes precedence), loads it and starts serving FastCGI requests. Requests
are dispatched to the service class; parameters are deserialized. Received 
structured data is serialized to JSON and passed back to the client.

Nibelite::App::GUI takes care of all WWW- and CGI-specific things, so that
the business logic scripts (mostly) don't need to care about things like JSON, CGI
and HTTP.

If a service needs to use HTTP-specific routines, such as access to CGI object,
this must be regarded either as a service misdesign, or the runner design
oversight, and reported as a bug.

=head1 REFERENCE

=cut

use strict;
use warnings;
use version; our $VERSION = 1.500;

use Exception::Class qw(TypeError ArgumentError);
use JSON;
use File::Basename qw(dirname);
use Nibelite::Auth;
use NetSDS::Exceptions;
use Getopt::Long;
use Template;
use Nibelite::WebUser;
use Encode;

use base 'NetSDS::App::FCGI';
use constant CONF_DIR => '/opt/nibelite/etc';

#
# Make accessors, support objects
#
sub new {
	my ( $class, %params ) = @_;
	my $this = $class->SUPER::new(%params);
	bless $this, $class;
	$this->mk_accessors(qw(module module_storage json authdb dbh template_dir remote_ip template user));
	$this->json( JSON->new() );
	$this->module_storage( {} );
	return $this;
}

sub config_file {
	my ( $this, $file_name ) = @_;
	my $name = $this->SUPER::config_file($file_name);
	unless ( -f $name && -r $name ) {
		$name = $this->CONF_DIR . "/nibelite.conf";
	}
	return $name;
}

#
# Init from config, CLI etc.
#
sub initialize {
	my ( $this, %params ) = @_;
	$this->SUPER::initialize(%params);
	if ( !defined( $this->conf ) ) {
		$this->conf( {} );
	}
	my $dbh = eval { return NetSDS::DBI->new( %{ $this->conf->{db}->{main} } ); };
	if ($DBI::errstr) {
		NetSDS::Exception::DBI::Connect->throw( error => "Cannot start up without a DBMS. Please fix your configuration. (" . $DBI::errstr . ")" );
	}
	$this->dbh($dbh);
	$this->connect_authdb( %{ $this->conf->{db}->{main} } );
	my $template_dir;
	GetOptions( 'template-dir=s' => \$template_dir );
	unless ($template_dir) {
		$template_dir = ( $this->conf->{template_dir} or $params{template_dir} or dirname($0) or '.' );
	}
	$this->template_dir($template_dir);
}

sub connect_authdb {
	my ( $this, %params ) = @_;
	$this->authdb( Nibelite::Auth->new(%params) );
	if ( $this->authdb ) {
		$this->log( "info", "Successfully connected to AAA data source" );
	} else {
		$this->log( "error", "Cannot connect to AAA data source" );
		NetSDS::Exception::DBI::Connect->throw( error => "Cannot connect to AAA data source" );
	}
}

=head2 access(method_name, [ acl1, acl2, ... ])

Define access rules for an action method.

=head3 Synopsis

	sub action_something {
		....
	}
	
	__PACKAGE__->access('action_something', 'service.action1', 'service.action2');
	
=head3 Description

This method wraps an action in a subroutine which checks access privileges.
When invoked with only method name, it will check that the user is authenticated.
When additionally passed a list of strings "serv
ce.action", will check against
user privilege for these service.action pairs. Access is only granted if at least
one pair matches.

=cut

sub access {
	my ( $class, $name, @aclset ) = @_;
	my $code = \*{"$class::$name"};
	unless ($code) {
		ArgumentError->throw( error => "Cannot define access for undefined method $class::$name" );
	}
	*{"$class::$name"} = sub {
		my ( $this, $jsondata, %params ) = @_;
		if ( $this->user->is_authenticated() && scalar(@aclset) && $this->is_authorized(@aclset) ) {
			my @results = &$code(@_);
			return @results;
		}
		return {}, { -http => '403 Forbidden' };
	};
}

=head2 authenticate

Associates session cookie with a user session. This is done on a per-request
basis. It is not called directly.

=cut

sub authenticate {
	my ($this)      = @_;
	my $sess_cookie = $this->get_cookie('SESSID');
	my ($sess_key)  = $sess_cookie ? @{$sess_cookie} : undef;
	$this->user( NetSDS::WebUser->new( $this->authdb, '', $this ) );
	if ($sess_key) {
		$this->user()->authenticate( session_key => $sess_key );
	}
}

=head2 is_authorized

Returns true if current session belongs to a registered user.

=cut

sub is_authorized {
	my ( $this, @acls ) = @_;
	foreach my $acl (@acls) {
		if ( !$this->user()->authorize($acl) ) {
			return 0;
		}
	}
	return 1;
}

sub get_cgi_params {
	my ($this) = @_;
	my @keys = $this->cgi->param();
	my %params;
	foreach my $key (@keys) {
		my @value = $this->cgi->param($key);
		if ( scalar(@value) == 1 ) {
			$params{$key} = $value[0];
		} else {
			$params{$key} = \@value;
		}
	}
	return %params;
}

sub determine_action {
	my ( $this, $path ) = @_;
	$path =~ /\/([\w0-9-]*)(?:\.([\w0-9-]+))?$/i;
	my $action     = ( $1 or "" );
	my $dispatcher = ( $2 or "" );
	$action     =~ s/-/_/g;
	$dispatcher =~ s/-/_/g;
	return lc($action), lc($dispatcher);
}

sub action {
	my ( $this, $name, $jsondata, %params ) = @_;
	if ( $this->can( 'action_' . $name ) ) {
		my $m = 'action_' . $name;
		$this->authenticate();
		return $this->$m( $jsondata, %params );
	}
	return {}, { -http => '404 Not found (action)' };
}

sub dispatch_json {
	my ( $this, $action ) = @_;
	$this->mime('text/json');
	my %params   = $this->get_cgi_params();
	my $jsondata = undef;
	my $ctype    = $this->cgi->http('Content-type');
	if ( ( defined($ctype) ) && ( ( $ctype eq 'text/json' ) || ( $ctype eq 'application/json' ) ) && defined( $this->cgi->param('POSTDATA') ) ) {
		$jsondata = $this->json->decode( $this->cgi->param('POSTDATA') );
	}
	my $results = { data => {}, control => {} };
	( $results->{data}, $results->{control} ) = eval { return $this->action( $action, $jsondata, %params ); };
	if ( my $e = Exception::Class->caught() ) {
		$results->{data}    = {};
		$results->{control} = {
			-http   => '500',
			message => $e->message
		};
	}
	if ( $results->{control}->{'-http'} ) {
		$this->status( $results->{control}->{'-http'} );
		delete $results->{control}->{'-http'};
	}
	my $d = decode("utf-8", $this->json->encode($results));
	$this->data( $d );
} ## end sub dispatch_json

sub dispatch_html {
	my ( $this, $action ) = @_;
	$this->mime('text/html');
	my $results  = { data => {}, control => {} };
	my %params   = $this->get_cgi_params();
	my $jsondata = undef;
	my $ctype    = $this->cgi->http('Content-type');
	$this->template( $this->template_dir() . "/" . ( $action or "unknown" ) . ".tt" );

	( $results->{data}, $results->{control} ) = eval { return $this->action( $action, \%params, %params ); };
	if ( my $e = Exception::Class->caught() ) {
		$results->{data}    = {};
		$results->{control} = {
			-http   => '500',
			status  => 'error',
			message => $e->message
		};
	}
	unless ( $results->{data} ) {
		$results->{data} = {};
	}
	unless ( $results->{control} ) {
		$results->{control} = {};
	}
	if ( $results->{control}->{'-http'} ) {
		$this->status( $results->{control}->{'-http'} );
		delete $results->{control}->{'-http'};
	}
	if ( defined( $results->{control}->{status} ) && ( $results->{control}->{status} eq 'error' ) ) {
		$this->log( $results->{control}->{message} );
	}
	my $output   = '';
	my $template = Template->new(
		INCLUDE_DIR => $this->template_dir(),
		RELATIVE    => 1,
		ABSOLUTE    => 1,
	);
	$template->process( $this->template(), $results->{data}, \$output );
	$this->data($output);
	unless ( defined($output) ) {
		$this->data("");
	}
	my $e = $template->error();
	if ($e) {
		$this->log($e);
	}
} ## end sub dispatch_html

sub process {
	my ($this) = @_;
	my ( $action, $dispatcher ) = $this->determine_action( $this->cgi->path_info() );
	if ( $this->can( 'dispatch_' . $dispatcher ) && $this->can( 'action_' . $action ) ) {
		$this->remote_ip( $this->cgi->remote_addr() );
		my $disp = 'dispatch_' . $dispatcher;
		$this->$disp($action);
	} else {
		$this->mime('text/plain');
		$this->status('404 Not found (type)');
		$this->data('404 Not Found');
	}
}

1;
