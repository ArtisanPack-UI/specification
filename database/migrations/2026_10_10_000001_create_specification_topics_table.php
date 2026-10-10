<?php

/**
 * Creates the specification_topics table.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Runs the migration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create( 'specification_topics', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'slug' )->unique();
            $table->string( 'category' )->index();
            $table->string( 'status', 16 )->index();
            $table->string( 'conformance', 16 )->nullable();
            $table->string( 'title' );
            $table->text( 'summary' );
            $table->longText( 'body_md' );
            $table->json( 'sources_json' );
            $table->string( 'resource_url', 512 );
            $table->string( 'snapshot_version', 64 )->index();
            $table->text( 'attribution' );
            $table->timestamp( 'source_updated_at' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverses the migration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists( 'specification_topics' );
    }
};
