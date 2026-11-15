<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set when Google refuses a renewal for the client; the next good renewal clears it
        // (youtube-music-credentials, design D4).
        Schema::table('youtube_music_connections', function (Blueprint $table) {
            $table->timestamp('config_error_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('youtube_music_connections', function (Blueprint $table) {
            $table->dropColumn('config_error_at');
        });
    }
};
