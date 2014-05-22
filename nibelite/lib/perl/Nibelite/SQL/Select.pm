package Nibelite::SQL::Select;

=head1 NAME

Nibelite::SQL::Select - the SELECT statement structuring and dynamic customization

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
    	'LEFT JOIN yet.another.table AS histable ON ()',
    ]
  )->where("name LIKE '%foo%'");
  my $results = $grid->find($dbh); # $dbh is a NetSDS::DBI;
  
=head1 DESCRIPTION

An often-arising task in a web portal is a CRUD implementation. There are lots of them,
one more automated than others. We are inventing this wheel over and over not because all
of them are so bad, which most of them are, but because it would fit more organically
in the whole underlying infrastructure most of which bears NIH syndrome consequences anyway. :-)

This class deals particularly with the task of flexibly building a SELECT statement, binding it
to GET/POST parameters, defining custom handlers for some operations and then feeding the statement
to a real database to process.

=head1 REFERENCE

=cut 

use strict;
use warnings;
use version; our $VERSION = '0.1';

use base 'NetSDS::Class::Abstract';

#****************************************************************************

=head2 B<new> - a class constructor

=head3 Synopsis

	my $select = NetSDS::Portal::Grid->select(
		tables => {
			alias => 'table.specification',
		},
		fields => {
			alias1 => 'SQL field specification or expression',
		},
		...
	);

=head3 Description

Constructs the statement. The constructor is passed a hash of parameters which are the base of the
statement. Once a part of statement is added, it cannot be removed. No part of statement is obligatory.

The parameters in the hash can be: fields, tables, where, joins, having, group, order, params, limit,
offset, bind. See the respective methods description to find out what they are for.

The idea behind this approach is that every SELECT has an immutable core elements and conditional ones.
You pass the core elements, which are never changed, to the constructor, and the conditional parts
you resolve (or do not resolve) later on.

=cut

#---------------------------8<-----------------------------------------------
sub new {
	my ( $class, %options ) = @_;
	my $this = $class->SUPER::new();
	bless $this, $class;
	$this->mk_accessors(qw(use_transactions));
	$this->use_transactions(0);
	$this->{_select}   = {};
	$this->{_tables}   = {};
	$this->{_joins}    = [];
	$this->{_where}    = [];
	$this->{_group}    = {};
	$this->{_having}   = [];
	$this->{_order}    = [];
	$this->{_offset}   = undef;
	$this->{_limit}    = undef;
	$this->{_bindings} = {};
	$this->{_locking}  = { type => "", tables => [], wait => 1 };
	foreach my $option ( keys %options ) {

		if ( $this->can($option) ) {
			$this->$option( $options{$option} );
		}
	}

	return $this;
} ## end sub new

#****************************************************************************

=head2 B<to_string> - format the current statement into a string

=head3 Synopsis

print $select->to_string();

=head3 Description

Converts the current statement to a string. Used internally to pass the statement
to the DBI wrapper. Can be also used for debugging.

Please note two important things. This class, on one hand, does not do any SQL analysis
which is done for you by the database. However, it does attempt to pass a valid SQL every time.
For example, if nothing is passed to the constructor, the result will be a valid SQL anyway.

	my $statement = NetSDS::Portal::Grid->select();
	print $statement->to_string(); # SELECT NULL
	
If you don't specify the tables from which you select your fields, it will slip by and run into
the database returning an error:

	my $statement = NetSDS::Portal::Grid->select( fields => { id => 'mytable.id', name => 'mytable.name' } );
	print $statement->to_string(); # SELECT mytable.id AS id, mytable.name AS name
	
On the other hand, if you specify tables but don't specify the fields, the class will assume 
that you want everything - which may give you errors in case of conflicting names. Unless you are 
absolutely sure your database schema won't change over time, don't do it:

	my $statement = NetSDS::Portal::Grid->select( tables => { apples => 'fruit.apples', oranges => 'fruit.oranges' } );
	print $statement->to_string(); # SELECT * FROM fruit.apples AS apples, fruit.oranges AS oranges
	
Use to_string often during development, it would save your headaches later.

=cut

#---------------------------8<-----------------------------------------------
sub to_string {
	my ($this) = @_;
	my $buffer = '';
	my ( $sel, $from, $joins, $wheres, $havings, $orders, $groupings, $limit, $locking );
	my $default_select = 'NULL';

	if ( scalar( keys( %{ $this->{_tables} } ) ) > 0 ) {
		$default_select = "*";
		$from           = sprintf(
			"FROM %s ",
			join(
				', ',
				map {
					if ( $this->{_tables}->{$_} ne $_ ) { sprintf( "%s AS %s", $this->{_tables}->{$_}, $_ ); }
					else                                { $_; }
				  } keys( %{ $this->{_tables} } )
			)
		);
	}

	if ( scalar( keys( %{ $this->{_fields} } ) ) > 0 ) {
		$sel = sprintf(
			"SELECT %s",
			join(
				', ',
				map {
					if ( $this->{_fields}->{$_} ne $_ ) { sprintf( "%s AS %s", $this->{_fields}->{$_}, $_ ); }
					else                                { $_; }
				  } keys( %{ $this->{_fields} } )
			)
		);
	} else {
		$sel = sprintf( "SELECT %s", $default_select );
	}

	$joins = join " ", @{ $this->{_joins} };

	$wheres = join " AND ", map { sprintf( "(%s)", $_ ) } @{ $this->{_where} };
	$wheres = "WHERE $wheres" if $wheres ne '';

	$havings = join " AND ", map { sprintf( "(%s)", $_ ) } @{ $this->{_having} };
	$havings = "HAVING $havings" if $havings ne '';

	$groupings = join ", ", keys( %{ $this->{_group} } );
	$groupings = "GROUP BY $groupings" if $groupings ne '';

	$orders = join ", ", map { join( " ", @$_ ) } @{ $this->{_order} };
	$orders = "ORDER BY $orders" if $orders ne '';

	$limit = '';
	if ( (!$this->use_transactions()) && ( $this->{_offset} || $this->{_limit} ) ) {
		my $o = "";
		my $l = "";
		$o = "OFFSET " . $this->{_offset} if defined( $this->{_offset} );
		$l = "LIMIT " . $this->{_limit}   if defined( $this->{_limit} );
		$limit = join " ", $o, $l;
	}

	$locking = "";
	if ( $this->{_locking}->{type} ) {
		my $tab = "";
		$tab = " OF TABLES " . join( ", ", $this->{_locking}->{tables} ) if scalar( @{ $this->{_locking}->{tables} } );
		my $w = " NOWAIT" unless $this->{_locking}->{wait};
		$locking = $this->{_locking}->{type} . $tab . $w;
	}

	$buffer = join " ", ( $sel, $from, $joins, $wheres, $groupings, $orders, $havings, $limit, $locking );
	return $buffer;
} ## end sub to_string

#****************************************************************************

=head2 B<fields> - add fields to the SELECT clause

=head3 Synopsis

	$statement->fields({
		'alias' => 'actual.field.name',
		'alias2' => 'SOMEFUNCTION()', # Expressions are OK
		# ...
	}); # SELECT actual.field.name AS alias, SOMEFUNCTION() AS alias2

=head3 Description

Adds elements to the resulting SELECT clause. Please note the method only accepts
a hashref, not a hash.

If no fields() method is called, there are defaults. If there is a FROM clause,
* is selected. If there is no FROM clause, NULL is SELECTED.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------
sub fields {
	my ( $this, $fields ) = @_;
	foreach my $alias ( keys %$fields ) {
		$this->{_fields}->{$alias} = $fields->{$alias};
	}
	return $this;
}

#****************************************************************************

=head2 B<tables> - add tables to the FROM clause

=head3 Synopsis

	$statement->tables({
		'alias' => 'actual.table.name',
		# ...
	}); 

=head3 Description

Adds elements to the resulting FROM clause. Please note the method only accepts
a hashref, not a hash.

See C<joins> method for adding JOIN clauses.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------
sub tables {
	my ( $this, $tables ) = @_;
	foreach my $alias ( keys %$tables ) {
		$this->{_tables}->{$alias} = $tables->{$alias};
	}
	return $this;
}

#****************************************************************************

=head2 B<joins> - add join clauses

=head3 Synopsis

	$statement->joins([
		'JOIN table2 USING (id)',
		# ...
	]); 

=head3 Description

Adds JOIN clauses. Please note the method only accepts an arrayref, not a list.

It does not check that you use unique aliases only, it's up to you. Up to you
is also the correct order of joins. Joins will be formatted in order of
appearance.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------
sub joins {
	my ( $this, $joins ) = @_;
	push @{ $this->{_joins} }, @$joins;
	return $this;
}

#****************************************************************************

=head2 B<where> - add elements to the WHERE clause

=head3 Synopsis

	$statement->where([
		'a = b',
		'b = c'
		# ...
	]);  # WHERE (a = b) AND (b = c)

=head3 Description

Adds elements to the WHERE clause. Please note the method only accepts an arrayref, not a list.

Each element of the WHERE clause is put into parentheses and all of them are ANDed together.
To OR expressions, you must prepare them beforehand. Note that OR expressions are much rarer than
AND, and ORs inserted conditionally are next to nil in practice, so this limitation does not
really harm.

=cut

#---------------------------8<-----------------------------------------------
sub where {
	my ( $this, $where ) = @_;
	push @{ $this->{_where} }, @$where;
	return $this;
}

#****************************************************************************

=head2 B<having> - add elements to the HAVING clause

=head3 Synopsis

	$statement->having([
		'a = b',
		'b = c'
		# ...
	]);  # HAVING (a = b) AND (b = c)

=head3 Description

Adds elements to the HAVING clause. Please note the method only accepts an arrayref, not a list.

Each element of the HAVING clause is put into parentheses and all of them are ANDed together.
To OR expressions, you must prepare them beforehand. Note that OR expressions are much rarer than
AND, and ORs inserted conditionally are next to nil in practice, so this limitation does not
really harm.

=cut

sub having {
	my ( $this, $having ) = @_;
	push @{ $this->{_having} }, @$having;
	return $this;
}

#****************************************************************************

=head2 B<group> - add fields to group by

=head3 Synopsis

	$statement->group([
		'field1',
		'FUNCTION(field3 + 10)'		# Expressions can be used
	]); 

=head3 Description

Adds elements to the resulting GROUP BY clause. Please note that the function
accepts an arrayref, not a list.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------
sub group {
	my ( $this, $group ) = @_;
	foreach my $g (@$group) {
		$this->{_group}->{$g} = 1;
	}
	return $this;
}

#****************************************************************************

=head2 B<params> - binds parameters to the statement

=head3 Synopsis

	$statement->where([
		'id = :foo',
		'expiry < :mydate',
	]);
	$statement->params({
		'foo' => 42,
		'mydate' => '2012-12-12 12:12'
	}); 

=head3 Description

Binds values to placeholders.

You can use named colon-placeholders in your SQL statement and then use this method
to bind actual values to them. Please note that no actual checks are performed on
whether these placeholders are known or any of them are missing in the final statement.

Please note that whereas in SQL fragments you prepend a colon to the parameters,
you don't in params() call.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------

sub params {
	my ( $this, $bindings ) = @_;
	foreach my $binding ( keys %$bindings ) {
		$this->{_bindings}->{":$binding"} = $bindings->{$binding};
	}
	return $this;
}

#****************************************************************************

=head2 B<order> - add fields to order by

=head3 Synopsis

	$statement->order([
		'field1',					# default ordering direction is ASC
		['field2', 'DESC'],			# set direction explicitly
		'FUNCTION(field3 + 10)'		# Expressions can be used
	]); 

=head3 Description

Adds elements to the resulting ORDER BY clause. The elements are passed in an
array reference, not an actual list.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------

sub order {
	my ( $this, $order ) = @_;
	foreach my $o (@$order) {
		if ( ref($o) eq 'ARRAY' ) {
			push @{ $this->{_order} }, $o;
		} else {
			push @{ $this->{_order} }, [ $o, 'ASC' ];
		}
	}
	return $this;
}

#****************************************************************************

=head2 B<limit> - set the limit

=head3 Synopsis

	$statement->limit(50); 

=head3 Description

Sets the limit as in LIMIT clause of a SQL statement. Note that the actual limit
is implemented in a different way, by using a cursor.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------
sub limit {
	my ( $this, $limit ) = @_;
	$this->{_limit} = int($limit);
	return $this;
}

#****************************************************************************

=head2 B<offset> - set the offset

=head3 Synopsis

	$statement->offset(50); 

=head3 Description

Sets the offset as in OFFSET clause of a SQL statement. Note that the actual offset
is implemented in a different way, by using a cursor.

Returns the modified object (self) which is useful for method call chaining.

=cut

#---------------------------8<-----------------------------------------------

sub offset {
	my ( $this, $offset ) = @_;
	$this->{_offset} = int($offset);
	return $this;
}

#****************************************************************************

=head2 B<find> - perform the query

=head3 Synopsis

	my $sth = $statement->find($dbh); # $dbh can be either DBI or NetSDS::DBI

=head3 Description

Actually performs the constructed query.

This is the method where you pass an actual database handle. Returns the DBI result
handle which you can iterate as you see fit.

=cut

#---------------------------8<-----------------------------------------------
sub find {
	my ( $this, $dbh ) = @_;
	# We need to take a real DBI handle out of its wrapper because
	# we use bind_param() and named parameters.
	my $real_dbh;
	if ( $dbh->isa('NetSDS::DBI') ) {
		$real_dbh = $dbh->dbh();
	} else {
		$real_dbh = $dbh;
	}
	my $statement = $this->to_string();
	my $sth;
	if ( $this->use_transactions() ) {
		$real_dbh->begin_work();
		#
		# Cursors like this must be used in a transaction block.
		# First, create the cursor.
		#
		$sth = $real_dbh->prepare("DECLARE sel_c CURSOR FOR $statement");
		foreach my $par ( keys( %{ $this->{_bindings} } ) ) {
			$sth->bind_param( $par, $this->{_bindings}->{$par} );
		}
		my $rv = $sth->execute();
		#
		# Move forward by the offset if applicable
		#
		if ( $this->{_offset} ) {
			$real_dbh->do( sprintf( "MOVE FORWARD %d IN sel_c", $this->{_offset} ) );
		}
		#
		# Either fetch up to the limit or everything
		#
		if ( $this->{_limit} ) {
			$sth = $real_dbh->prepare( sprintf( "FETCH %d FROM sel_c", int( $this->{_limit} ) ) );
		} else {
			$sth = $real_dbh->prepare("FETCH ALL FROM sel_c");
		}
		my $rows_to_select = $sth->execute();
		#
		# Count the remaining rows
		#
		$rv             = $real_dbh->do("MOVE FORWARD ALL IN sel_c");
		$rv             = 0 unless $rv;
		$rows_to_select = 0 unless $rows_to_select;
		$this->{_offset} = 0 unless $this->{_offset};
		$this->{_total_rows} = int( $this->{_offset} ) + $rows_to_select + $rv;

		#
		# Discard everything
		#
		$real_dbh->rollback();
	} else {
		$sth = $real_dbh->prepare("$statement");
		foreach my $par ( keys( %{ $this->{_bindings} } ) ) {
			$sth->bind_param( $par, $this->{_bindings}->{$par} );
		}
		$sth->execute();
	}
	return $sth;
} ## end sub find

sub locktype {
	my ( $this, $locktype ) = @_;
	return $this unless ( uc($locktype) eq 'FOR UPDATE' ) || ( uc($locktype) eq 'FOR SHARE' ) || ( $locktype eq '' );
	$this->{_locking}->{type} = uc($locktype);
	return $this;
}

sub locktables {
	my ( $this, $tables ) = @_;
	return $this unless ref($tables) eq 'ARRAY';
	push @{ $this->{_locking}->{tables} }, @$tables;
	return $this;
}

sub lockwait {
	my ( $this, $wait ) = @_;
	$this->{_locking}->{wait} = $wait ? 1 : 0;
	return $this;
}

#****************************************************************************

=head1 A NOTE ON CODING STYLE

When passing elements to constructor or SQL subclauses methods, do it one on 
a line. This dramatically improves patches.

In other words, this

	select(fields => { f1 => 'v1', f2 => 'v2' }, where => ['condition']);
	
is wrong, and this

	select(
		fields => { 
			f1 => 'v1',
			f2 => 'v2',
		},
		where => [
			'condition'
		],
	);

is right. You want to keep your commits readable.

=cut

#---------------------------8<-----------------------------------------------

1;
