<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUsersSeeder::class);
        $this->call(WilayahIndonesiaSeeder::class);
        $this->call(JibomIncidentsSeeder::class);
        $this->call(KwrnIncidentsSeeder::class);
        $this->call(WanTerorIncidentsSeeder::class);
    }
}
