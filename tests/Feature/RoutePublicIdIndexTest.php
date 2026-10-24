<?php

namespace Tests\Feature;

use App\Services\RoutePublicIds;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDOException;
use Tests\TestCase;

class RoutePublicIdIndexTest extends TestCase
{
    public function test_duplicate_index_job_is_accepted_only_after_the_unique_index_is_visible(): void
    {
        Schema::shouldReceive('hasIndex')
            ->times(3)
            ->with('class_details', ['public_id'], 'unique')
            ->andReturn(false, false, true);
        Schema::shouldReceive('table')
            ->once()
            ->with('class_details', Mockery::type('Closure'))
            ->andThrow($this->indexError(1061));

        app(RoutePublicIds::class)->ensureUniqueIndex('class_details');
    }

    public function test_an_existing_unique_index_needs_no_ddl(): void
    {
        Schema::shouldReceive('hasIndex')
            ->once()
            ->with('class_details', ['public_id'], 'unique')
            ->andReturn(true);
        Schema::shouldReceive('table')->never();

        app(RoutePublicIds::class)->ensureUniqueIndex('class_details');
    }

    public function test_other_index_errors_are_not_ignored(): void
    {
        Schema::shouldReceive('hasIndex')
            ->once()
            ->with('class_details', ['public_id'], 'unique')
            ->andReturn(false);
        Schema::shouldReceive('table')
            ->once()
            ->andThrow($this->indexError(1062));

        $this->expectException(QueryException::class);

        app(RoutePublicIds::class)->ensureUniqueIndex('class_details');
    }

    private function indexError(int $code): QueryException
    {
        $cause = new PDOException('Index creation failed.', 42000);
        $cause->errorInfo = ['42000', $code, 'Index creation failed.'];

        return new QueryException('mysql', 'alter table `class_details` add unique', [], $cause);
    }
}
