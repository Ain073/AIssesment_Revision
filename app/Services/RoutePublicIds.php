<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class RoutePublicIds
{
    private const INDEX_WAIT_ATTEMPTS = 150;

    public const TABLES = [
        'subjects' => 'subject_id',
        'class_details' => 'class_details_id',
        'assessment_items' => 'assessment_item_id',
        'submissions' => 'submission_id',
    ];

    /** Fill only missing URL identifiers, without model events or timestamp changes. */
    public function fillMissing(): array
    {
        $counts = [];
        foreach (self::TABLES as $table => $primaryKey) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'public_id')) {
                throw new LogicException('Run the public ID migration before backfilling '.$table.'.');
            }

            $counts[$table] = 0;
            DB::table($table)
                ->where(fn ($query) => $query->whereNull('public_id')->orWhere('public_id', ''))
                ->select($primaryKey)
                ->chunkById(500, function ($rows) use ($table, $primaryKey, &$counts): void {
                    $counts[$table] += DB::transaction(function () use ($rows, $table, $primaryKey): int {
                        $filled = 0;
                        foreach ($rows as $row) {
                            $filled += DB::table($table)->where($primaryKey, $row->{$primaryKey})
                                ->where(fn ($query) => $query->whereNull('public_id')->orWhere('public_id', ''))
                                ->update(['public_id' => (string) Str::uuid()]);
                        }

                        return $filled;
                    });
                }, $primaryKey);
        }

        return $counts;
    }

    /** A TiDB index job can still be running when an interrupted migration is retried. */
    public function ensureUniqueIndex(string $table): void
    {
        if (Schema::hasIndex($table, ['public_id'], 'unique')) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique('public_id'));
        } catch (QueryException $exception) {
            // TiDB reports 1061 if another DDL job is already creating this index.
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1061) {
                throw $exception;
            }
        }

        for ($attempt = 0; $attempt <= self::INDEX_WAIT_ATTEMPTS; $attempt++) {
            if (Schema::hasIndex($table, ['public_id'], 'unique')) {
                return;
            }

            if ($attempt < self::INDEX_WAIT_ATTEMPTS) {
                sleep(2);
            }
        }

        throw new RuntimeException('The unique public ID index on '.$table.' did not become available after five minutes.');
    }
}
