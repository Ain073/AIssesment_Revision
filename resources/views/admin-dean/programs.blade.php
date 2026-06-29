@extends('layouts.portal')

@section('title', 'Programs | AIssessment Admin/Dean')
@section('header', 'Programs')

@push('styles')
    <style>
        .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header {
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

        .icon-box {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
            border-radius: 0.25rem;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
        }

        .programs-table {
            min-width: 900px;
        }

        .modal-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
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

    <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">school</span>
            Create Program
        </button>
    </div>

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            New programs created here will automatically belong to <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Programs List</h3>
            <span class="small text-white-50">Program records currently visible in the dean workspace</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 programs-table">
                <thead>
                    <tr>
                        <th>Program Name</th>
                        <th>College</th>
                        <th>Students</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="icon-box"><span class="material-symbols-outlined">school</span></span>
                                    <span class="fw-bold" style="color: var(--psu-navy);">{{ $program->program_name }}</span>
                                </div>
                            </td>
                            <td>{{ $program->college?->college_name ?? 'Not assigned' }}</td>
                            <td>
                                {{ $program->student_profiles_count }}
                                {{ $program->student_profiles_count === 1 ? 'Student' : 'Students' }}
                            </td>
                            <td>
                                <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="4">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No programs yet</h4>
                                <p class="text-secondary mb-4">Create the first program so students can be grouped under a real academic offering.</p>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">school</span>
                                    Create First Program
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $programs->count() }} {{ $programs->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Student mapping can follow next</span>
        </div>
    </section>

    <div class="modal fade" id="programModal" tabindex="-1" aria-labelledby="programModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('admin-dean.programs.store') }}" class="modal-content" method="POST">
                @csrf
                <input name="is_active" type="hidden" value="0">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="programModalLabel">New Program</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($scopedCollege)
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="college_scope_name">College</label>
                            <input class="form-control form-control-lg" id="college_scope_name" type="text" value="{{ $scopedCollege->college_name }}" readonly>
                            <input name="college_id" type="hidden" value="{{ $scopedCollege->college_id }}">
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="college_id">College</label>
                            <select class="form-select form-select-lg" id="college_id" name="college_id" required @disabled($colleges->isEmpty())>
                                @forelse ($colleges as $college)
                                    <option value="{{ $college->college_id }}" @selected(old('college_id') == $college->college_id)>{{ $college->college_name }}</option>
                                @empty
                                    <option>No colleges available yet</option>
                                @endforelse
                            </select>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="program_name">Program Name</label>
                        <input class="form-control form-control-lg" id="program_name" name="program_name" placeholder="e.g. Bachelor of Science in Information Technology" required type="text" value="{{ old('program_name') }}">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="is_active_switch" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                        <label class="form-check-label fw-semibold" for="is_active_switch">Program is active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($colleges->isEmpty())>Save Program</button>
                </div>
            </form>
        </div>
    </div>
@endsection
