@php
    $programAcronym = function (?string $programName): string {
        $programName = trim((string) $programName);

        if ($programName === '') {
            return 'Section';
        }

        $words = preg_split('/\s+/', strtolower($programName)) ?: [];
        $stopWords = ['of', 'in', 'and', 'the'];
        $acronym = collect($words)
            ->reject(fn ($word) => in_array($word, $stopWords, true))
            ->map(fn ($word) => strtoupper(substr(preg_replace('/[^a-z0-9]/', '', $word), 0, 1)))
            ->filter()
            ->join('');

        return $acronym !== '' ? $acronym : 'Section';
    };
    $selectedSemesterId = old('semester_id', $activeSemesterId);
    $selectedSemesterName = $semesters->firstWhere('semester_id', (int) $selectedSemesterId)?->semester_name
        ?? $activeSemesterName
        ?? 'No active semester';
@endphp

<div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered class-form-dialog">
        <form action="{{ route('instructor.classes.store') }}" class="modal-content" method="POST" autocomplete="off" data-ajax-form data-reset-on-success="true">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title h4" id="classModalLabel">New Class</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-12 class-subject-field">
                        <label class="form-label fw-bold text-uppercase small" for="subject_id">Subject</label>
                        <select class="form-select form-select-lg" id="subject_id" name="subject_id" required>
                            <option value="">Select subject</option>
                            @foreach ($activeSubjects as $subject)
                                <option value="{{ $subject->subject_id }}" @selected(old('subject_id') == $subject->subject_id)>
                                    {{ $subject->subject_code }} - {{ $subject->subject_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-uppercase small" for="program_id">Program</label>
                        <select class="form-select form-select-lg" id="program_id" name="program_id" required data-class-program-select>
                            <option value="">Select program</option>
                            @foreach ($activePrograms as $program)
                                <option value="{{ $program->program_id }}" data-section-prefix="{{ $programAcronym($program->program_name) }}" @selected(old('program_id') == $program->program_id)>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 class-semester-field">
                        <label class="form-label fw-bold text-uppercase small" for="semester_display">Semester</label>
                        <input id="semester_id" name="semester_id" type="hidden" value="{{ $selectedSemesterId }}">
                        <input class="form-control form-control-lg class-readonly-field" id="semester_display" type="text" value="{{ $selectedSemesterName }}" readonly tabindex="-1">
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-uppercase small" for="year_level">Year Level</label>
                        <select class="form-select form-select-lg" id="year_level" name="year_level" required>
                            @foreach ([1, 2, 3, 4] as $yearLevel)
                                <option value="{{ $yearLevel }}" @selected((string) old('year_level', '1') === (string) $yearLevel)>{{ \App\Support\YearLevel::label($yearLevel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="section_name">Section Name</label>
                        <select class="form-select form-select-lg" id="section_name" name="section_name" required data-class-section-select data-selected-section="{{ old('section_name') }}">
                            <option value="">Select program first</option>
                        </select>
                    </div>
                    <div class="col-md-3 class-school-year-field">
                        <label class="form-label fw-bold text-uppercase small" for="school_year_start">Academic Year</label>
                        <div class="school-year-fields" data-school-year-pair>
                            <span class="school-year-prefix">20</span>
                            <input class="form-control form-control-lg text-center school-year-part" id="school_year_start" name="school_year_start" inputmode="numeric" maxlength="2" pattern="\d{2}" required type="text" value="{{ old('school_year_start') }}" aria-label="Academic year start">
                            <span class="school-year-separator">-</span>
                            <span class="school-year-prefix">20</span>
                            <input class="form-control form-control-lg text-center school-year-part" name="school_year_end" inputmode="numeric" maxlength="2" pattern="\d{2}" required type="text" value="{{ old('school_year_end') }}" aria-label="Academic year end">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit">Create Class</button>
            </div>
        </form>
    </div>
</div>
