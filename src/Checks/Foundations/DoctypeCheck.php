<?php

/**
 * Reference SpecCheck for `foundations/doctype`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Checks\Foundations;

use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecFix;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\SpecStatus;

/**
 * Checks that a page starts with the HTML doctype.
 *
 * This is the in-package reference implementation owner packages can copy:
 * it shows a Page-scoped check that skips when it has nothing to inspect,
 * passes or fails with structured details, and offers an autofix only when
 * the context tells it how to persist the change.
 *
 * Following the HTML syntax rules, a byte order mark, whitespace, and
 * comments may precede the doctype; `<!doctype html>` and the
 * `about:legacy-compat` form both pass, matched case-insensitively. A doctype
 * token later in the document is reported as misplaced; doctype-like text
 * inside a comment or a script, style, textarea, or title element is not a
 * doctype token, so it is ignored and the autofix never changes it.
 *
 * The check is not registered by default. Enable it by adding the class to
 * the `artisanpack.specification.checks` config array.
 *
 * @since 1.0.0
 */
final class DoctypeCheck implements SpecCheck
{
    /**
     * The topic slug this check enforces.
     *
     * @var string
     */
    public const SLUG = 'foundations/doctype';

    /**
     * Context data key holding a `callable(string): void` that writes fixed HTML.
     *
     * @var string
     */
    public const HTML_WRITER = 'html_writer';

    /**
     * The doctype the autofix inserts.
     *
     * @var string
     */
    public const DOCTYPE = '<!doctype html>';

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function slug(): string
    {
        return self::SLUG;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function status(): SpecStatus
    {
        return SpecStatus::Required;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function scope(): CheckScope
    {
        return CheckScope::Page;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function run( SpecContext $context ): SpecResult
    {
        if ( null === $context->html || '' === trim( $context->html ) ) {
            return SpecResult::skipped( $this, $context, __( 'No HTML was provided for this page.' ) );
        }

        $inspection = $this->inspect( $context->html );

        return match ( $inspection['state'] ) {
            'error'      => SpecResult::errored( $this, $context, __( 'The page HTML could not be inspected (the pattern engine failed: :error).', [
                'error' => preg_last_error_msg(),
            ] ) ),
            'valid'      => SpecResult::passed( $this, $context, __( 'The page starts with the HTML doctype.' ), [
                'found' => $inspection['found'],
            ] ),
            'legacy'     => SpecResult::failed( $this, $context, __( 'The page uses a legacy doctype instead of <!doctype html>.' ), [
                'found'    => $inspection['found'],
                'expected' => self::DOCTYPE,
            ] ),
            'misplaced'  => SpecResult::failed( $this, $context, __( 'The doctype is not the first thing in the document, so browsers render in quirks mode.' ), [
                'found'    => $inspection['found'],
                'expected' => self::DOCTYPE,
            ] ),
            default      => SpecResult::failed( $this, $context, __( 'The page has no doctype, so browsers render in quirks mode.' ), [
                'found'    => null,
                'expected' => self::DOCTYPE,
            ] ),
        };
    }

    /**
     * {@inheritDoc}
     *
     * Returns a fix only when the page fails and the context carries an
     * HTML_WRITER callable; the fix's details include the corrected HTML so
     * a UI or agent can preview the change before applying it. No fix is
     * offered when the HTML could not be inspected.
     *
     * @since 1.0.0
     */
    public function autofix( SpecContext $context ): ?SpecFix
    {
        $writer = $context->get( self::HTML_WRITER );

        if ( null === $context->html || '' === trim( $context->html ) || ! is_callable( $writer ) ) {
            return null;
        }

        $inspection = $this->inspect( $context->html );

        if ( 'valid' === $inspection['state'] || 'error' === $inspection['state'] ) {
            return null;
        }

        $fixed = $this->fix( $context->html, $inspection );

        return new SpecFix(
            self::SLUG,
            null === $inspection['found']
                ? __( 'Add <!doctype html> to the start of the page.' )
                : __( 'Replace :found with <!doctype html> at the start of the page.', [ 'found' => $inspection['found'] ] ),
            static function () use ( $writer, $fixed ): void {
                $writer( $fixed );
            },
            [
                'before' => $inspection['found'],
                'after'  => self::DOCTYPE,
                'html'   => $fixed,
            ],
        );
    }

    /**
     * Locates the doctype and classifies it.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The page HTML.
     *
     * @return array{state: string, found: string|null, offset: int|null, prefix: int}
     *               `state` is one of valid, legacy, misplaced, missing, or
     *               error (the HTML could not be scanned);
     *               `prefix` is the length of the allowed preamble (BOM,
     *               whitespace, comments) before the first real token.
     */
    private function inspect( string $html ): array
    {
        $prefix = $this->preambleLength( $html );
        $rest   = substr( $html, $prefix );

        if ( 1 === preg_match( '/^<!doctype\b[^>]*>/i', $rest, $match ) ) {
            $valid = 1 === preg_match( '/^<!doctype\s+html(?:\s+system\s+(["\'])about:legacy-compat\1)?\s*>$/i', $match[0] );

            return [ 'state' => $valid ? 'valid' : 'legacy', 'found' => $match[0], 'offset' => $prefix, 'prefix' => $prefix ];
        }

        // Doctype-like text inside a comment or a raw-text element (script,
        // style, textarea, title) is not a doctype token, so it is ignored
        // here and the autofix never touches it.
        $searchable = $this->blankNonMarkup( $html );

        if ( null === $searchable ) {
            return [ 'state' => 'error', 'found' => null, 'offset' => null, 'prefix' => $prefix ];
        }

        if ( 1 === preg_match( '/<!doctype\b[^>]*>/i', $searchable, $match, PREG_OFFSET_CAPTURE ) ) {
            return [ 'state' => 'misplaced', 'found' => substr( $html, $match[0][1], strlen( $match[0][0] ) ), 'offset' => $match[0][1], 'prefix' => $prefix ];
        }

        return [ 'state' => 'missing', 'found' => null, 'offset' => null, 'prefix' => $prefix ];
    }

    /**
     * Measures the BOM, whitespace, and comments allowed before the doctype.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The page HTML.
     *
     * @return int
     */
    private function preambleLength( string $html ): int
    {
        $position = str_starts_with( $html, "\u{FEFF}" ) ? 3 : 0;
        $length   = strlen( $html );

        while ( $position < $length ) {
            $position += strspn( $html, " \t\n\f\r", $position );

            if ( ! str_starts_with( substr( $html, $position, 4 ), '<!--' ) ) {
                break;
            }

            $end = strpos( $html, '-->', $position + 4 );

            if ( false === $end ) {
                break;
            }

            $position = $end + 3;
        }

        return $position;
    }

    /**
     * Blanks comments and raw-text element contents with spaces.
     *
     * The result has the same length as the input, so offsets found in it
     * line up with the original HTML. Unterminated comments and elements are
     * left as-is.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The page HTML.
     *
     * @return string|null Null when the pattern engine fails (e.g. a backtrack limit on very large HTML).
     */
    private function blankNonMarkup( string $html ): ?string
    {
        return preg_replace_callback(
            '/<!--.*?-->|<(script|style|textarea|title|xmp)\b[^>]*>.*?<\/\1\s*>/is',
            static fn ( array $match ): string => str_repeat( ' ', strlen( $match[0] ) ),
            $html,
        );
    }

    /**
     * Builds the corrected HTML.
     *
     * A legacy doctype is replaced in place. A misplaced doctype is removed
     * and the HTML doctype is inserted after the byte order mark; a missing
     * one is inserted there too.
     *
     * @since 1.0.0
     *
     * @param  string                                                                  $html        The page HTML.
     * @param  array{state: string, found: string|null, offset: int|null, prefix: int}  $inspection  The inspection.
     *
     * @return string
     */
    private function fix( string $html, array $inspection ): string
    {
        if ( 'legacy' === $inspection['state'] ) {
            return substr_replace( $html, self::DOCTYPE, (int) $inspection['offset'], strlen( (string) $inspection['found'] ) );
        }

        if ( 'misplaced' === $inspection['state'] ) {
            $html = substr_replace( $html, '', (int) $inspection['offset'], strlen( (string) $inspection['found'] ) );
        }

        $bom = str_starts_with( $html, "\u{FEFF}" ) ? "\u{FEFF}" : '';

        return $bom . self::DOCTYPE . "\n" . substr( $html, strlen( $bom ) );
    }
}
