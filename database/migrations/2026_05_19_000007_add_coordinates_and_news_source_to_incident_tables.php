<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jibom_incidents', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('news_source', 10)->default('offline');
            $table->string('news_url', 2048)->nullable();
        });

        Schema::table('kwrn_incidents', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('news_source', 10)->default('offline');
            $table->string('news_url', 2048)->nullable();
        });

        Schema::table('wan_teror_incidents', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('news_source', 10)->default('offline');
            $table->string('news_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('jibom_incidents', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'news_source', 'news_url']);
        });

        Schema::table('kwrn_incidents', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'news_source', 'news_url']);
        });

        Schema::table('wan_teror_incidents', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'news_source', 'news_url']);
        });
    }
};
