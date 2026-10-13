<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed required accounts, roles, and missing public IDs.
     */
    public function run(): void
    {
        $this->call(RoleAndSuperAdminSeeder::class);
        $this->call(PublicIdSeeder::class);
    }
}
