<?php

/**
 * Bounded tar.gz reader.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;

/**
 * Reads regular files out of a gzip-compressed tar archive in memory.
 *
 * Nothing is extracted to disk here. Symlinks, hard links, devices, and
 * directories are ignored rather than materialized, the decompressed size
 * and entry count are capped, and every header checksum is verified. Callers
 * decide which of the returned paths to trust.
 *
 * Supports ustar, GNU long names (`L`), and pax `path` records.
 *
 * @since 1.0.0
 */
final class TarGzReader
{
    /**
     * Tar block size in bytes.
     *
     * @var int
     */
    private const BLOCK = 512;

    /**
     * Creates a new reader.
     *
     * @since 1.0.0
     *
     * @param  int  $maxUncompressedBytes  Maximum decompressed tar size.
     * @param  int  $maxEntries            Maximum number of regular files.
     */
    public function __construct(
        private readonly int $maxUncompressedBytes,
        private readonly int $maxEntries = 5000,
    ) {
    }

    /**
     * Reads every regular file in the archive.
     *
     * @since 1.0.0
     *
     * @param  string  $compressed  The gzip-compressed tar bytes.
     *
     * @throws InvalidArchiveException When the archive is malformed or exceeds a limit.
     *
     * @return array<string, string> File contents keyed by archive path.
     */
    public function read( string $compressed ): array
    {
        $tar = @gzdecode( $compressed, $this->maxUncompressedBytes );

        if ( false === $tar ) {
            throw new InvalidArchiveException( __( 'The OKF archive is not valid gzip data or exceeds :bytes bytes when decompressed.', [
                'bytes' => $this->maxUncompressedBytes,
            ] ) );
        }

        $files    = [];
        $offset   = 0;
        $length   = strlen( $tar );
        $longName = null;
        $paxPath  = null;

        while ( $offset + self::BLOCK <= $length ) {
            $header = substr( $tar, $offset, self::BLOCK );

            if ( str_repeat( "\0", self::BLOCK ) === $header ) {
                break;
            }

            $this->assertChecksum( $header );

            $size       = $this->octal( substr( $header, 124, 12 ), 'size' );
            $type       = $header[156];
            $dataOffset = $offset + self::BLOCK;

            if ( $dataOffset + $size > $length ) {
                throw new InvalidArchiveException( __( 'The OKF archive is truncated.' ) );
            }

            $data   = substr( $tar, $dataOffset, $size );
            $offset = $dataOffset + (int) ( ceil( $size / self::BLOCK ) * self::BLOCK );

            if ( 'L' === $type ) {
                $longName = rtrim( $data, "\0" );

                continue;
            }

            if ( 'x' === $type ) {
                $paxPath = $this->paxPath( $data );

                continue;
            }

            if ( 'g' === $type ) {
                continue;
            }

            $path     = $paxPath ?? $longName ?? $this->headerPath( $header );
            $longName = null;
            $paxPath  = null;

            if ( '0' !== $type && "\0" !== $type ) {
                continue;
            }

            if ( count( $files ) >= $this->maxEntries ) {
                throw new InvalidArchiveException( __( 'The OKF archive contains more than :count files.', [
                    'count' => $this->maxEntries,
                ] ) );
            }

            $files[ $path ] = $data;
        }

        return $files;
    }

    /**
     * Verifies a header block's checksum.
     *
     * @since 1.0.0
     *
     * @param  string  $header  The 512-byte header.
     *
     * @throws InvalidArchiveException When the checksum does not match.
     *
     * @return void
     */
    private function assertChecksum( string $header ): void
    {
        $expected = $this->octal( substr( $header, 148, 8 ), 'checksum' );
        $actual   = array_sum( unpack( 'C*', substr( $header, 0, 148 ) . str_repeat( ' ', 8 ) . substr( $header, 156 ) ) );

        if ( $expected !== $actual ) {
            throw new InvalidArchiveException( __( 'The OKF archive has a corrupt header.' ) );
        }
    }

    /**
     * Parses an octal header field.
     *
     * @since 1.0.0
     *
     * @param  string  $field  The raw field.
     * @param  string  $name   The field name, for error messages.
     *
     * @throws InvalidArchiveException When the field is not octal.
     *
     * @return int
     */
    private function octal( string $field, string $name ): int
    {
        $value = trim( $field, "\0 " );

        if ( 1 !== preg_match( '/^[0-7]{1,12}$/', $value ) ) {
            throw new InvalidArchiveException( __( 'The OKF archive has an invalid :field field.', [ 'field' => $name ] ) );
        }

        return (int) octdec( $value );
    }

    /**
     * Gets the path stored in a ustar header (prefix + name).
     *
     * @since 1.0.0
     *
     * @param  string  $header  The 512-byte header.
     *
     * @return string
     */
    private function headerPath( string $header ): string
    {
        $name = rtrim( substr( $header, 0, 100 ), "\0" );

        if ( ! str_starts_with( substr( $header, 257, 6 ), 'ustar' ) ) {
            return $name;
        }

        $prefix = rtrim( substr( $header, 345, 155 ), "\0" );

        return '' === $prefix ? $name : $prefix . '/' . $name;
    }

    /**
     * Gets the `path` record from a pax extended header, if any.
     *
     * @since 1.0.0
     *
     * @param  string  $data  The pax header data.
     *
     * @return string|null
     */
    private function paxPath( string $data ): ?string
    {
        $offset = 0;

        while ( 1 === preg_match( '/\G(\d+) /', $data, $matches, 0, $offset ) ) {
            $recordLength = (int) $matches[1];

            if ( $recordLength <= strlen( $matches[0] ) ) {
                break;
            }

            $record = substr( $data, $offset + strlen( $matches[0] ), $recordLength - strlen( $matches[0] ) - 1 );
            $offset += $recordLength;

            if ( str_starts_with( $record, 'path=' ) ) {
                return substr( $record, 5 );
            }
        }

        return null;
    }
}
