<?php

/**
 * Console output sanitizing.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Console\Concerns;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Neutralizes third-party text before it is written to the console.
 *
 * Topic titles, slugs, and error messages can originate upstream (the OKF
 * archive or the MCP server). Control characters are stripped so they
 * cannot inject terminal escape sequences, and console style tags are
 * escaped so they cannot restyle or hide output.
 *
 * @since 1.0.0
 */
trait SanitizesOutput
{
    /**
     * Makes a string safe to print.
     *
     * @since 1.0.0
     *
     * @param  string  $text  The untrusted text.
     *
     * @return string
     */
    protected function plain( string $text ): string
    {
        $text = (string) preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F]/', '', $text );

        return OutputFormatter::escape( $text );
    }
}
