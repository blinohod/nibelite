
=head1 NAME

Nibelite::Util - miscellaneous reusable routines for Nibelite

=head1 SYNOPSIS

	use Nibelite::Util qw(str_format);

	my $string = str_format("Hello, %username!", username => "Vasya"); # -> Hello, Vasya!
	my $string = str_format("Hello, %%username!", username => "Vasya"); # -> Hello, %username!



=head1 DESCRIPTION

C<NetSDS::Util::String> module contains functions may be used to quickly solve
string processing tasks like parsing, recoding, formatting.

As in other NetSDS modules standard encoding is UTF-8.

=cut

package Nibelite::Util;

use 5.8.0;
use warnings 'all';
use strict;

use base 'Exporter';

use version; our $VERSION = '1.000';

our @EXPORT = qw(
  str_format
);

=head2 str_format( $format, %params ) - format a string

Replaces "%placeholders" with values from %params in the format string.
Use \% if a placeholder is not meant. For example:

	print str_format('Your username is %username', username => 'john'), "\n"; # Your username is john
	print str_format('Your \%username is %username', username => 'john'), "\n"; # Your %username is john
	print str_format('Your \\\\%username is %username', username => 'john'), "\n"; # Your \%username is john

=cut

sub str_format {
	my ( $format_string, %params ) = @_;
	$format_string ||= '';
	map { $format_string =~ s/(?<!\\)%$_/$params{$_}/gs; } keys(%params);
	$format_string =~ s/\\%/%/g;
	return $format_string;
}

1;
