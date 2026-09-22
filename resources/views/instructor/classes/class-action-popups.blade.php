@php
    $classDefaultStartYear = now()->month >= 6 ? now()->year : now()->year - 1;
    $classDefaultStartYearShort = substr((string) $classDefaultStartYear, -2);
    $classDefaultEndYearShort = substr((string) ($classDefaultStartYear + 1), -2);
    $schoolYearParts = function (?string $schoolYear) use ($classDefaultStartYearShort, $classDefaultEndYearShort): array {
        if (preg_match('/^20(\d{2})-20(\d{2})$/', (string) $schoolYear, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [$classDefaultStartYearShort, $classDefaultEndYearShort];
    };
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
@endphp

@foreach ($classes as $class)
    @if ($activeClassTab === 'active')
        @php
            [$schoolYearStart, $schoolYearEnd] = $schoolYearParts($class->school_year);
            $classSemesterId = $class->semester_id ?? $activeSemesterId;
            $classSemesterName = $class->semester?->semester_name
                ?? $semesters->firstWhere('semester_id', (int) $classSemesterId)?->semester_name
                ?? $activeSemesterName
                ?? 'No active semester';
        @endphp
        <div class="modal fade" id="editClassModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="editClassModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered class-form-dialog">
                <form action="{{ route('instructor.classes.update', $class) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="editClassModalLabel{{ $class->class_id }}">Edit Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-12 class-subject-field">
                                <label class="form-label fw-bold text-uppercase small" for="edit_subject_id_{{ $class->class_id }}">Subject</label>
                                <select class="form-select form-select-lg" id="edit_subject_id_{{ $class->class_id }}" name="subject_id" required>
                                    <option value="">Select subject</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->subject_id }}" @selected($class->subject_id == $subject->subject_id)>
                                            {{ $subject->subject_code }} - {{ $subject->subject_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-uppercase small" for="edit_program_id_{{ $class->class_id }}">Program</label>
                                <select class="form-select form-select-lg" id="edit_program_id_{{ $class->class_id }}" name="program_id" required data-class-program-select>
                                    <option value="">Select program</option>
                                    @foreach ($programs as $program)
                                        <option value="{{ $program->program_id }}" data-section-prefix="{{ $programAcronym($program->program_name) }}" @selected((int) ($class->program_id ?? $class->subject?->program_id) === (int) $program->program_id)>
                                            {{ $program->program_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 class-semester-field">
                                <label class="form-label fw-bold text-uppercase small" for="edit_semester_display_{{ $class->class_id }}">Semester</label>
                                <input name="semester_id" type="hidden" value="{{ $classSemesterId }}">
                                <input class="form-control form-control-lg class-readonly-field" id="edit_semester_display_{{ $class->class_id }}" type="text" value="{{ $classSemesterName }}" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-uppercase small" for="edit_year_level_{{ $class->class_id }}">Year Level</label>
                                <select class="form-select form-select-lg" id="edit_year_level_{{ $class->class_id }}" name="year_level" required>
                                    @foreach ([1, 2, 3, 4] as $yearLevel)
                                        <option value="{{ $yearLevel }}" @selected((int) $class->year_level === $yearLevel)>{{ \App\Support\YearLevel::label($yearLevel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_section_name_{{ $class->class_id }}">Section Name</label>
                                <select class="form-select form-select-lg" id="edit_section_name_{{ $class->class_id }}" name="section_name" required data-class-section-select data-selected-section="{{ $class->section_name ?? $class->class_name }}">
                                    <option value="">Select section</option>
                                </select>
                            </div>
                            <div class="col-md-3 class-school-year-field">
                                <label class="form-label fw-bold text-uppercase small" for="edit_school_year_start_{{ $class->class_id }}">Academic Year</label>
                                <div class="school-year-fields" data-school-year-pair>
                                    <span class="school-year-prefix">20</span>
                                    <input class="form-control form-control-lg text-center school-year-part" id="edit_school_year_start_{{ $class->class_id }}" name="school_year_start" inputmode="numeric" maxlength="2" pattern="\d{2}" required type="text" value="{{ $schoolYearStart }}" aria-label="Academic year start">
                                    <span class="school-year-separator">-</span>
                                    <span class="school-year-prefix">20</span>
                                    <input class="form-control form-control-lg text-center school-year-part" name="school_year_end" inputmode="numeric" maxlength="2" pattern="\d{2}" required type="text" value="{{ $schoolYearEnd }}" aria-label="Academic year end">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="archiveClassModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="archiveClassModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('instructor.classes.archive', $class) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="archiveClassModalLabel{{ $class->class_id }}">Archive Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $class->displayName() }}</p>
                        <p class="text-secondary mb-0">This will move the class to archived records. It will no longer appear in active class lists or publishing selections.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-psu px-4" type="submit">Archive Class</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="deleteClassModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="deleteClassModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('instructor.classes.destroy', $class) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="deleteClassModalLabel{{ $class->class_id }}">Delete Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $class->displayName() }}</p>
                        <p class="text-secondary mb-0">This will remove the class record, its enrolled student links, join requests, and published assessment publications.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Delete Class</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
