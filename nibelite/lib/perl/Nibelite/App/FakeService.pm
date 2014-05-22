package Nibelite::App::FakeService;

use 5.8.0;
use strict;
use warnings;

use base 'Nibelite::App::Service';

sub initialize {
	my ($this, %params) = @_;
	$this->SUPER::initialize(%params);
	$this->mq(FakeMQ->new);
	$this->{_mcounter} = 1;
	$this->{to_finalize} = 0;
}

sub fetch_messages {
	my ($this) = @_;
	return $this->mq->pull();
}

1;


package FakeMQ;

use base 'NetSDS::Class::Abstract';

sub new {
	my ($class, %options) = @_;
	my $this = $class->SUPER::new(%options);
	$this->{_mcounter} = 1;
	$this->{_queue} = {};
	return $this;
}

sub enqueue_message {
	my ( $this, %msg ) = @_;
	my %m = %msg;
	$m{id} = $this->{_mcounter};
	$this->{_mqueue}->{$m{id}} = \%m;
	$this->{_mcounter}++;
	my $res = $this->{_mqueue}->{$m{id}};
	printf STDOUT ("ENQUEUE: %s => %s: (%s) %s\n", $res->{src_addr}, $res->{dst_addr}, $res->{msg_status}, $res->{msg_body} );
}

sub update_message {
	my ( $this, $msg_id, %msg ) = @_;
	unless ($this->{_mqueue}->{$msg_id}) {
		print STDERR "NOT FOUND: $msg_id\n";
	} else {
		foreach my $key (keys %msg) {
			$this->{_mqueue}->{$msg_id}->{$key} = $msg{$key};
		}
	}
	my $m = $this->{_mqueue}->{$msg_id};
	printf STDOUT ("UPDATE[%s]: %s => %s: (%s) %s\n", $msg_id, $m->{src_addr}, $m->{dst_addr}, $m->{msg_status}, $m->{msg_body} );
}

sub pull {
	my ( $this ) = @_;
	my $message = <STDIN>;
	$_ = $message;
	$message =~ m/^\s*(\d+)\s+(\d+)\s+(.*?)$/;
	my $result = {
		id => $this->{_mcounter},
		msg_type => 'SMS_TEXT',
		src_addr => $1,
		dst_addr => $2,
		msg_body => $3,
		msg_status => 'ROUTED'
	};
	$this->{_mqueue}->{$result->{id}} = $result;
	$this->{_mcounter}++;
	return ($result);
}

sub make_reply {
	my ( $this, $msg, %params ) = @_;

	my %rep = (
		refer_id   => $msg->{id},
		src_app_id => $msg->{dst_app_id},
		dst_app_id => $msg->{src_app_id},
		src_addr   => $msg->{dst_addr},
		dst_addr   => $msg->{src_addr},
		msg_body   => '',
		msg_status => 'ROUTED',
		%params,
	);

	return \%rep;
}

1;
