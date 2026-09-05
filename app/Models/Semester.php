<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public static function activeOrDefault(): self
    {
        $semester = self::query()
            ->where('is_active', true)
            ->first()
            ?? self::query()
                ->orderBy('semester_id')
                ->first();

        if ($semester) {
            return $semester;
        }

        return self::query()->create([
            'semester_name' => self::SEMESTERS[0],
            'is_active' => true,
        ]);
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

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'semester_id', 'semester_id');
    }
}
