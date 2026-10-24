<?php

namespace Tests\Feature;

use App\Models\AssessmentItem;
use App\Models\ClassDetail;
use App\Models\Subject;
use App\Models\Submission;
use App\Services\RoutePublicIds;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoutePublicIdMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (RoutePublicIds::TABLES as $table => $key) {
            $publicId = $table === 'subjects' ? ', public_id TEXT' : '';
            DB::statement("CREATE TABLE {$table} ({$key} INTEGER PRIMARY KEY, marker TEXT, created_at DATETIME, updated_at DATETIME{$publicId})");
        }
        DB::statement('CREATE TABLE audit_logs (audit_log_id INTEGER PRIMARY KEY, description TEXT)');
        DB::table('audit_logs')->insert(['audit_log_id' => 9, 'description' => 'Original history']);
    }

    public function test_backfill_is_idempotent_preserves_all_other_fields_and_covers_multiple_chunks(): void
    {
        $existing = (string) Str::uuid();
        foreach (RoutePublicIds::TABLES as $table => $key) {
            $rows = [];
            foreach (range(1, $table === 'subjects' ? 503 : 2) as $id) {
                $row = [$key => $id, 'marker' => "Original {$table} {$id}", 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-02-03 04:05:06'];
                if ($table === 'subjects') $row['public_id'] = $id === 1 ? $existing : ($id === 2 ? '' : null);
                $rows[] = $row;
            }
            DB::table($table)->insert($rows);
        }
        $before = $this->businessState();
        $this->migration()->up();
        $ids = [];
        foreach (RoutePublicIds::TABLES as $table => $key) {
            $values = DB::table($table)->orderBy($key)->pluck('public_id')->all();
            $this->assertTrue(Schema::hasIndex($table, ['public_id'], 'unique'));
            $this->assertCount(count($values), array_unique($values));
            foreach ($values as $value) $this->assertTrue(Str::isUuid($value));
            $ids[$table] = $values;
        }
        $this->assertSame($existing, DB::table('subjects')->where('subject_id', 1)->value('public_id'));
        $this->migration()->up();
        $this->migration()->down();
        foreach (RoutePublicIds::TABLES as $table => $key) {
            $this->assertSame($ids[$table], DB::table($table)->orderBy($key)->pluck('public_id')->all());
        }
        $this->assertSame($before, $this->businessState());
        $this->assertSame(['Original history'], DB::table('audit_logs')->pluck('description')->all());
    }

    public function test_failed_chunk_rolls_back_and_can_be_retried_without_changing_business_data(): void
    {
        DB::table('subjects')->insert([
            ['subject_id' => 1, 'marker' => 'First'], ['subject_id' => 2, 'marker' => 'Second'],
        ]);
        $before = $this->businessState();
        DB::statement("CREATE TRIGGER reject_public_id BEFORE UPDATE ON subjects WHEN OLD.subject_id = 2 BEGIN SELECT RAISE(ABORT, 'Simulated backfill failure'); END");
        try {
            $this->migration()->up();
            $this->fail('A failed backfill must not silently succeed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated backfill failure', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('subjects')->whereNotNull('public_id')->count());
        $this->assertSame($before, $this->businessState());
        DB::statement('DROP TRIGGER reject_public_id');
        $this->migration()->up();
        $this->assertSame(2, DB::table('subjects')->whereNotNull('public_id')->count());
        $this->assertSame($before, $this->businessState());
    }

    public function test_cutover_command_fills_late_legacy_rows_once_and_new_models_generate_their_own_ids(): void
    {
        $this->migration()->up();
        DB::table('submissions')->insert(['submission_id' => 12, 'marker' => 'Late legacy write', 'updated_at' => '2026-01-01 00:00:00']);
        $before = $this->businessState();
        $this->artisan('public-ids:backfill')->assertSuccessful();
        $first = DB::table('submissions')->where('submission_id', 12)->value('public_id');
        $this->assertTrue(Str::isUuid($first));
        $this->artisan('public-ids:backfill')->assertSuccessful();
        $this->assertSame($first, DB::table('submissions')->where('submission_id', 12)->value('public_id'));
        $this->assertSame($before, $this->businessState());
        foreach ([Subject::class, ClassDetail::class, AssessmentItem::class, Submission::class] as $modelClass) {
            $model = new $modelClass;
            $model->forceFill(['marker' => 'New record'])->save();
            $this->assertTrue(Str::isUuid($model->public_id));
            $this->assertSame($model->public_id, $model->getRouteKey());
            $this->assertSame('public_id', $model->getRouteKeyName());
        }
        $this->assertSame(['Original history'], DB::table('audit_logs')->pluck('description')->all());
    }

    public function test_unique_index_rejects_duplicate_public_ids(): void
    {
        $this->migration()->up();
        $id = (string) Str::uuid();
        DB::table('submissions')->insert(['submission_id' => 1, 'public_id' => $id]);
        $this->expectException(QueryException::class);
        DB::table('submissions')->insert(['submission_id' => 2, 'public_id' => $id]);
    }

    private function businessState(): array
    {
        $state = [];
        foreach (RoutePublicIds::TABLES as $table => $key) {
            $state[$table] = DB::table($table)->orderBy($key)->get([$key, 'marker', 'created_at', 'updated_at'])->toJson();
        }

        return $state;
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_000002_add_public_ids_to_remaining_route_models.php');
    }
}
