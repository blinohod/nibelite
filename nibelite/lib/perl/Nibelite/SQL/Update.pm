package Nibelite::SQL::Update;

=head1 NAME

NetSDS::Portal::Grid::Update - the UPDATE statement structuring and dynamic customization

=head1 SYNOPSIS

  use Nibelite::SQL;
  
  my $grid = Nibelite::SQL->update(
  	'tablespec',
  	from => {
  		mytable => 'some.other.table',
  	},
  	fields => {
  		field => $value1,
  		otherfield => 'Other Value',
  		pass_field => 'aD4rkSecr3t'
  		#...
  	},
  	field_map => {
  		computed_field => 'NOW()',
  		pass_field => 'MD5(:pass_field)'
  	}
  )->where("name LIKE '%foo%'");
  my $results = $grid->perform($dbh); # $dbh is a NetSDS::DBI;
  
=head1 DESCRIPTION

An often-arising task in a web portal is a CRUD implementation. There are lots of them,
one more automated than others. We are inventing this wheel over and over not because all
of them are so bad, which most of them are, but because it would fit more organically
in the whole underlying infrastructure most of which bears NIH syndrome consequences anyway. :-)

This class deals with the problem of dynamically constructing an UPDATE statement, making it
possible to add any part of the statement at runtime, in any order, without messing with
string manipulation. 

=head1 REFERENCE

=cut

use strict;
use warnings;
use version; our $VERSION = '0.1';

use base 'NetSDS::Class::Abstract';
use Exception::Class ( 'ProgrammingError', 'TypeError' );

#****************************************************************************

=head2 B<new> - a class constructor

=head3 Synopsis

	my $update = NetSDS::Portal::Grid->update(
		'tablespec',
		fields => {
			name => $value,
			#...
		},
		...
	);

=head3 Description

Constructs the statement. The constructor is passed a hash of parameters which are the base of the
statement. Once a part of statement is added, it cannot be removed. The only part of the statement
which is obligatory is the tablespec of the main table to update.

The parameters in the hash can be: set, only, where, from, returning, params, field_map. See 
the respective methods description to find out what they are for.

=cut

#---------------------------8<-----------------------------------------------

sub new {
	my ( $class, $tablespec, %options ) = @_;
	my $this = $class->SUPER::new();
	bless $this, $class;
	$this->mk_accessors (qw(tablespec));
	$this->tablespec($tablespec);
	$this->{_fields}     = {};
	$this->{_field_map}  = {};
	$this->{_from}       = {};
	$this->{_where}      = [];
	$this->{_returning}  = [];
	$this->{_only}       = 0;
	$this->{_params}     = {};
	$this->{_params_map} = {};
	$this->{_max_param}  = 0;
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
There's one caveat to it. An UPDATE statement is only valid when there is at least one field
assignment in the SET clause.

	my $statement = NetSDS::Portal::Grid->update('sometable');
	print $statement->to_string(); # ProgrammingError is raised
	
	my $statement = NetSDS::Portal::Grid->update('sometable', set => { name = 'Foo' });
	print $statement->to_string(); # UPDATE sometable SET name = :name
	
Use to_string often during development, it would save your headaches later.

=cut

#---------------------------8<-----------------------------------------------
sub to_string {
	my ($this) = @_;
	my $buffer = 'UPDATE ';

	$buffer .= 'ONLY ' if $this->{_only};
	$buffer .= $this->tablespec();

	my @set = ();
	foreach my $fld ( keys %{ $this->{_fields} } ) {
		my $fmap_entry = ( defined( $this->{_field_map}->{$fld} ) ) ? $this->{_field_map}->{$fld} : ":$fld";
		push @set, "$fld = $fmap_entry";
		$this->params( { $fld => $this->{_fields}->{$fld} } );
	}

	$buffer .= " SET " . join( ", ", @set );
	my @k = keys( %{ $this->{_from} } );
	if ( scalar(@k) ) {
		my @from = ();
		foreach my $alias ( keys( %{ $this->{_from} } ) ) {
			if ( $alias ne $this->{_from}->{$alias} ) {
				push @from, $this->{_from}->{$alias} . " AS $alias";
			} else {
				push @from, $this->{_from}->{$alias};
			}
		}
		$buffer .= " FROM " . join( ", ", @from );
	}

	if ( scalar( @{ $this->{_where} } ) ) {
		$buffer .= " WHERE " . join( " AND ", map { "($_)"; } @{ $this->{_where} } );
	}

	if ( ( ref( $this->{_returning} ) eq 'ARRAY' ) && scalar( @{$this->{_returning}} ) ) {
		$buffer .= " RETURNING " . join( ", ", @{ $this->{_returning} } );
	}

	return $buffer;
} ## end sub to_string

sub fields {
	my ( $this, $fields ) = @_;
	TypeError->throw( error => 'TypeError: fields must be a hash reference' ) if ref($fields) ne 'HASH';
	foreach my $f ( keys %{$fields} ) {
		$this->{_fields}->{$f} = $fields->{$f};
	}
	return $this;
}

sub field_map {
	my ( $this, $field_map ) = @_;
	TypeError->throw( error => 'TypeError: field_map must be a hash reference' ) if ref($field_map) ne 'HASH';
	foreach my $f ( keys %{$field_map} ) {
		$this->{_field_map}->{$f} = $field_map->{$f};
	}
	return $this;
}

sub where {
	my ( $this, $where ) = @_;
	TypeError->throw( error => 'TypeError: where must be an array reference' ) if ref($where) ne 'ARRAY';
	foreach my $f ( @{$where} ) {
		push @{ $this->{_where} }, $f;
	}
	return $this;
}

sub returning {
	my ( $this, $ret ) = @_;
	TypeError->throw( error => 'TypeError: returning must be an array reference' ) if ref($ret) ne 'ARRAY';
	foreach my $f ( @{$ret} ) {
		push @{ $this->{_returning} }, $f;
	}
	return $this;
}

sub params {
	my ( $this, $fields ) = @_;
	TypeError->throw( error => 'TypeError: params must be a hash reference' ) if ref($fields) ne 'HASH';
	foreach my $f ( keys %{$fields} ) {
		if ( !defined( $this->{_params_map}->{$f} ) ) {
			$this->{_max_param}++;
			$this->{_params_map}->{$f} = $this->{_max_param};
		}
		$this->{_params}->{ $this->{_params_map}->{$f} } = $fields->{$f};
	}
	return $this;
}

sub from {
	my ( $this, $fields ) = @_;
	TypeError->throw( error => 'TypeError: from must be a hash reference' ) if ref($fields) ne 'HASH';
	foreach my $f ( keys %{$fields} ) {
		$this->{_from}->{$f} = $fields->{$f};
	}
	return $this;
}

sub perform {
	my ( $this, $dbh ) = @_;
	TypeError->throw( error => 'TypeError: perform must be passed a NetSDS::DBI reference' ) unless $dbh->isa('NetSDS::DBI');
	my $query = $this->replace_params( $this->to_string() );
	my $sth = $dbh->dbh->prepare($query);
	foreach my $key ( keys( %{ $this->{_params} } ) ) {
		$sth->bind_param( "\$$key", $this->{_params}->{$key} );
	}
	$sth->execute();
	return $sth;
}

sub replace_params {
	my ( $this, $q ) = @_;
	while ( $q =~ /(?!<:)(:[A-Za-z0-9_]+)/ ) {
		my $fld   = $1;
		my $param = $fld;
		$param =~ s/^://;
		my $replacement = $this->{_params_map}->{$param};
		$q =~ s/(?!<:)$fld/\$$replacement/g;
	}
	return $q;
}

1;
