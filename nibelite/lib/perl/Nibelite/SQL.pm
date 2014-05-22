package Nibelite::SQL;

=head1 NAME

Nibelite::SQL - the SQL statements structuring and dynamic customization

=head1 SYNOPSIS

  use Nibelite::SQL;
  
  my $grid = Nibelite::SQL->select(
  	tables => {
  		mytable => 'some.other.table',
  	},
  	fields => {
  		id => 'mytable.id',
  		name => 'mytable.name',
  		#...
  	},
    joins => [
    	'LEFT JOIN yet.another.table AS histable ON (condition)',
    ]
  )->where("name LIKE '%foo%'");
  my $results = $grid->find($dbh); # $dbh is a NetSDS::DBI;


  my $update = Nibelite::SQL->update(
  	'main_table',
  	only => 0,
  	fields => {
  		name => 'New name',
  		description => 'New description',
  	},
  	from => {
  		othertablealias => 'tablespec',
  	}
  )


=head1 DESCRIPTION

An often-arising task in a web portal is a CRUD implementation. There are lots of them,
one more automated than others. We are inventing this wheel over and over not because all
of them are so bad, which most of them are, but because it would fit more organically
in the whole underlying infrastructure most of which bears NIH syndrome consequences anyway. :-)

This class deals particularly with the task of flexibly building a SQL statement, binding it
to GET/POST parameters, defining custom handlers for some operations and then feeding the statement
to a real database to process.

=head1 REFERENCE

=cut 

use strict;
use warnings;
use version; our $VERSION = '0.2';

use base 'NetSDS::Class::Abstract';
use Nibelite::SQL::Select;
use Nibelite::SQL::Update;

#****************************************************************************

=head2 B<select> - a class constructor

=head3 Synopsis

	my $select = Nibelite::SQL->select(
		tables => {
			alias => 'table.specification',
		},
		fields => {
			alias1 => 'SQL field specification or expression',
		},
		...
	);

=head3 Description

Constructs a SQL SELECT statement. The constructor is passed a hash of parameters 
which are the base of the statement. Once a part of statement is added, it cannot 
be removed. No part of statement is obligatory.

The parameters in the hash can be: fields, tables, where, joins, having, group, 
order, params, limit, offset, bind. See the respective methods description 
to find out what they are for.

The idea behind this approach is that every SELECT has an immutable core elements 
and conditional ones. You pass the core elements, which are never changed, 
to the constructor, and the conditional parts you resolve (or do not resolve) 
later on.

=cut

#---------------------------8<-----------------------------------------------
sub select {
	my ( $class, %options ) = @_;
	return Nibelite::SQL::Select->new(%options);
} ## end sub select


sub update {
	my ( $class, $tablespec, %options ) = @_;
	return Nibelite::SQL::Update->new($tablespec, %options);
} ## end sub select


1;
