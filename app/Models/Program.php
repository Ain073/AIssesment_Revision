<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory, UsesPublicId;

    protected $primaryKey = 'program_id';

    protected $fillable = [
        'department_id',
        'program_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'program_id', 'program_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'program_id', 'program_id');
    }

    public function sectionPrefix(): string
    {
        return self::formatAcronym($this->program_name);
    }

    public static function formatAcronym(?string $programName): string
    {
        $programName = trim((string) $programName);

        if ($programName === '') {
            return 'Section';
        }

        $lower = strtolower($programName);

        if (str_contains($lower, 'business administration') && str_contains($lower, 'marketing')) {
            return 'BSBA MM';
        }
        if (str_contains($lower, 'business administration') && str_contains($lower, 'financial')) {
            return 'BSBA FM';
        }
        if (str_contains($lower, 'business administration') && (str_contains($lower, 'human resource') || str_contains($lower, 'operations'))) {
            return 'BSBA HRM';
        }
        if (str_contains($lower, 'information technology')) {
            return 'BSIT';
        }
        if (str_contains($lower, 'hospitality management')) {
            return 'BSHM';
        }
        if (str_contains($lower, 'elementary education')) {
            return 'BEED';
        }
        if (str_contains($lower, 'secondary education')) {
            return 'BSED';
        }
        if (str_contains($lower, 'technology and livelihood') || str_contains($lower, 'livelihood education')) {
            return 'BTLED';
        }
        if (str_contains($lower, 'public administration')) {
            return 'BPA';
        }
        if (str_contains($lower, 'office administration')) {
            return 'BSOA';
        }
        if (str_contains($lower, 'agriculture')) {
            return 'BSA';
        }

        if (str_contains($lower, 'major in')) {
            [$base, $major] = explode('major in', $lower, 2);
            $baseAcronym = self::buildInitials($base);
            $majorAcronym = self::buildInitials($major);

            return trim("{$baseAcronym} {$majorAcronym}");
        }

        return self::buildInitials($programName) ?: 'Section';
    }

    private static function buildInitials(string $text): string
    {
        $words = preg_split('/\s+/', strtolower($text)) ?: [];
        $stopWords = ['of', 'in', 'and', 'the', 'for'];

        return collect($words)
            ->reject(fn ($word) => in_array($word, $stopWords, true))
            ->map(fn ($word) => strtoupper(substr(preg_replace('/[^a-z0-9]/', '', $word), 0, 1)))
            ->filter()
            ->join('');
    }
}

