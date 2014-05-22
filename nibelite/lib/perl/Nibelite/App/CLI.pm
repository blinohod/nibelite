package Nibelite::App::CLI;

=head1 NAME

Nibelite::App::CLI - a base class for command-line applications

=head1 SYNOPSIS

	MyApp->run(
		# Common options for a NetSDS application
	);

	package MyApp;
	
	use base 'Nibelite::App::CLI';
	
	sub action_foo {
		my ($this, %params) = @_;
		# do work for action foo
	}
	
	1;
	
	[user@host] $ myapp foo --var=value
	# will call action_foo ( var => value )

=cut

use strict;
use warnings;
use version; our $VERSION = 1.500;

use Exception::Class qw(TypeError ArgumentError);
use File::Basename qw(dirname);
use Nibelite::Auth;
use NetSDS::Exceptions;
use Getopt::Long;
use NetSDS::DBI;

use base 'NetSDS::App';
use constant CONF_DIR => '/opt/nibelite/etc';

sub config_file {
	my ( $this, $file_name ) = @_;
	my $name = $this->SUPER::config_file($file_name);
	unless ( -f $name && -r $name ) {
		$name = $this->CONF_DIR . "/nibelite.conf";
	}
	return $name;
}

sub new {
	my ( $class, %params ) = @_;
	my $this = $class->SUPER::new(
		daemon   => 0,    # run in the foreground
		verbose  => 1,
		has_conf => 1,    # is configuration file necessary
		infinite => 0,    # is infinite loop
		%params,
	);
	$this->mk_accessors('exitcode', 'dbh');
	$this->exitcode(0);
	return $this;
}

# Determine execution parameters from CLI
sub _get_cli_param {

	my ($this) = @_;

	my $conf    = undef;
	my $debug   = undef;
	my $daemon  = undef;
	my $verbose = undef;
	my $name    = undef;

	# Get command line arguments
	GetOptions(
		'conf=s'   => \$conf,
		'debug!'   => \$debug,
		'daemon!'  => \$daemon,
		'verbose!' => \$verbose,
	);

	# Set configuration file name
	if ($conf) {
		$this->{conf_file} = $conf;
	}

	# Set debug mode
	if ( defined $debug ) {
		$this->{debug} = $debug;
	}

	# Set daemon mode
	if ( defined $daemon ) {
		$this->{daemon} = $daemon;
	}

	# Set verbose mode
	if ( defined $verbose ) {
		$this->{verbose} = $verbose;
	}

} ## end sub _get_cli_param

sub initialize {
	my ($this, %params) = @_;
	my @ARGV2 = @ARGV;
	$this->{argv} = \@ARGV2;
	$this->SUPER::initialize(%params);
	my $dbh = eval { return NetSDS::DBI->new( %{ $this->conf->{db}->{main} } ); };
	if ($DBI::errstr) {
		NetSDS::Exception::DBI::Connect->throw( error => "Cannot start up without a DBMS. Please fix your configuration. (" . $DBI::errstr . ")" );
	}
	$this->dbh($dbh);
}

sub process {
	my ($this) = @_;
	my %params     = ();
	my @ARGV2      = @{$this->{argv}};
	my $subcommand = $ARGV2[0];    # Always the second parameter
	shift @ARGV2;                 # Strip subcommand off
	                              #
	                              # Getopt::Long does not have an option to stick unknown
	                              # options into a hash. We need to do it by hand.
	                              #
	my @OPTS       = @ARGV2;
	for (@OPTS) {
		if (/^--([\w-]+)=(.+)$/) {
			$params{$1} = $2;
			shift @ARGV2;
		} elsif (/^--([\w-]+)$/) {
			$params{$1} = 1;
		} elsif (/^(?<!--)(.+)$/) {
			if ( !defined( $params{"__non_options__"} ) ) {
				$params{"__non_options__"} = [];
			}
			push @{ $params{"__non_options__"} }, $1;
		} elsif (/^--$/) {
			shift @ARGV2;
			if ( !defined( $params{"__non_options__"} ) ) {
				$params{"__non_options__"} = [];
			}
			push @{ $params{"__non_options__"} }, @ARGV2;
			last;
		}
	} ## end for (@OPTS)
	$subcommand = "unknown" unless $subcommand;
	my $method_name = "action_" . $subcommand;
	$method_name =~ s/-/_/g;
	no strict 'refs';
	if ( $this->can($method_name) ) {
		$this->$method_name(%params);
		$this->{to_finalize} = 1;
	} else {
		$this->speak("Unknown action $subcommand");
		$this->{to_finalize} = 1;
		$this->exitcode(1);
	}
	use strict 'refs';
} ## end sub process

sub finalize {
	my ( $this, $msg ) = @_;
	$this->log( 'info', 'Application stopped' );
	exit( $this->exitcode );
}

sub action_unknown {
	my ( $this, %params ) = @_;
	$this->speak("Unknown action called, ARGV: ".join(" ", @ARGV));
}

1;
