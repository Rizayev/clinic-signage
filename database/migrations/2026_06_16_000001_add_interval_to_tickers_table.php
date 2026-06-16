<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickers', function (Blueprint $table) {
            // Recurring schedule: show for duration_minutes every interval_minutes.
            // null = no recurrence (duration_minutes keeps its one-shot meaning).
            $table->unsignedInteger('interval_minutes')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tickers', function (Blueprint $table) {
            $table->dropColumn('interval_minutes');
        });
    }
};
