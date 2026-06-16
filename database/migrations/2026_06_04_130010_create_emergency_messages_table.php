<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_messages', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('text');
            $table->string('target_type')->default('all');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('background_color')->default('#b00020');
            $table->string('text_color')->default('#ffffff');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_messages');
    }
};
