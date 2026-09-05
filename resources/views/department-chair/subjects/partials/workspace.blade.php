@php
    $semesterOptions = $semesters ?? ['First Semester', 'Second Semester', 'Summer'];
@endphp

<div class="subject-toolbar super-admin-form-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <form action="{{ route('department-chair.subjects') }}" class="d-flex flex-wrap align-items-end gap-4" method="GET">
        <div>
            <label class="form-label small fw-bold text-uppercase mb-1" for="program-filter">View Program</label>
            <select class="form-select program-filter-select" id="program-filter" name="program" onchange="this.form.submit()">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->public_id }}" @selected($selectedProgramKey === $program->public_id)>
                        {{ $program->program_name }} - {{ $program->department?->dept_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-bold text-uppercase mb-1" for="year-level-filter">Year Level</label>
            <select class="form-select compact-filter-select" id="year-level-filter" name="year_level" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                @foreach ([1, 2, 3, 4] as $yearLevel)
                    <option value="{{ $yearLevel }}" @selected($selectedYearLevel === $yearLevel)>Year {{ $yearLevel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-bold text-uppercase mb-1" for="semester-filter">Semester</label>
            <select class="form-select compact-filter-select" id="semester-filter" name="semester" onchange="this.form.submit()">
                <option value="">All Semesters</option>
                @foreach ($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
        </div>
        <noscript>
            <button class="btn btn-outline-primary" type="submit">View</button>
        </noscript>
    </form>

    <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button" @disabled($programs->isEmpty())>
        <span class="material-symbols-outlined fs-5">add</span>
        Add Subject
    </button>
</div>

<section class="directory-card subject-directory-card shadow-sm">
    <div class="directory-header px-4 py-3">
        <h3 class="h4 mb-0">Subjects by Program</h3>
    </div>

    <div class="table-responsive subjects-table-wrap">
        <table class="table table-hover mb-0 subjects-table compact-data-table">
            <colgroup>
                <col style="width: 27%;">
                <col style="width: 31%;">
                <col style="width: 11%;">
                <col style="width: 13%;">
                <col style="width: 8%;">
                <col style="width: 10%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Program</th>
                    <th class="text-center">Year Level</th>
                    <th>Semester</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    @php
                        $subjectSemester = $subject->semester?->semester_name ?? 'Not assigned';
                        $subjectIsActive = $subject->is_active && $activeSemester === $subjectSemester;
                    @endphp
                    <tr>
                        <td class="fw-bold subject-primary-cell subject-name-cell" data-label="Subject Name" style="color: var(--psu-navy);">
                            <div>{{ $subject->subject_name }}</div>
                            <div class="small text-secondary fw-semibold mt-1">{{ $subject->subject_code }}</div>
                        </td>
                        <td class="subject-program-cell" data-label="Program">
                            <div class="fw-semibold">{{ $subject->program?->program_name ?? 'Not assigned' }}</div>
                            <div class="small text-secondary">
                                {{ collect([$subject->program?->department?->dept_name, $subject->program?->department?->college?->college_name])->filter()->join(' - ') ?: 'No department' }}
                            </div>
                        </td>
                        <td class="text-center" data-label="Year Level">{{ $subject->year_level }}</td>
                        <td data-label="Semester">{{ $subjectSemester }}</td>
                        <td class="text-center" data-label="Status">
                            <span class="badge {{ $subjectIsActive ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                {{ $subjectIsActive ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-center" data-label="Actions">
                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewSubjectModal{{ $subject->subject_id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $subject->subject_name }}">
                                <span class="material-symbols-outlined fs-6">visibility</span>
                                View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center py-5 subject-empty-cell" colspan="6">
                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">menu_book</span></div>
                            <h4 class="h4" style="color: var(--psu-navy);">
                                {{ $hasSubjectFilters ? 'No subjects match these filters' : 'No subject mappings yet' }}
                            </h4>
                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button" @disabled($programs->isEmpty())>
                                <span class="material-symbols-outlined fs-5">add</span>
                                Add First Subject
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-4 py-3 border-top" style="background: #eff4ff;">
        <span class="small text-secondary">Showing {{ $subjects->count() }} {{ $subjects->count() === 1 ? 'entry' : 'entries' }}</span>
    </div>
</section>
