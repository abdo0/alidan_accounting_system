<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\Shh\ShhReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * The SHH-01 reference data (chart, dimensions, parameters, roles) is seeded once
     * when the test database is rebuilt; each test then runs inside a transaction.
     */
    protected bool $seed = true;

    protected string $seeder = ShhReferenceSeeder::class;
}
