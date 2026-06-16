<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickers', function (Blueprint $table) {
            $table->unsignedInteger('repeat_count')->nullable()->after('end_time');   // null/0 = бесконечно
            $table->unsignedInteger('duration_minutes')->nullable()->after('repeat_count');
            $table->timestamp('started_at')->nullable()->after('duration_minutes');    // ставится сервером при включении
        });
    }

    public function down(): void
    {
        Schema::table('tickers', function (Blueprint $table) {
            $table->dropColumn(['repeat_count', 'duration_minutes', 'started_at']);
        });
    }
};
