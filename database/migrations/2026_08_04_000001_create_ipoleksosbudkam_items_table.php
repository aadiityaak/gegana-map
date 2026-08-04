<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipoleksosbudkam_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->date('incident_date')->nullable();
            $table->string('severity_level', 20)->default('low');
            $table->string('status', 20)->default('active');
            $table->string('category', 100)->nullable();
            $table->string('sub_category', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->string('kabupaten_kota', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->integer('jumlah_terdampak')->nullable();
            $table->string('source')->nullable();
            $table->string('sumber_berita', 2048)->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('sub_category');
            $table->index('incident_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipoleksosbudkam_items');
    }
};
