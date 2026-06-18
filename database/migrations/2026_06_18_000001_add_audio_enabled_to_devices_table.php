<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Play media with sound on this screen. Off by default (most screens
            // are silent; browser autoplay also defaults to muted).
            $table->boolean('audio_enabled')->default(false)->after('screen_orientation');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('audio_enabled');
        });
    }
};
