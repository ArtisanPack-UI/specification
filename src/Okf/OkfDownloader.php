<?php

/**
 * OKF archive downloader.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Exceptions\SyncFailedException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Downloads the OKF archive over HTTPS with a size cap.
 *
 * The cap is enforced while streaming (declared Content-Length and bytes
 * received so far) and again on the final body.
 *
 * @since 1.0.0
 */
final class OkfDownloader
{
    /**
     * Creates a new downloader.
     *
     * @since 1.0.0
     *
     * @param  HttpFactory  $http      The HTTP client factory.
     * @param  string       $url       The archive URL (must be https).
     * @param  int          $timeout   Request timeout in seconds.
     * @param  int          $maxBytes  Maximum archive size in bytes.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $url,
        private readonly int $timeout,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * Downloads the archive bytes.
     *
     * @since 1.0.0
     *
     * @throws SyncFailedException When the URL is not https, the request fails, or the archive is too large.
     *
     * @return string
     */
    public function download(): string
    {
        if ( 'https' !== strtolower( (string) parse_url( $this->url, PHP_URL_SCHEME ) ) ) {
            throw new SyncFailedException( __( 'The OKF URL must use https: :url', [ 'url' => $this->url ] ) );
        }

        $tooLarge = fn (): SyncFailedException => new SyncFailedException( __( 'The OKF archive exceeds the :bytes byte limit.', [ 'bytes' => $this->maxBytes ] ) );

        try {
            $response = $this->http
                ->timeout( $this->timeout )
                ->accept( 'application/gzip' )
                ->withOptions( [
                    'allow_redirects' => [
                        'max'       => 3,
                        'protocols' => [ 'https' ],
                    ],
                    // Abort mid-transfer instead of buffering an oversized body.
                    'on_headers'      => function ( ResponseInterface $response ) use ( $tooLarge ): void {
                        if ( (int) $response->getHeaderLine( 'Content-Length' ) > $this->maxBytes ) {
                            throw $tooLarge();
                        }
                    },
                    'progress'        => function ( int $expected, int $downloaded ) use ( $tooLarge ): void {
                        if ( $downloaded > $this->maxBytes ) {
                            throw $tooLarge();
                        }
                    },
                ] )
                ->get( $this->url );
        } catch ( Throwable $exception ) {
            for ( $previous = $exception; null !== $previous; $previous = $previous->getPrevious() ) {
                if ( $previous instanceof SyncFailedException ) {
                    throw $previous;
                }
            }

            throw new SyncFailedException( __( 'Could not download :url: :message', [
                'url'     => $this->url,
                'message' => $exception->getMessage(),
            ] ), previous: $exception );
        }

        if ( ! $response->successful() ) {
            throw new SyncFailedException( __( 'Downloading :url failed with HTTP :status.', [
                'url'    => $this->url,
                'status' => $response->status(),
            ] ) );
        }

        $declared = $response->header( 'Content-Length' );

        if ( ( '' !== $declared && (int) $declared > $this->maxBytes ) || strlen( $response->body() ) > $this->maxBytes ) {
            throw $tooLarge();
        }

        return $response->body();
    }
}
