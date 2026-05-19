<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reg_provinces', function (Blueprint $table) {
            $table->char('id', 2)->primary();
            $table->string('name');
        });

        Schema::create('reg_regencies', function (Blueprint $table) {
            $table->char('id', 4)->primary();
            $table->char('province_id', 2);
            $table->string('name');

            $table->foreign('province_id')->references('id')->on('reg_provinces');
            $table->index('province_id');
        });

        Schema::create('reg_districts', function (Blueprint $table) {
            $table->char('id', 6)->primary();
            $table->char('regency_id', 4);
            $table->string('name');

            $table->foreign('regency_id')->references('id')->on('reg_regencies');
            $table->index('regency_id');
        });

        Schema::create('reg_villages', function (Blueprint $table) {
            $table->char('id', 10)->primary();
            $table->char('district_id', 6);
            $table->string('name');

            $table->foreign('district_id')->references('id')->on('reg_districts');
            $table->index('district_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_villages');
        Schema::dropIfExists('reg_districts');
        Schema::dropIfExists('reg_regencies');
        Schema::dropIfExists('reg_provinces');
    }
};

