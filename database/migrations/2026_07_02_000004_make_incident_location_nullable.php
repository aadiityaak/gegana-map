<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['jibom_incidents', 'kbrn_incidents', 'wan_teror_incidents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $t) use ($tableName) {
                $this->safeDropForeign($t, "{$tableName}_province_id_foreign");
                $this->safeDropForeign($t, "{$tableName}_regency_id_foreign");
                $this->safeDropForeign($t, "{$tableName}_district_id_foreign");
                $this->safeDropForeign($t, "{$tableName}_village_id_foreign");

                $t->string('province_id', 2)->nullable()->change();
                $t->string('regency_id', 4)->nullable()->change();
                $t->string('district_id', 6)->nullable()->change();
                $t->string('village_id', 10)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['jibom_incidents', 'kbrn_incidents', 'wan_teror_incidents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $t) use ($tableName) {
                $t->string('province_id', 2)->nullable(false)->change();
                $t->string('regency_id', 4)->nullable(false)->change();
                $t->string('district_id', 6)->nullable(false)->change();
                $t->string('village_id', 10)->nullable(false)->change();

                $t->foreign('province_id')->references('id')->on('reg_provinces');
                $t->foreign('regency_id')->references('id')->on('reg_regencies');
                $t->foreign('district_id')->references('id')->on('reg_districts');
                $t->foreign('village_id')->references('id')->on('reg_villages');
            });
        }
    }

    private function safeDropForeign(Blueprint $t, string $name): void
    {
        try {
            $t->dropForeign($name);
        } catch (\Throwable) {
        }
    }
};
