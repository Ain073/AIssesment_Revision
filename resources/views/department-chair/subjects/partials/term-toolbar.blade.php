@php
    $semesterOptions = $semesters ?? ['First Semester', 'Second Semester', 'Summer'];
@endphp

<div class="academic-term-toolbar super-admin-form-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <div class="small fw-bold text-secondary text-uppercase mb-1">Active Semester</div>
        <div class="active-term-value d-flex align-items-center gap-2">
            <span class="material-symbols-outlined">calendar_month</span>
            {{ $activeSemester ?? 'Not set' }}
        </div>
    </div>

    <form
        action="{{ route('department-chair.subjects.semester.activate', array_filter(['program' => $selectedProgramKey, 'year_level' => $selectedYearLevel, 'semester' => $selectedSemester])) }}"
        class="d-flex flex-wrap align-items-end gap-2"
        method="POST"
        data-ajax-form
    >
        @csrf
        <div>
            <label class="form-label small fw-bold text-uppercase mb-1" for="active-semester">Semester</label>
            <select class="form-select compact-filter-select" id="active-semester" name="semester" required @disabled(! $scopedDepartment)>
                @foreach ($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($activeSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit" @disabled(! $scopedDepartment)>
            <span class="material-symbols-outlined fs-5">check_circle</span>
            Activate
        </button>
    </form>
</div>
