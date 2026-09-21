<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpan statistik (tren bulanan, area rawan, distribusi kategori, KPI)
     * hasil hitungan server, supaya grafik bisa digambar ulang kapan saja
     * dari riwayat analisa tanpa memanggil AI lagi.
     */
    public function up(): void
    {
        Schema::table('ai_analysis_history', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_analysis_history', 'stats')) {
                $table->json('stats')->nullable()->after('result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_history', function (Blueprint $table) {
            if (Schema::hasColumn('ai_analysis_history', 'stats')) {
                $table->dropColumn('stats');
            }
        });
    }
};
