<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('name');
            $table->string('device_code')->unique();
            $table->string('pairing_code')->nullable()->unique();
            $table->string('api_token')->nullable()->unique();
            $table->string('platform')->nullable();
            $table->string('device_type')->default('android_tv');
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('android_id')->nullable();
            $table->string('screen_orientation')->default('landscape');
            $table->string('resolution')->nullable();
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('app_version')->nullable();
            $table->foreignId('current_playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->bigInteger('free_storage')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
