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
        if (config('offline.enabled')) {
            $this->call([
                OfflineCompanySeeder::class,
            ]);

            return;
        }

        $this->call([
            SuperAdminSeeder::class,
        ]);
    }
}
