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

        $clean = preg_replace('/[_\-]+/', ' ', $programName);
        $lower = strtolower($clean);

        // 1. Secondary Education with Majors
        if (str_contains($lower, 'secondary education') || preg_match('/\bbsed\b/i', $clean)) {
            if (str_contains($lower, 'social studies') || preg_match('/\bss\b/i', $clean)) {
                return 'BSED SS';
            }
            if (str_contains($lower, 'filipino') || preg_match('/\bfil\b/i', $clean)) {
                return 'BSED FIL';
            }
            if (str_contains($lower, 'english') || preg_match('/\beng\b/i', $clean)) {
                return 'BSED ENG';
            }
            if (str_contains($lower, 'math')) {
                return 'BSED MATH';
            }
            if (str_contains($lower, 'science') || preg_match('/\bsci\b/i', $clean)) {
                return 'BSED SCI';
            }
            if (str_contains($lower, 'values')) {
                return 'BSED VE';
            }
            if (str_contains($lower, 'major in')) {
                [$base, $major] = explode('major in', $lower, 2);
                $majorInitials = self::buildInitials($major);
                return trim("BSED {$majorInitials}");
            }
            if (preg_match('/\bbsed\s+([a-z0-9]+)\b/i', $clean, $matches)) {
                return 'BSED ' . strtoupper($matches[1]);
            }
            return 'BSED';
        }

        // 2. Business Administration with Majors
        if (str_contains($lower, 'business administration') || preg_match('/\bbsba\b/i', $clean)) {
            if (str_contains($lower, 'marketing') || preg_match('/\bmm\b/i', $clean)) {
                return 'BSBA MM';
            }
            if (str_contains($lower, 'financial') || preg_match('/\bfm\b/i', $clean)) {
                return 'BSBA FM';
            }
            if (str_contains($lower, 'human resource development') || preg_match('/\bhrdm\b/i', $clean)) {
                return 'BSBA HRDM';
            }
            if (str_contains($lower, 'human resource') || preg_match('/\bhrm\b/i', $clean)) {
                return 'BSBA HRM';
            }
            if (str_contains($lower, 'operations') || preg_match('/\bom\b/i', $clean)) {
                return 'BSBA OM';
            }
            if (str_contains($lower, 'major in')) {
                [$base, $major] = explode('major in', $lower, 2);
                $majorInitials = self::buildInitials($major);
                return trim("BSBA {$majorInitials}");
            }
            if (preg_match('/\bbsba\s+([a-z0-9]+)\b/i', $clean, $matches)) {
                return 'BSBA ' . strtoupper($matches[1]);
            }
            return 'BSBA';
        }

        // 3. Technology and Livelihood Education with Majors
        if (str_contains($lower, 'technology and livelihood') || str_contains($lower, 'livelihood education') || preg_match('/\bbtled\b/i', $clean)) {
            if (str_contains($lower, 'home economics') || preg_match('/\bhe\b/i', $clean)) {
                return 'BTLED HE';
            }
            if (str_contains($lower, 'industrial arts') || preg_match('/\bia\b/i', $clean)) {
                return 'BTLED IA';
            }
            if (str_contains($lower, 'ict') || str_contains($lower, 'information and communication')) {
                return 'BTLED ICT';
            }
            if (str_contains($lower, 'agri')) {
                return 'BTLED AFA';
            }
            if (str_contains($lower, 'major in')) {
                [$base, $major] = explode('major in', $lower, 2);
                $majorInitials = self::buildInitials($major);
                return trim("BTLED {$majorInitials}");
            }
            if (preg_match('/\bbtled\s+([a-z0-9]+)\b/i', $clean, $matches)) {
                return 'BTLED ' . strtoupper($matches[1]);
            }
            return 'BTLED';
        }

        // 4. Elementary & Early Education
        if (str_contains($lower, 'elementary education') || preg_match('/\bbeed\b/i', $clean)) {
            return 'BEED';
        }
        if (str_contains($lower, 'early childhood')) {
            return 'BECEd';
        }
        if (str_contains($lower, 'special needs')) {
            return 'BSNEd';
        }

        // 5. Computing & Other Programs
        if (str_contains($lower, 'information technology') || preg_match('/\bbsit\b/i', $clean)) {
            return 'BSIT';
        }
        if (str_contains($lower, 'computer science') || preg_match('/\bbscs\b/i', $clean)) {
            return 'BSCS';
        }
        if (str_contains($lower, 'hospitality management') || preg_match('/\bbshm\b/i', $clean)) {
            return 'BSHM';
        }
        if (str_contains($lower, 'tourism management') || preg_match('/\bbstm\b/i', $clean)) {
            return 'BSTM';
        }
        if (str_contains($lower, 'office administration') || preg_match('/\bbsoa\b/i', $clean)) {
            return 'BSOA';
        }
        if (str_contains($lower, 'public administration') || preg_match('/\bbpa\b/i', $clean)) {
            return 'BPA';
        }
        if (str_contains($lower, 'agriculture') || preg_match('/\bbsa\b/i', $clean)) {
            return 'BSA';
        }
        if (str_contains($lower, 'criminology') || preg_match('/\bbscrim\b/i', $clean)) {
            return 'BSCrim';
        }
        if (str_contains($lower, 'nursing') || preg_match('/\bbsn\b/i', $clean)) {
            return 'BSN';
        }

        // 6. Generic Fallback with "major in" support
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

