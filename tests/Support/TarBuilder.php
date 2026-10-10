<?php

declare( strict_types=1 );

namespace Tests\Support;

/**
 * Builds tar.gz archives byte-for-byte so tests can craft hostile entries
 * (traversal paths, symlinks, corrupt headers) that archiving tools refuse
 * to produce.
 */
final class TarBuilder
{
    /**
     * Builds a gzip-compressed tar from regular files.
     *
     * @param  array<string, string>  $files  Contents keyed by path.
     */
    public static function fromFiles( array $files ): string
    {
        $entries = [];

        foreach ( $files as $path => $contents ) {
            $entries[] = self::entry( $path, $contents );
        }

        return self::gzip( $entries );
    }

    /**
     * Compresses raw tar entries into a tar.gz.
     *
     * @param  array<int, string>  $entries  Raw entries from entry().
     */
    public static function gzip( array $entries ): string
    {
        return (string) gzencode( self::tar( $entries ) );
    }

    /**
     * Concatenates raw entries and the end-of-archive marker.
     *
     * @param  array<int, string>  $entries  Raw entries from entry().
     */
    public static function tar( array $entries ): string
    {
        return implode( '', $entries ) . str_repeat( "\0", 1024 );
    }

    /**
     * Builds one raw ustar entry (header plus padded data).
     */
    public static function entry( string $name, string $data = '', string $type = '0', string $linkName = '' ): string
    {
        $header = str_pad( substr( $name, 0, 100 ), 100, "\0" )
            . str_pad( '0000644', 8, "\0" )
            . str_pad( '0000000', 8, "\0" )
            . str_pad( '0000000', 8, "\0" )
            . str_pad( sprintf( '%011o', strlen( $data ) ), 12, "\0" )
            . str_pad( sprintf( '%011o', 0 ), 12, "\0" )
            . str_repeat( ' ', 8 )
            . $type
            . str_pad( $linkName, 100, "\0" )
            . "ustar\0" . '00'
            . str_repeat( "\0", 32 + 32 + 8 + 8 + 155 + 12 );

        $checksum = array_sum( unpack( 'C*', $header ) );
        $header   = substr_replace( $header, sprintf( '%06o', $checksum ) . "\0 ", 148, 8 );

        return $header . $data . str_repeat( "\0", ( 512 - ( strlen( $data ) % 512 ) ) % 512 );
    }

    /**
     * Builds a pax extended header that sets the next entry's path.
     */
    public static function paxPath( string $path ): string
    {
        $record = ' path=' . $path . "\n";
        $length = strlen( $record );

        while ( strlen( $record ) + strlen( (string) $length ) !== $length ) {
            $length = strlen( $record ) + strlen( (string) $length );
        }

        return self::entry( 'PaxHeader', $length . $record, 'x' );
    }
}
