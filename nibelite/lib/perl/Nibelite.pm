package Nibelite;

use 5.8.0;
use strict;
use warnings;

use base 'Exporter';

our @EXPORT = qw(
  tpl
);


sub tpl {

	my ( $tpl, %params ) = @_;

	map { $tpl =~ s/%$_/$params{$_}/gs; } keys(%params);

	return $tpl;
}

1;

