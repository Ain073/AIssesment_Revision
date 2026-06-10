@extends('layouts.portal')

@section('title', 'Classes | AIssessment Instructor')
@section('header', 'Classes')

@push('styles')
    <style>
        .stat-card,
        .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header,
        .modal-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .table thead th {
            background: #edf2ff;
            color: var(--psu-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1rem 1.25rem;
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .class-icon,
        .empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--psu-navy-2);
            background: var(--psu-gold-soft);
        }

        .class-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.25rem;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
        }

        .classes-table {
            min-width: 860px;
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Classes</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalClasses }}</span>
                    <span class="small text-secondary">Classes under your instructor account</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Latest School Year</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $latestSchoolYear ?? 'N/A' }}</span>
                    <span class="small text-secondary">Based on your saved class records</span>
                </div>
            </div>
        </div>
    </div>

    <div class="alert {{ ! $instructorProfile ? 'alert-warning' : ($subjects->isEmpty() ? 'alert-warning' : 'alert-primary') }} border-0 shadow-sm mb-4">
        @if (! $instructorProfile)
            This account does not have an instructor profile yet, so class creation is temporarily unavailable.
        @elseif ($subjects->isEmpty())
            No active subjects are available yet. Add subjects first from the Super Admin portal before creating classes.
        @else
            This follows the ERD class structure. New class records will automatically use your instructor profile and selected subject.
        @endif
    </div>

    <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty())>
            <span class="material-symbols-outlined fs-5">add</span>
            Create Class
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Classes List</h3>
            <span class="small text-white-50">
                {{ $instructorProfile?->department?->dept_name ?? 'No department assigned yet' }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 classes-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Students</th>
                        <th>School Year</th>
                        <th>Department</th>
                        <th>College</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classes as $class)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="class-icon"><span class="material-symbols-outlined">school</span></span>
                                    <div>
                                        <div class="fw-bold" style="color: var(--psu-navy);">{{ $class->class_name }}</div>
                                        <div class="small text-secondary">Class ID: {{ $class->class_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($class->subject)
                                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $class->subject->subject_code }}</div>
                                    <div class="small text-secondary">{{ $class->subject->subject_name }}</div>
                                @else
                                    <span class="text-secondary">Not selected</span>
                                @endif
                            </td>
                            <td>{{ $class->students_count }}</td>
                            <td>{{ $class->school_year }}</td>
                            <td>{{ $class->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</td>
                            <td>{{ $class->instructorProfile?->department?->college?->college_name ?? 'Not assigned' }}</td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" href="{{ route('instructor.classes.show', ['class' => $class, 'tab' => 'students']) }}">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="7">
                                <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">school</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No classes yet</h4>
                                <p class="text-secondary mb-4">Create your first class record based on the ERD fields.</p>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty())>
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Create First Class
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $classes->count() }} {{ $classes->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Handled by {{ $profileName }}</span>
        </div>
    </section>

    <div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('instructor.classes.store') }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="classModalLabel">New Class</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="subject_id">Subject</label>
                        <select class="form-select form-select-lg" id="subject_id" name="subject_id" required>
                            <option value="">Select subject</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->subject_id }}" @selected(old('subject_id') == $subject->subject_id)>
                                    {{ $subject->subject_code }} - {{ $subject->subject_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="class_name">Class Name</label>
                        <input class="form-control form-control-lg" id="class_name" name="class_name" placeholder="e.g. BSIT 2A" required type="text" value="{{ old('class_name') }}">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold text-uppercase small" for="school_year">School Year</label>
                        <input class="form-control form-control-lg" id="school_year" name="school_year" placeholder="e.g. 2026-2027" required type="text" value="{{ old('school_year') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit">Save Class</button>
                </div>
            </form>
        </div>
    </div>
@endsection
