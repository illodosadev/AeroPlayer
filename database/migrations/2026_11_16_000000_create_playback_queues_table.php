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
        // One queue per owner, with the fields of the later queue changes already in place (playback-queue,
        // design D1). current_item_id has no foreign key: the repository keeps it valid.
        Schema::create('playback_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->unsignedBigInteger('current_item_id')->nullable();
            $table->boolean('shuffle')->default(false);
            $table->string('repeat', 8)->default('off');
            $table->string('mode', 32)->default('queue');
            $table->string('context_uri')->nullable();
            $table->string('context_title', 500)->nullable();
            $table->json('continuation')->nullable();
            $table->timestamps();
        });

        Schema::create('playback_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playback_queue_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('original_position');
            $table->string('source', 32);
            $table->string('song_id', 64);
            $table->string('uri');
            $table->string('title', 500);
            $table->json('artists');
            $table->string('album', 500)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('image', 2048)->nullable();
            $table->json('origin')->nullable();
            $table->timestamps();

            $table->index(['playback_queue_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playback_queue_items');
        Schema::dropIfExists('playback_queues');
    }
};
