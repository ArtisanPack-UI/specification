<?php

/**
 * Adds the changes column to the specification_snapshots table.
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
        Schema::table( 'specification_snapshots', function ( Blueprint $table ): void {
            $table->json( 'changes' )->nullable()->after( 'topic_count' );
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
        Schema::table( 'specification_snapshots', function ( Blueprint $table ): void {
            $table->dropColumn( 'changes' );
        } );
    }
};
