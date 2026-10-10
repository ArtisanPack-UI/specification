<?php

/**
 * HTTP MCP client.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Mcp;

use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use ArtisanPackUI\Specification\Exceptions\McpUnavailableException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Calls the specification.website MCP server's tools over Streamable HTTP.
 *
 * The upstream server is stateless and unauthenticated, so each call is a
 * single JSON-RPC `tools/call` POST with no session handshake. Responses may
 * be plain JSON or a server-sent event stream; the tool's
 * `structuredContent` is used as the result. Bodies larger than
 * MAX_RESPONSE_BYTES are refused, while streaming and again at the end.
 *
 * @since 1.0.0
 */
final class HttpMcpClient implements McpClient
{
    /**
     * Largest response body accepted, in bytes.
     *
     * @var int
     */
    public const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;

    /**
     * The JSON-RPC request id of the last call.
     *
     * @var int
     */
    private int $requestId = 0;

    /**
     * Creates a new client.
     *
     * @since 1.0.0
     *
     * @param  HttpFactory  $http     The HTTP client factory.
     * @param  bool         $enabled  Whether the client may make requests.
     * @param  string       $url      The MCP endpoint (must be https).
     * @param  int          $timeout  Request timeout in seconds.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly bool $enabled,
        private readonly string $url,
        private readonly int $timeout,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function listTopics( ?string $category = null, ?SpecStatus $status = null, ?int $limit = null ): array
    {
        $content = $this->call( 'list_topics', array_filter( [
            'category' => $category,
            'status'   => $status?->value,
            'limit'    => null === $limit ? null : max( 1, min( 200, $limit ) ),
        ], static fn ( mixed $value ): bool => null !== $value ) );

        return $this->topics( $content['topics'] ?? null, 'topics' );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function getTopic( string $slug ): McpTopic
    {
        $upstream = str_contains( $slug, '/' ) ? substr( $slug, strrpos( $slug, '/' ) + 1 ) : $slug;
        $topic    = McpTopic::fromArray( $this->call( 'get_topic', [ 'slug' => $upstream ] ) );

        if ( str_contains( $slug, '/' ) && $topic->slug !== $slug ) {
            throw new McpRequestException( __( 'The MCP server returned [:returned] when [:requested] was requested.', [
                'returned'  => $topic->slug,
                'requested' => $slug,
            ] ) );
        }

        return $topic;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function getChanges( DateTimeInterface|string|null $since = null, ?ChangeType $type = null, ?int $limit = null ): McpChangeSet
    {
        // Upstream change dates are UTC days.
        if ( $since instanceof DateTimeInterface ) {
            $since = CarbonImmutable::instance( $since )->utc()->toDateString();
        }

        return McpChangeSet::fromArray( $this->call( 'get_changes', array_filter( [
            'since' => $since,
            'type'  => $type?->value,
            'limit' => null === $limit ? null : max( 1, min( 100, $limit ) ),
        ], static fn ( mixed $value ): bool => null !== $value ) ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function search( string $query, int $limit = 5 ): array
    {
        $content = $this->call( 'search', [
            'query' => $query,
            'limit' => max( 1, min( 25, $limit ) ),
        ] );

        return $this->topics( $content['results'] ?? null, 'results' );
    }

    /**
     * Calls a tool and returns its structured content.
     *
     * @since 1.0.0
     *
     * @param  string                $tool       The tool name.
     * @param  array<string, mixed>  $arguments  The tool arguments.
     *
     * @throws McpUnavailableException When the client is disabled.
     * @throws McpRequestException     When the request fails or the response is invalid.
     *
     * @return array<string, mixed>
     */
    private function call( string $tool, array $arguments ): array
    {
        if ( ! $this->enabled ) {
            throw new McpUnavailableException( __( 'The specification.website MCP client is disabled. Set SPECIFICATION_MCP_ENABLED=true to enable it.' ) );
        }

        if ( 'https' !== strtolower( (string) parse_url( $this->url, PHP_URL_SCHEME ) ) ) {
            throw new McpRequestException( __( 'The MCP URL must use https: :url', [ 'url' => $this->url ] ) );
        }

        $id       = ++$this->requestId;
        $tooLarge = fn (): McpRequestException => new McpRequestException( __( 'The MCP response exceeds the :bytes byte limit.', [ 'bytes' => self::MAX_RESPONSE_BYTES ] ) );

        try {
            $response = $this->http
                ->timeout( $this->timeout )
                ->withHeaders( [ 'Accept' => 'application/json, text/event-stream' ] )
                ->withOptions( [
                    'allow_redirects' => false,
                    'on_headers'      => function ( ResponseInterface $response ) use ( $tooLarge ): void {
                        if ( (int) $response->getHeaderLine( 'Content-Length' ) > self::MAX_RESPONSE_BYTES ) {
                            throw $tooLarge();
                        }
                    },
                    'progress'        => function ( int $expected, int $downloaded ) use ( $tooLarge ): void {
                        if ( $downloaded > self::MAX_RESPONSE_BYTES ) {
                            throw $tooLarge();
                        }
                    },
                ] )
                ->asJson()
                ->post( $this->url, [
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'method'  => 'tools/call',
                    'params'  => [
                        'name'      => $tool,
                        'arguments' => (object) $arguments,
                    ],
                ] );
        } catch ( Throwable $exception ) {
            for ( $previous = $exception; null !== $previous; $previous = $previous->getPrevious() ) {
                if ( $previous instanceof McpRequestException ) {
                    throw $previous;
                }
            }

            throw new McpRequestException( __( 'Could not reach the MCP server at :url: :message', [
                'url'     => $this->url,
                'message' => $exception->getMessage(),
            ] ), previous: $exception );
        }

        if ( ! $response->successful() ) {
            throw new McpRequestException( __( 'The MCP tool [:tool] failed with HTTP :status.', [
                'tool'   => $tool,
                'status' => $response->status(),
            ] ) );
        }

        if ( strlen( $response->body() ) > self::MAX_RESPONSE_BYTES ) {
            throw $tooLarge();
        }

        $message = $this->message( $response, $id );

        if ( is_array( $message['error'] ?? null ) ) {
            throw new McpRequestException( __( 'The MCP tool [:tool] returned an error: :message', [
                'tool'    => $tool,
                'message' => is_string( $message['error']['message'] ?? null ) ? $message['error']['message'] : __( 'Unknown error' ),
            ] ) );
        }

        $result = $message['result'] ?? null;

        if ( ! is_array( $result ) ) {
            throw new McpRequestException( __( 'The MCP tool [:tool] returned no result.', [ 'tool' => $tool ] ) );
        }

        if ( true === ( $result['isError'] ?? false ) ) {
            throw new McpRequestException( __( 'The MCP tool [:tool] returned an error: :message', [
                'tool'    => $tool,
                'message' => $this->text( $result ),
            ] ) );
        }

        if ( ! is_array( $result['structuredContent'] ?? null ) ) {
            throw new McpRequestException( __( 'The MCP tool [:tool] returned no structured content.', [ 'tool' => $tool ] ) );
        }

        return $result['structuredContent'];
    }

    /**
     * Decodes the JSON-RPC response message from a JSON or SSE body.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  The HTTP response.
     * @param  int       $id        The request id to match.
     *
     * @throws McpRequestException When no matching JSON-RPC message is found.
     *
     * @return array<string, mixed>
     */
    private function message( Response $response, int $id ): array
    {
        $body = $response->body();

        if ( str_contains( strtolower( $response->header( 'Content-Type' ) ), 'text/event-stream' ) ) {
            foreach ( preg_split( "/\r?\n\r?\n/", $body ) ?: [] as $event ) {
                $data = [];

                foreach ( preg_split( "/\r?\n/", $event ) ?: [] as $line ) {
                    if ( str_starts_with( $line, 'data:' ) ) {
                        $data[] = ltrim( substr( $line, 5 ) );
                    }
                }

                $decoded = json_decode( implode( "\n", $data ), true );

                if ( is_array( $decoded ) && ( $decoded['id'] ?? null ) === $id ) {
                    return $decoded;
                }
            }
        } else {
            $decoded = json_decode( $body, true );

            if ( is_array( $decoded ) && ( $decoded['id'] ?? null ) === $id ) {
                return $decoded;
            }
        }

        throw new McpRequestException( __( 'The MCP server returned an invalid JSON-RPC response.' ) );
    }

    /**
     * Joins the text content blocks of a tool result.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  The tool result.
     *
     * @return string
     */
    private function text( array $result ): string
    {
        $text = [];

        foreach ( is_array( $result['content'] ?? null ) ? $result['content'] : [] as $block ) {
            if ( is_array( $block ) && 'text' === ( $block['type'] ?? null ) && is_string( $block['text'] ?? null ) ) {
                $text[] = $block['text'];
            }
        }

        return [] === $text ? __( 'Unknown error' ) : implode( ' ', $text );
    }

    /**
     * Maps a list of topic items.
     *
     * @since 1.0.0
     *
     * @param  mixed   $items  The raw list.
     * @param  string  $key    The field name, for errors.
     *
     * @throws McpRequestException When the list is missing.
     *
     * @return array<int, McpTopic>
     */
    private function topics( mixed $items, string $key ): array
    {
        if ( ! is_array( $items ) ) {
            throw new McpRequestException( __( 'The MCP server returned no [:key] list.', [ 'key' => $key ] ) );
        }

        return array_map(
            static fn ( mixed $item ): McpTopic => McpTopic::fromArray( is_array( $item ) ? $item : [] ),
            array_values( $items ),
        );
    }
}
