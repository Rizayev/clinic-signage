<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_messages', function (Blueprint $table) {
            $table->timestamp('scheduled_start')->nullable()->after('ends_at'); // отложенный старт
            $table->timestamp('scheduled_end')->nullable()->after('scheduled_start'); // авто-снятие до времени
            $table->string('display_style')->default('fullscreen')->after('scheduled_end'); // fullscreen|banner
            $table->string('position')->default('bottom')->after('display_style'); // top|bottom (для banner)
            $table->unsignedInteger('font_size')->default(48)->after('position');
            $table->boolean('blink')->default(false)->after('font_size');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_messages', function (Blueprint $table) {
            $table->dropColumn(['scheduled_start', 'scheduled_end', 'display_style', 'position', 'font_size', 'blink']);
        });
    }
};
