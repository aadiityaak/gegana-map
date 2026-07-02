<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hermes_agent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hermes_agent_logs');
    }
};
