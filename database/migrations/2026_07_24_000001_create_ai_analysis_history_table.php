<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_history', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('action', 20);
            $table->string('period', 10);
            $table->integer('total_data')->default(0);
            $table->text('prompt')->nullable();
            $table->longText('result');
            $table->timestamps();

            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_history');
    }
};
