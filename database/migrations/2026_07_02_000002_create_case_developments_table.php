<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_developments', function (Blueprint $table) {
            $table->id();
            $table->string('incident_type', 20);
            $table->unsignedBigInteger('incident_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->index(['incident_type', 'incident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_developments');
    }
};
