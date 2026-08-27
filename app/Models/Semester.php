<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Semester extends Model
{
    public const SEMESTERS = [
        'First Semester',
        'Second Semester',
        'Summer',
    ];

    protected $primaryKey = 'semester_id';

    protected $fillable = [
        'semester_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function names(): array
    {
        return self::SEMESTERS;
    }

    public static function activeName(): ?string
    {
        return self::query()
            ->where('is_active', true)
            ->value('semester_name');
    }

    public static function activate(string $semesterName): self
    {
        return DB::transaction(function () use ($semesterName): self {
            self::query()->update(['is_active' => false]);

            return self::query()->updateOrCreate(
                ['semester_name' => $semesterName],
                ['is_active' => true],
            );
        });
    }
}
