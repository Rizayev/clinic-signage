<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->string('file_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->integer('duration')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('checksum')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
