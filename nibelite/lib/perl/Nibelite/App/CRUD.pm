package Nibelite::App::CRUD;

=head1 NAME

Nibelite::App::CRUD - a base package for easy CRUD apps creation

=head1 SYNOPSIS

	Nibelite::App::CRUD->run (
		table => 'core.apps',
		forbid_operations => ['delete'], # list, update, create
		
		primary => 'id', # this is the default anyway
		# primary => ['user_id', 'group_id'] for composite primary keys
		
		#
		# The table can well have a lot more fields, but we
		# select only these for listing
		#
		
		selectable_fields => [ 'id', 'name', 'descr', 'active' ],
		
		#
		# Same goes for fields we can update, others will be ignored
		#
		
		updatable_fields => [
			'name', 'descr', 'active'
		],
		
		#
		# Same goes for fields we can sort by
		#
		
		sortable_fields => [
			'id', 'name', 'active'
		],
		
		#
		# These are fields to be returned after a successful insert/update.
		#
		
		returning_fields => [
			'id', 'name', 'active'
		]
		
		#
		# This is what we use when "sort by FOO ascending" really
		# means "sort also by BAR descending and BAZ ascending"
		#
		
		sort_rules => {
			'name:asc' => [['name', 'ASC'], ['active', 'DESC']],
			'name:desc' => [['name', 'DESC'], ['active', 'DESC']]
		},
		
		#
		# Generally, in foo=bar&baz=dribble query strings for list method,
		# foo and baz are not field names but filter names. By default,
		# filters are equality tests for corresponding field names, but
		# we can easily redefine them to perform fancier operations.
		#
		
		filters => {
			name => sub {
				my ($this, $value, $statement) = @_;
				$value =~ s/([%_])/\\$1/g;
				my $resv = "%$value%";
				$statement->where(['name LIKE :name_like']);
				$statement->params('name_like' => $resv);
				return $statement;
			}
		}
	);
	
=head1 DESCRIPTION

An often-tedious task in Nibelite development is CRUD applications. The repeatable tasks are
SQL statements construction, getting results in consistent manner. Moreover, this task is very
bug-prone, and there are numerous things to take care of:

=over

=item *

When updating records, it is important to only update fields which actually changed, not all of them

=item *

When selecting records page by page, it must be possible to get the number of records in total, in
an efficient manner.

=item *

When filtering records by user input, security precautions must be taken. The SQL statement is often
created dynamically, to ensure only needed conditions are present, so this is a particularly vulnerability-prone
process.

=back

These are only several things hard to deal with which this class tries to prevent.

=cut

use strict;
use warnings;
use 5.8.0;

use base 'Nibelite::App::GUI';
use NetSDS::Exceptions;
use Exception::Class qw(ArgumentError);
use Nibelite::SQL;

sub new {
	my ( $class, %params ) = @_;
	my $this = $class->SUPER::new(%params);
	$this->{_filters} = {};
	$this->mk_accessors(qw( primary table forbid_operations selectable_fields selectable_sql updatable_fields primary sort_rules sortable_fields returning_fields forbidden_operations create_fields_map update_fields_map ));
	return $this;
}

sub initialize {
	my ( $this, %params ) = @_;
	$this->SUPER::initialize(%params);
	$this->table( $params{table} or ArgumentError->throw( error => 'Table must be specified for CRUD' ) );
	$this->forbid_operations( {} );
	if ( $params{forbid_operations} ) {
		foreach my $op ( @{ $params{forbid_operations} } ) {
			$this->forbid_operations->{$op} = 1;
		}
	}
	if ( !$params{updatable_fields} && !( $this->forbid_operations->{create} && $this->forbid_operations->{update} ) ) {
		ArgumentError->throw( error => 'Updatable fields must be specified if create or update is allowed.' );
	}
	if ( !$params{selectable_fields} && !( $this->forbid_operations->{list} ) ) {
		ArgumentError->throw( error => 'Selectable fields must be specified if list is allowed.' );
	}
	$this->primary( $params{primary}                     or 'id' );
	$this->selectable_fields( $params{selectable_fields} or [] );
	$this->selectable_sql( $params{selectable_sql}       or {} );
	$this->updatable_fields( $params{updatable_fields}   or [] );
	$this->returning_fields( $params{returning_fields}   or [] );
	$this->sort_rules( $params{sort_rules}               or {} );
	$this->filters( $params{filters}                     or {} );
	$this->create_fields_map( $params{create_fields_map} or {} );
	$this->update_fields_map( $params{update_fields_map} or {} );
	$this->sortable_fields( {} );

	if ( $params{sortable_fields} ) {

		foreach my $field ( @{ $params{sortable_fields} } ) {
			$this->sortable_fields->{$field} = 1;
		}
	}
} ## end sub initialize

sub filters {
	my ( $this, $filters ) = @_;
	my $class_name = ref($this);
	no strict 'refs';
	foreach my $filter ( keys %$filters ) {
		my $fname = $filter;
		$fname =~ s/[^\w]/_/g;
		*{"${class_name}::filter_$fname"} = $filters->{$filter};
		$this->{_filters}->{$filter} = "filter_$fname";
	}
	use strict 'refs';
}

sub primary_present {
	my ( $this, $params ) = @_;
	my $present = 1;
	if ( ref( $this->primary ) eq 'ARRAY' ) {
		foreach my $f ( @{ $this->primary } ) {
			unless ( defined( $params->{$f} ) ) {
				$present = 0;
			}
		}
	} else {
		$present = defined( $params->{ $this->primary } );
	}
	return $present;
}

sub list_base_query {
	my ($this) = @_;
	my $fields = {};
	if ( scalar( keys( %{ $this->selectable_sql() } ) ) ) {
		$fields = $this->selectable_sql();
	} else {
		foreach my $f ( @{ $this->selectable_fields() } ) {
			$fields->{$f} = $f;
		}
	}
	my $query = Nibelite::SQL->select(
		tables => {
			$this->table => $this->table,
		},
		fields           => $fields,
		use_transactions => 1
	);
	return $query;
}

sub action_list {
	my ( $this, $data, %params ) = @_;
	if ( $this->forbid_operations->{list} ) {
		return {}, { -http => '403 Forbidden', message => 'Operation "list" is forbidden' };
	}
	my $query = $this->list_base_query();
	#
	# Sorting
	#
	if ( defined( $params{iSortCol_0} ) && ( $params{iSortCol_0} ne '' ) ) {
		my $ordfield = $this->selectable_fields()->[ $params{iSortCol_0} ];
		my $orddir = $params{sSortDir_0} || 'asc';
		if ( $this->sort_rules->{"$ordfield:$orddir"} ) {
			$query->order( $this->sort_rules->{"$ordfield:$orddir"} );
		} else {
			$query->order( [ [ $ordfield, $orddir ] ] );
		}
	}
	#
	# Paging
	#
	if ( defined( $params{iDisplayStart} ) && ( $params{iDisplayStart} ne '' ) && ( $params{iDisplayLength} != -1 ) ) {
		$query->limit( $params{iDisplayLength} );
		$query->offset( $params{iDisplayStart} );
	}
	#
	# Filtering
	#
	foreach my $filtername ( keys %params ) {
		if ( $this->{_filters}->{$filtername} ) {
			my $method = $this->{_filters}->{$filtername};
			$method =~ s/[^\w]/_/g;
			$this->$method( $params{$filtername}, $query );
		}
	}
	my $rows    = $query->find( $this->dbh() );
	my $results = [];
	my $total   = $query->{_total_rows};
	while ( my $row = $rows->fetchrow_hashref() ) {
		my $row_array = [];
		foreach my $key ( @{ $this->selectable_fields } ) {
			push @$row_array, $row->{$key};
		}
		push @$results, $row_array;
	}
	my $echo = $params{sEcho};
	return {
		sEcho                => $echo,
		iTotalRecords        => int($total),
		iTotalDisplayRecords => int($total),
		aaData               => $results
	};
} ## end sub action_list

sub get_default_values {
	my ($this) = @_;
	return {};
}

sub action_create {
	my ( $this, $data, %params ) = @_;
	if ( $this->forbid_operations->{create} ) {
		return {}, { -http => '403 Forbidden', status => 'error', message => 'Operation "create" is forbidden' };
	}
	my @field_names;
	my @values;
	my @placeholders;
	my $defaults = $this->get_default_values();
	%params = ( %$defaults, %params );
	foreach my $field ( @{ $this->updatable_fields } ) {
		if ( defined( $params{$field} ) ) {
			push @field_names, $field;
			push @values,      $params{$field};
			push @placeholders, ( $this->create_fields_map->{$field} or '?' );
		}
	}
	my $ret = $this->selectable_fields;
	if ( defined( $this->returning_fields ) && scalar( @{ $this->returning_fields } ) ) {
		$ret = $this->returning_fields;
	}
	my $q = sprintf(
		"INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
		$this->table,
		join( ', ', @field_names ),
		join( ', ', @placeholders ),
		join( ', ', @{$ret} )
	);
	my $result;
	eval { $result = $this->dbh->fetch_call( $q, @values ); };
	if ( $this->dbh->dbh->err ) {
		my $err = $this->dbh->dbh->errstr;
		$err =~ s/^ERROR:\s+//i;
		return {}, { status => 'error', message => $err };
	}
	my $row_array = [];
	foreach my $field ( @{$ret} ) {
		push @$row_array, $result->[0]->{$field};
	}
	return { aData => $row_array }, {};
} ## end sub action_create

sub update_base_query {
	my ($this) = @_;
	return Nibelite::SQL->update( $this->table(), field_map => $this->update_fields_map );
}

=head2 update_add_primary ($query, $params)

Add primary key component (where primary = ...) to update query. Works for both singular and
composite keys.

=cut

sub update_add_primary {
	my ( $this, $query, $params ) = @_;
	if ( ref( $this->primary ) eq 'ARRAY' ) {
		foreach my $field ( @{ $this->primary } ) {
			$query->where( ["$field = :$field"] );
			$query->params( { $field => $params->{$field} } );
		}
	} else {
		$query->where( [ $this->primary . " = :" . $this->primary ] );
		$query->params( { $this->primary => $params->{ $this->primary } } );
	}
	return $query;
}

sub action_update {
	my ( $this, $data, %params ) = @_;
	if ( $this->forbid_operations->{update} ) {
		return {}, { -http => '403 Forbidden', status => 'error', message => 'Operation "update" is forbidden' };
	}
	unless ( $this->primary_present( \%params ) ) {
		return {}, { status => 'error', message => 'Cannot update: primary key is unspecified, or not fully specified.' };
	}
	my $query = $this->update_base_query();
	my $i     = 0;
	foreach my $field ( @{ $this->updatable_fields } ) {
		if ( defined( $params{$field} ) ) {
			$query->fields( { $field => $params{$field} } );
			$i++;
		}
	}
	if ( !$i ) {
		return {}, { status => 'error', message => 'Cannot update: nothing to update.' };
	}
	$this->update_add_primary( $query, \%params );
	my $ret = $this->selectable_fields;
	if ( defined( $this->returning_fields ) && scalar( @{ $this->returning_fields } ) ) {
		$ret = $this->returning_fields;
	}
	$query->returning($ret);
	my $sth;
	eval { $sth = $query->perform( $this->dbh ); };
	if ( $sth->err ) {
		my $err = $this->dbh->dbh->errstr;
		$err =~ s/^ERROR:\s+//i;
		return {}, { status => 'error', message => $err };
	}
	my $row       = $sth->fetchrow_hashref();
	my $row_array = [];
	foreach my $key ( @{$ret} ) {
		push @$row_array, $row->{$key};
	}
	return { aData => $row_array };
} ## end sub action_update

sub action_delete {
	my ( $this, $data, %params ) = @_;
	if ( $this->forbid_operations->{"delete"} ) {
		return {}, { -http => '403 Forbidden', message => 'Operation "delete" is forbidden' };
	}
	unless ( $this->primary_present( \%params ) ) {
		return {}, { status => 'error', message => 'Cannot delete: primary key is unspecified, or not fully specified.' };
	}
	my @bind;
	my @where;
	if ( ref( $this->primary ) eq 'ARRAY' ) {
		foreach my $field ( @{ $this->primary } ) {
			push @where, "($field = ?)";
			push @bind,  $params{$field};
		}
	} else {
		push @where, "(" . $this->primary . " = ?)";
		push @bind,  $params{ $this->primary };
	}
	my $q = "DELETE FROM %s WHERE %s";
	my $r;
	eval { $r = $this->dbh->call( sprintf( $q, $this->table, join( ' AND ', @where ) ), @bind ); };
	if ( $r->err ) {
		my $err = $this->dbh->dbh->errstr;
		$err =~ s/^ERROR:\s+//i;
		return {}, { status => 'error', message => $err };
	}
	return {};
} ## end sub action_delete

1;
