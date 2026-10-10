<?php

/**
 * Creates the specification_snapshots table.
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
        Schema::create( 'specification_snapshots', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'version', 64 )->unique();
            $table->string( 'okf_version', 16 )->nullable();
            $table->unsignedInteger( 'topic_count' );
            $table->json( 'changes' )->nullable();
            $table->timestamp( 'synced_at' )->index();
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
        Schema::dropIfExists( 'specification_snapshots' );
    }
};
