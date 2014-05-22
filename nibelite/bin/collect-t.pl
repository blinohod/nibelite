#!/usr/bin/perl


use strict;

my $res = {};

while(<>) {
    while (m#\<t\>([a-z0-9_/-]+)\</t\>#i || m#\bt\(['"]([a-z0-9_/-]+)['"]\)#i) {
	my $x = $1;
	$res->{$x} = 1 if $x;
        s/$x//;
    }
}

print join ("\n", sort (keys(%$res))) .(scalar(keys(%$res)) ? "\n" : "");

1;
