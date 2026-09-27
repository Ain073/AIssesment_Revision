<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $programs = DB::table('programs')->get();

        foreach ($programs as $program) {
            $lower = strtolower($program->program_name);

            // 1. Social Studies
            if (str_contains($lower, 'social studies')) {
                $this->normalizeSections($program->program_id, '/^BSED\s+([0-9]-[A-Za-z])$/i', 'BSED SS ');
            }
            // 2. Filipino
            elseif (str_contains($lower, 'filipino')) {
                $this->normalizeSections($program->program_id, '/^BSED\s+([0-9]-[A-Za-z])$/i', 'BSED FIL ');
            }
            // 3. English
            elseif (str_contains($lower, 'english')) {
                $this->normalizeSections($program->program_id, '/^BSED\s+([0-9]-[A-Za-z])$/i', 'BSED ENG ');
            }
            // 4. Mathematics
            elseif (str_contains($lower, 'math')) {
                $this->normalizeSections($program->program_id, '/^BSED\s+([0-9]-[A-Za-z])$/i', 'BSED MATH ');
            }
            // 5. Science
            elseif (str_contains($lower, 'science')) {
                $this->normalizeSections($program->program_id, '/^BSED\s+([0-9]-[A-Za-z])$/i', 'BSED SCI ');
            }
            // 6. Marketing Management
            elseif (str_contains($lower, 'marketing')) {
                $this->normalizeSections($program->program_id, '/^BSBA\s+([0-9]-[A-Za-z])$/i', 'BSBA MM ');
            }
            // 7. Financial Management
            elseif (str_contains($lower, 'financial')) {
                $this->normalizeSections($program->program_id, '/^BSBA\s+([0-9]-[A-Za-z])$/i', 'BSBA FM ');
            }
            // 8. Human Resources Development Management
            elseif (str_contains($lower, 'development') || str_contains($lower, 'hrdm')) {
                $this->normalizeSections($program->program_id, '/^BSBA\s+([0-9]-[A-Za-z])$/i', 'BSBA HRDM ');
            }
            // 9. Home Economics
            elseif (str_contains($lower, 'home economics')) {
                $this->normalizeSections($program->program_id, '/^BTLED\s+([0-9]-[A-Za-z])$/i', 'BTLED HE ');
            }
            // 9. Industrial Arts
            elseif (str_contains($lower, 'industrial arts')) {
                $this->normalizeSections($program->program_id, '/^BTLED\s+([0-9]-[A-Za-z])$/i', 'BTLED IA ');
            }
        }
    }

    private function normalizeSections(int $programId, string $pattern, string $newPrefix): void
    {
        DB::table('classes')
            ->where('program_id', $programId)
            ->get()
            ->each(function ($class) use ($pattern, $newPrefix) {
                if (preg_match($pattern, trim($class->section_name), $matches)) {
                    DB::table('classes')
                        ->where('class_id', $class->class_id)
                        ->update([
                            'section_name' => $newPrefix . strtoupper($matches[1]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // No reversal needed for data normalization
    }
};
