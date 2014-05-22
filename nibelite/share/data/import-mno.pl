#!/usr/bin/env perl

use 5.8.0;
use warnings;
use strict;

use Config::General qw(ParseConfig);

use Data::Dumper;
use NetSDS::DBI;

my $db = NetSDS::DBI->new(
	dsn => 'dbi:Pg:dbname=nibelite',
	user => 'nibelite',
	passwd => 'nibelite',
);

my %cf = ParseConfig("./mno.conf");

#print Dumper(\%cf);

$db->begin();

foreach my $country (keys $cf{country}) {

	my $country_code = $cf{country}->{$country}->{code};

	print "$country : $country_code\n";

	foreach my $mno (keys %{$cf{country}->{$country}->{mno}	}) {

		my $mno_title = $cf{country}->{$country}->{mno}->{$mno}->{title};
		my @mno_codes = split (',', $cf{country}->{$country}->{mno}->{$mno}->{codes});

		print "\tMNO: $mno\n";
		print "\tTitle: $mno_title\n";
		print "\tCodes: " . join (':', @mno_codes) . "\n";

		my $sql = 'INSERT INTO core.mno("name", country_code, title) VALUES (?, ?, ?) RETURNING id';
		print "SQL: $sql, $mno, $country, $mno_title\n";
		my ($row) = @{$db->fetch_call($sql, $mno, $country, $mno_title)};
		my $mno_id = $row->{id};

		foreach my $mno_code (@mno_codes) {
			my $sql = 'INSERT INTO core.mno_prefixes (mno_id, prefix) VALUES (?, ?)';
			print "\tSQL: $sql, $mno_id, ${country_code}${mno_code}\n";
			$db->call($sql,  $mno_id, $country_code . $mno_code);
		}
	}

}

$db->commit();
