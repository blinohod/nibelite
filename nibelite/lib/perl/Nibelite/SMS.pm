
=head1 NAME

Nibelite::SMS - routines for SMS data processing

=head1 SYNOPSIS

	use Nibelite::SMS;

	# Prepare 400 characters string
	my $long_line = "zuka"x100;

	# Split string to SMS parts
	my @sms = string_to_pdu($long_line, COD_7BIT);


=head1 DESCRIPTION

C<NetSDS> module contains superclass all other classes should be inherited from.

=cut

package Nibelite::SMS;

use 5.8.0;
use strict;
use warnings;

use version; our $VERSION = '1.000';

use base 'Exporter';

=head1 EXPORTED CONSTANTS

B<COD_7BIT>

B<COD_8BIT>

B<COD_UNICODE>

=cut

our @EXPORT = qw(

  COD_7BIT
  COD_8BIT
  COD_UNICODE

  string_to_pdu
  detect_coding

);

use constant COD_7BIT    => '0';    # GSM 03.38 default charset
use constant COD_8BIT    => '1';    # Binary encoded message
use constant COD_UNICODE => '2';    # UCS-2BE Unicode message

use POSIX;
use NetSDS::Const;
use NetSDS::Util::Convert;
use NetSDS::Util::String;

#***********************************************************************

=head1 EXPORTED FUNCTIONS

=over

=item B<string_to_pdu($str, $coding)> - transform UTF-8 string to PDU array

Paramters:
	
	* $str - original text string
	* $coding - SMS coding (0 for default 7bit, 2 for UCS-2BE)

Returns: array of hash references containing SMS data.

PDU structure is the following:

	{
		coding => $sms_coding, # SMS encoding (0 or 2)
		udh    => $udh,        # UDH as byte string
		ud     => $user_data,  # User Data as byte string in GSM03.38 or UCS-2BE encoding
		text   => $text_part,  # User Data as UTF-8 string (internal Perl Unicode)
	}

Example:

	my @pdus = string_to_pdu($long_string, $sms_coding);


=cut 

#-----------------------------------------------------------------------

sub string_to_pdu {

	my ( $text, $coding ) = @_;

	my @ret = ();    # Return data

	my $udh = "\x05\x00\x03";

	my $sms_size  = 160;
	my $part_size = 153;
	my $charset   = 'GSM0338';

	# Prepare message body for processing.
	# Convert to byte string and define concatenation constants.

	unless ( ( $coding == COD_7BIT ) or ( $coding == COD_UNICODE ) ) {
		return undef;    # Wrong encoding
	}

	if ( $coding == COD_UNICODE ) {
		$charset   = 'UTF-16BE';
		$sms_size  = 140;
		$part_size = 134;
	}

	$text = str_decode( str_encode($text), $charset );

	# Check if message may be passed as single SMS
	if ( bytes::length($text) <= $sms_size ) {

		push @ret,
		  {
			udh    => undef,
			coding => $coding,
			ud     => $text,
			text   => str_encode( $text, $charset ),
		  };

	} else {

		my $refnum = int( rand(256) );                             # generate random chain reference
		my $qty    = ceil( bytes::length($text) / $part_size );    # determine result number of PDUs
		$udh .= pack( 'C*', $refnum, $qty );                       # add chain reference and chain size to UDH

		for ( my $i = 1 ; $i <= $qty ; $i++ ) {

			# Prepare user data part
			my $ud = bytes::substr( $text, $part_size * ( $i - 1 ), $part_size );

			push @ret,
			  {
				udh    => $udh . pack( 'C', $i ),
				coding => $coding,
				ud     => $ud,
				text   => str_encode( $ud,  $charset ),
			  };

		}

	} ## end else [ if ( bytes::length($text...))]

	return @ret;

} ## end sub string_to_pdu

#**************************************************************************

=item B<detect_coding($utf8_string)> - detect text SMS encoding

Example:

	my $text = "String is here";
	my $coding = detect_coding($text);

=cut 

sub detect_coding {

	my ($text) = @_;

	# Encode to Unicode string (not bytes)
	$text = str_encode($text);

	# If text can't be recoded to GSM 03.38 then use UCS-2BE charset
	my $gsm0338 = undef;

	eval { $gsm0338 = str_recode( $text, "utf-8", "gsm0338" ); };

	if ( $@ and !$gsm0338 ) {
		return COD_UNICODE;    # UCS-2BE (16 bit)
	} else {
		return COD_7BIT;       # GSM 03.38 (7bit)
	}

}

#**************************************************************************
1;

__END__

=back

=head1 EXAMPLES

None

=head1 BUGS

None

=head1 SEE ALSO

None

=head1 TODO

None

=head1 AUTHOR

Michael Bochkaryov <misha@rattler.kiev.ua>

=head1 LICENSE

Copyright (C) 2008-2011 Net Style Ltd.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

=cut

