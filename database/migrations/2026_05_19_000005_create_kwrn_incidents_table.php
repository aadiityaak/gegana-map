<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kwrn_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_type');
            $table->string('finding_type')->nullable();
            $table->longText('description')->nullable();
            $table->json('photos')->nullable();
            $table->char('province_id', 2);
            $table->char('regency_id', 4);
            $table->char('district_id', 6);
            $table->char('village_id', 10);
            $table->timestamps();

            $table->foreign('province_id')->references('id')->on('reg_provinces');
            $table->foreign('regency_id')->references('id')->on('reg_regencies');
            $table->foreign('district_id')->references('id')->on('reg_districts');
            $table->foreign('village_id')->references('id')->on('reg_villages');

            $table->index(['incident_type']);
            $table->index(['province_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kwrn_incidents');
    }
};

