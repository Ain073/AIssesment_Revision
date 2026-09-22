<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 pb-4 mb-4 border-bottom">
    <div>
        <div class="small fw-bold text-secondary text-uppercase mb-2">Active Semester</div>
        <div class="d-flex align-items-center gap-2" style="color: var(--psu-navy);">
            <span class="material-symbols-outlined">calendar_month</span>
            <span class="h5 fw-bold mb-0">{{ $activeSemester?->semester_name ?? 'No active semester' }}</span>
        </div>
    </div>

    <form action="{{ route('department-chair.subjects.semester.activate') }}" class="d-flex flex-wrap align-items-end gap-2" method="POST" data-ajax-form>
        @csrf
        <div>
            <label class="form-label fw-bold text-uppercase small" for="active_semester_name">Semester</label>
            <select class="form-select form-select-lg" id="active_semester_name" name="semester_name" required>
                @foreach ($semesters as $semester)
                    <option value="{{ $semester->semester_name }}" @selected($activeSemester?->semester_id === $semester->semester_id)>
                        {{ $semester->semester_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-psu btn-lg d-inline-flex align-items-center gap-2" type="submit" @disabled($semesters->isEmpty())>
            <span class="material-symbols-outlined fs-5">check_circle</span>
            Activate
        </button>
    </form>
</div>

<div class="subject-toolbar super-admin-form-toolbar d-flex flex-wrap align-items-end gap-3 mb-4">
    <form action="{{ route('department-chair.subjects') }}" class="subject-filter-form d-flex flex-wrap align-items-end gap-3" method="GET">
        <button class="btn btn-psu d-flex align-items-center gap-2 subject-toolbar-action" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment)>
            <span class="material-symbols-outlined fs-5">add</span>
            Subject
        </button>
    </form>
</div>

<section class="directory-card subject-directory-card shadow-sm">
    <div class="directory-header px-4 py-3">
        <h3 class="h4 mb-0">Subjects List</h3>
    </div>

    <div class="table-responsive subjects-table-wrap">
        <table class="table table-hover mb-0 subjects-table compact-data-table">
            <colgroup>
                <col style="width: 48%;">
                <col style="width: 34%;">
                <col style="width: 10%;">
                <col style="width: 8%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Department</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    @php
                        $subjectIsActive = $subject->is_active;
                    @endphp
                    <tr>
                        <td class="fw-bold subject-primary-cell subject-name-cell" data-label="Subject Name" style="color: var(--psu-navy);">
                            <div>{{ $subject->subject_name }}</div>
                            <div class="small text-secondary fw-semibold mt-1">{{ $subject->subject_code }}</div>
                        </td>
                        <td class="subject-program-cell" data-label="Department">
                            <div class="fw-semibold">{{ $subject->department?->dept_name ?? $subject->program?->department?->dept_name ?? 'Not assigned' }}</div>
                            <div class="small text-secondary">
                                {{ $subject->department?->college?->college_name ?? $subject->program?->department?->college?->college_name ?? 'No college' }}
                            </div>
                        </td>
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
                        <td class="text-center py-5 subject-empty-cell" colspan="4">
                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">menu_book</span></div>
                            <h4 class="h4" style="color: var(--psu-navy);">
                                {{ $hasSubjectFilters ? 'No subjects match these filters' : 'No subject mappings yet' }}
                            </h4>
                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment)>
                                <span class="material-symbols-outlined fs-5">add</span>
                                Subject
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
