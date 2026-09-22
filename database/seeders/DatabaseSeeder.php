<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Shh\ShhReferenceSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ShhReferenceSeeder::class);

        if (! app()->isProduction()) {
            $this->call(AdminUserSeeder::class);
        }
    }
}
