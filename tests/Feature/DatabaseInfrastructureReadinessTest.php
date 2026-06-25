<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseInfrastructureReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backed_framework_infrastructure_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }
}
