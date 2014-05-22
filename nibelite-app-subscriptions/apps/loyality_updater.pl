#!/usr/bin/env perl 

use 5.8.0;
use strict;
use warnings;

NetSDSApp->run(
	daemon    => 0,
	infinite  => 0,
	has_conf  => 1,
	conf_file => './smsnews.conf',
);

1;

=head1 NAME

loyality_updater.pl - Subscribers' usage_days updater

=cut

package NetSDSApp;

use warnings;
use strict;
use lib '/opt/nibelite/lib/perl';
use base 'Nibelite::App::Service';

sub process {

	my ($this) = @_;

	$this->{to_finalize} = 1;

	my $subs = $this->update_subscribers();
	foreach my $sub (@$subs) {
		
		$this->perform_loyality_dances($sub->{id},$sub->{msisdn},$sub->{usage_days});

	}

	return 1;

}

=item perform_loyality_dances

	Make good things about loyal subscriber
		
=cut

sub perform_loyality_dances {
	
	my ($this, $id, $msisdn, $usage_days) = @_;
	
	#TODO: what?

	return 1;

}

=item update_subscribers

	Increments usage_days of loyal subscribers and returns a list of
	their {id, msisdn, incremented usage_days}

=cut

sub update_subscribers {

	my ($this) = @_;

	my $sql = 
		"update smsnews.subscribers".
		" set usage_days = usage_days + 1".
		" where id in (".
			"select distinct bers.id".
			" from ".
				"smsnews.active_subscriptions as tions".
				" left outer join smsnews.active_subscribers as bers".
				" on bers.id=tions.subscriber_id".
			" where".
				" bers.msisdn is not null".
				" and now() between tions.started and tions.stopped".
				" and bers.test_until < now()".
		")".
		" returning id,msisdn,usage_days";

	my $subs = $this->dbh->fetch_call($sql);

	return $subs;

}

1;

