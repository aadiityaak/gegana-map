<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['jibom_incidents', 'kbrn_incidents', 'wan_teror_incidents'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('news_source', 20)->default('offline')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['jibom_incidents', 'kbrn_incidents', 'wan_teror_incidents'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('news_source', 10)->default('offline')->change();
            });
        }
    }
};
