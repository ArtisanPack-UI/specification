<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use ArtisanPackUI\Specification\Exceptions\McpUnavailableException;
use ArtisanPackUI\Specification\Mcp\HttpMcpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const MCP_URL = 'https://mcp.specification.website/mcp';

/**
 * Builds an enabled client against the fake HTTP factory.
 */
function mcpClient( string $url = MCP_URL ): HttpMcpClient
{
    return new HttpMcpClient( app( HttpFactory::class ), true, $url, 5 );
}

/**
 * Fakes the MCP endpoint, answering each tool call with the given structured content.
 *
 * @param  array<string, array<string, mixed>>  $results  Structured content keyed by tool name.
 */
function fakeMcpTools( array $results ): void
{
    Http::fake( [
        MCP_URL => function ( Request $request ) use ( $results ) {
            $tool = $request->data()['params']['name'];

            return Http::response( [
                'jsonrpc' => '2.0',
                'id'      => $request->data()['id'],
                'result'  => [
                    'content'           => [ [ 'type' => 'text', 'text' => 'ignored' ] ],
                    'structuredContent' => $results[ $tool ],
                ],
            ] );
        },
    ] );
}

/**
 * Upstream topic item for the doctype fixture topic.
 *
 * @param  array<string, mixed>  $overrides
 *
 * @return array<string, mixed>
 */
function upstreamDoctype( array $overrides = [] ): array
{
    return array_merge( [
        'slug'     => 'doctype',
        'title'    => 'The HTML doctype',
        'status'   => 'required',
        'category' => 'foundations',
        'summary'  => 'Every HTML document must start with <!doctype html>.',
        'url'      => 'https://specification.website/spec/foundations/doctype/',
    ], $overrides );
}

it( 'is bound from config and disabled by default', function (): void {
    $client = app( McpClient::class );

    expect( $client )->toBeInstanceOf( HttpMcpClient::class )
        ->and( $client->isEnabled() )->toBeFalse()
        ->and( specification()->mcp() )->toBe( $client );
} );

it( 'is enabled by the env-backed config flag', function (): void {
    config()->set( 'artisanpack.specification.mcp.enabled', true );
    app()->forgetInstance( McpClient::class );

    expect( app( McpClient::class )->isEnabled() )->toBeTrue();
} );

it( 'refuses to make requests while disabled', function ( Closure $call ): void {
    Http::fake();

    expect( fn () => $call( app( McpClient::class ) ) )->toThrow( McpUnavailableException::class, 'SPECIFICATION_MCP_ENABLED' );

    Http::assertNothingSent();
} )->with( [
    'list_topics' => [ fn ( McpClient $client ) => $client->listTopics() ],
    'get_topic'   => [ fn ( McpClient $client ) => $client->getTopic( 'foundations/doctype' ) ],
    'get_changes' => [ fn ( McpClient $client ) => $client->getChanges() ],
    'search'      => [ fn ( McpClient $client ) => $client->search( 'doctype' ) ],
] );

it( 'sends a stateless JSON-RPC tools/call request', function (): void {
    fakeMcpTools( [ 'list_topics' => [ 'total' => 0, 'count' => 0, 'filters' => [], 'topics' => [] ] ] );

    mcpClient()->listTopics();

    Http::assertSent( function ( Request $request ): bool {
        return MCP_URL === $request->url()
            && 'POST' === $request->method()
            && str_contains( $request->header( 'Accept' )[0], 'text/event-stream' )
            && str_contains( $request->body(), '"arguments":{}' )
            && [ 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [ 'name' => 'list_topics', 'arguments' => [] ] ] === json_decode( $request->body(), true );
    } );
} );

it( 'lists topics with local category/topic slugs', function (): void {
    fakeMcpTools( [ 'list_topics' => [
        'total'   => 2,
        'count'   => 2,
        'filters' => [ 'category' => 'foundations' ],
        'topics'  => [ upstreamDoctype(), upstreamDoctype( [ 'slug' => 'html-lang', 'title' => 'html lang', 'status' => 'recommended' ] ) ],
    ] ] );

    $topics = mcpClient()->listTopics( 'foundations', SpecStatus::Required, 10 );

    expect( $topics )->toHaveCount( 2 )
        ->and( $topics[0]->slug )->toBe( 'foundations/doctype' )
        ->and( $topics[0]->status )->toBe( SpecStatus::Required )
        ->and( $topics[0]->markdown )->toBeNull()
        ->and( $topics[1]->slug )->toBe( 'foundations/html-lang' )
        ->and( $topics[1]->status )->toBe( SpecStatus::Recommended );

    Http::assertSent( fn ( Request $request ): bool => [ 'category' => 'foundations', 'status' => 'required', 'limit' => 10 ] === (array) $request->data()['params']['arguments'] );
} );

it( 'fetches a topic by its local slug using the upstream bare slug', function (): void {
    fakeMcpTools( [ 'get_topic' => upstreamDoctype( [
        'mdUrl'    => 'https://specification.website/spec/foundations/doctype.md',
        'updated'  => '2026-07-27T00:00:00.000Z',
        'markdown' => "---\ntitle: The HTML doctype\n---\n\n# The HTML doctype",
    ] ) ] );

    $topic = mcpClient()->getTopic( 'foundations/doctype' );

    expect( $topic->slug )->toBe( 'foundations/doctype' )
        ->and( $topic->markdown )->toContain( '# The HTML doctype' )
        ->and( $topic->updatedAt?->toDateString() )->toBe( '2026-07-27' )
        ->and( $topic->attribution()->url )->toBe( 'https://specification.website/spec/foundations/doctype/' )
        ->and( $topic->toArray() )->not->toHaveKey( 'markdown' )
        ->and( $topic->toArray()['attribution']['license'] )->toBe( 'CC BY 4.0' );

    Http::assertSent( fn ( Request $request ): bool => [ 'slug' => 'doctype' ] === (array) $request->data()['params']['arguments'] );
} );

it( 'rejects a topic from a different category than requested', function (): void {
    fakeMcpTools( [ 'get_topic' => upstreamDoctype( [ 'markdown' => '# x' ] ) ] );

    mcpClient()->getTopic( 'seo/doctype' );
} )->throws( McpRequestException::class, 'returned [foundations/doctype] when [seo/doctype] was requested' );

it( 'gets upstream changes since a date', function (): void {
    fakeMcpTools( [ 'get_changes' => [
        'since'   => '2026-09-01',
        'type'    => null,
        'count'   => 2,
        'latest'  => '2026-09-30',
        'changes' => [
            [
                'date'            => '2026-09-30',
                'type'            => 'status',
                'title'           => 'Doctype promoted',
                'summary'         => 'Now required.',
                'topics'          => [ upstreamDoctype() ],
                'unresolvedSlugs' => [],
            ],
            [
                'date'            => '2026-09-10',
                'type'            => 'removed',
                'title'           => 'Removed a page',
                'summary'         => 'Gone.',
                'topics'          => [],
                'unresolvedSlugs' => [ 'old-page' ],
            ],
        ],
    ] ] );

    $set = mcpClient()->getChanges( Carbon\CarbonImmutable::parse( '2026-09-01 13:45:00' ), ChangeType::Status, 50 );

    expect( $set->since )->toBe( '2026-09-01' )
        ->and( $set->latest )->toBe( '2026-09-30' )
        ->and( $set->changes )->toHaveCount( 2 )
        ->and( $set->changes[0]->type )->toBe( ChangeType::Status )
        ->and( $set->changes[0]->date->toDateString() )->toBe( '2026-09-30' )
        ->and( $set->changes[0]->slugs() )->toBe( [ 'foundations/doctype' ] )
        ->and( $set->changes[1]->unresolvedSlugs )->toBe( [ 'old-page' ] )
        ->and( $set->slugs() )->toBe( [ 'foundations/doctype' ] )
        ->and( $set->isEmpty() )->toBeFalse()
        ->and( $set->toArray()['changes'][0]['topics'][0]['slug'] )->toBe( 'foundations/doctype' );

    Http::assertSent( fn ( Request $request ): bool => [ 'since' => '2026-09-01', 'type' => 'status', 'limit' => 50 ] === (array) $request->data()['params']['arguments'] );
} );

it( 'searches the upstream spec and clamps the limit', function (): void {
    fakeMcpTools( [ 'search' => [
        'query'   => 'doctype',
        'count'   => 1,
        'results' => [ upstreamDoctype( [ 'score' => 12.5, 'mdUrl' => 'x', 'excerpts' => [] ] ) ],
    ] ] );

    $hits = mcpClient()->search( 'doctype', 500 );

    expect( $hits )->toHaveCount( 1 )
        ->and( $hits[0]->slug )->toBe( 'foundations/doctype' );

    Http::assertSent( fn ( Request $request ): bool => [ 'query' => 'doctype', 'limit' => 25 ] === (array) $request->data()['params']['arguments'] );
} );

it( 'parses responses delivered as a server-sent event stream', function (): void {
    Http::fake( [
        MCP_URL => fn ( Request $request ) => Http::response(
            "event: message\ndata: " . json_encode( [ 'jsonrpc' => '2.0', 'method' => 'notifications/progress' ] ) . "\n\n"
            . "event: message\ndata: " . json_encode( [
                'jsonrpc' => '2.0',
                'id'      => $request->data()['id'],
                'result'  => [ 'structuredContent' => [ 'query' => 'x', 'count' => 1, 'results' => [ upstreamDoctype() ] ] ],
            ] ) . "\n\n",
            200,
            [ 'Content-Type' => 'text/event-stream' ],
        ),
    ] );

    expect( mcpClient()->search( 'x' )[0]->slug )->toBe( 'foundations/doctype' );
} );

it( 'surfaces tool errors', function (): void {
    Http::fake( [ MCP_URL => fn ( Request $request ) => Http::response( [
        'jsonrpc' => '2.0',
        'id'      => $request->data()['id'],
        'result'  => [ 'isError' => true, 'content' => [ [ 'type' => 'text', 'text' => 'No spec page with slug "nope".' ] ] ],
    ] ) ] );

    mcpClient()->getTopic( 'nope' );
} )->throws( McpRequestException::class, 'No spec page with slug "nope".' );

it( 'surfaces JSON-RPC errors', function (): void {
    Http::fake( [ MCP_URL => fn ( Request $request ) => Http::response( [
        'jsonrpc' => '2.0',
        'id'      => $request->data()['id'],
        'error'   => [ 'code' => -32602, 'message' => 'Unknown tool' ],
    ] ) ] );

    mcpClient()->listTopics();
} )->throws( McpRequestException::class, 'Unknown tool' );

it( 'fails on unsuccessful HTTP responses', function (): void {
    Http::fake( [ MCP_URL => Http::response( 'busy', 503 ) ] );

    mcpClient()->listTopics();
} )->throws( McpRequestException::class, 'HTTP 503' );

it( 'wraps transport errors', function (): void {
    Http::fake( fn () => throw new ConnectionException( 'timed out' ) );

    mcpClient()->listTopics();
} )->throws( McpRequestException::class, 'timed out' );

it( 'rejects malformed responses', function ( ?array $message, int $idOffset = 0 ): void {
    Http::fake( [ MCP_URL => fn ( Request $request ) => Http::response(
        null === $message ? 'not json' : array_merge( [ 'jsonrpc' => '2.0', 'id' => $request->data()['id'] + $idOffset ], $message ),
    ) ] );

    mcpClient()->listTopics();
} )->with( [
    'not JSON'                => [ null ],
    'mismatched id'           => [ [ 'result' => [ 'structuredContent' => [ 'topics' => [] ] ] ], 1 ],
    'no result'               => [ [] ],
    'no structured content'   => [ [ 'result' => [ 'content' => [] ] ] ],
    'no topics list'          => [ [ 'result' => [ 'structuredContent' => [ 'count' => 0 ] ] ] ],
    'unknown status'          => [ [ 'result' => [ 'structuredContent' => [ 'topics' => [ upstreamDoctype( [ 'status' => 'mandatory' ] ) ] ] ] ] ],
    'topic missing its title' => [ [ 'result' => [ 'structuredContent' => [ 'topics' => [ upstreamDoctype( [ 'title' => '' ] ) ] ] ] ] ],
] )->throws( McpRequestException::class );

it( 'rejects malformed change entries', function ( array $change ): void {
    fakeMcpTools( [ 'get_changes' => [ 'since' => null, 'latest' => null, 'changes' => [ $change ] ] ] );

    mcpClient()->getChanges();
} )->with( [
    'unknown type' => [ [ 'date' => '2026-09-30', 'type' => 'renamed', 'title' => 'x', 'summary' => '', 'topics' => [] ] ],
    'invalid date' => [ [ 'date' => 'not a date', 'type' => 'added', 'title' => 'x', 'summary' => '', 'topics' => [] ] ],
] )->throws( McpRequestException::class );

it( 'refuses a non-https MCP URL', function (): void {
    Http::fake();

    expect( fn () => mcpClient( 'http://mcp.specification.website/mcp' )->listTopics() )->toThrow( McpRequestException::class, 'must use https' );

    Http::assertNothingSent();
} );

it( 'refuses oversized responses', function (): void {
    Http::fake( [ MCP_URL => Http::response( str_repeat( 'x', HttpMcpClient::MAX_RESPONSE_BYTES + 1 ) ) ] );

    mcpClient()->listTopics();
} )->throws( McpRequestException::class, 'exceeds the' );

it( 'normalizes MCP topic URLs to the upstream origin', function ( mixed $url ): void {
    $topic = ArtisanPackUI\Specification\Mcp\McpTopic::fromArray( upstreamDoctype( [ 'url' => $url ] ) );

    expect( $topic->url )->toBe( 'https://specification.website/spec/foundations/doctype/' )
        ->and( $topic->attribution()->toHtml() )->not->toContain( 'javascript:' )
        ->and( $topic->attribution()->toHtml() )->not->toContain( 'evil.example' );
} )->with( [
    'javascript'   => 'javascript:alert(1)',
    'foreign host' => 'https://evil.example/spec/foundations/doctype/',
    'missing'      => null,
] );

it( 'clamps the list and change limits to the documented ranges', function ( string $tool, Closure $call, int $expected ): void {
    fakeMcpTools( [
        'list_topics' => [ 'topics' => [] ],
        'get_changes' => [ 'changes' => [] ],
    ] );

    $call( mcpClient() );

    Http::assertSent( fn ( Request $request ): bool => $tool === $request->data()['params']['name']
        && $expected === ( (array) $request->data()['params']['arguments'] )['limit'] );
} )->with( [
    'list above'    => [ 'list_topics', fn ( HttpMcpClient $client ) => $client->listTopics( limit: 1000 ), 200 ],
    'list below'    => [ 'list_topics', fn ( HttpMcpClient $client ) => $client->listTopics( limit: 0 ), 1 ],
    'changes above' => [ 'get_changes', fn ( HttpMcpClient $client ) => $client->getChanges( limit: 500 ), 100 ],
    'changes below' => [ 'get_changes', fn ( HttpMcpClient $client ) => $client->getChanges( limit: -5 ), 1 ],
] );

it( 'tolerates a JSON-RPC error whose message is not a string', function (): void {
    Http::fake( [ MCP_URL => fn ( Request $request ) => Http::response( [
        'jsonrpc' => '2.0',
        'id'      => $request->data()['id'],
        'error'   => [ 'code' => -32000, 'message' => [ 'nested' => 'value' ] ],
    ] ) ] );

    mcpClient()->listTopics();
} )->throws( McpRequestException::class, 'Unknown error' );

it( 'sends the UTC day for a date-time since value', function (): void {
    fakeMcpTools( [ 'get_changes' => [ 'changes' => [] ] ] );

    mcpClient()->getChanges( Carbon\CarbonImmutable::parse( '2026-09-01 23:30:00', 'America/Chicago' ) );

    Http::assertSent( fn ( Request $request ): bool => '2026-09-02' === ( (array) $request->data()['params']['arguments'] )['since'] );
} );
