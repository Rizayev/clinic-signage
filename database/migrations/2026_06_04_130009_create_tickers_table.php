<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('text');
            $table->string('target_type')->default('all');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('position')->default('bottom');
            $table->integer('speed')->default(60);
            $table->integer('font_size')->default(28);
            $table->string('text_color')->default('#ffffff');
            $table->string('background_color')->default('#000000');
            $table->decimal('opacity', 3, 2)->default(0.80);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickers');
    }
};
