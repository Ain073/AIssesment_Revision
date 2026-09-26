@extends('layouts.portal')

@section('title', 'Programs | AIssessment Department Chair')
@section('header', 'Programs')
@section('body_class', 'department-chair-programs')

@push('styles')
    <style>
        .department-chair-programs .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .department-chair-programs .table thead th {
            background: #edf2ff;
            color: var(--psu-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1rem 1.25rem;
        }

        .department-chair-programs .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .department-chair-programs .programs-table {
            min-width: 920px;
        }

        .department-chair-programs .programs-table .count-cell {
            text-align: center;
        }

        .department-chair-programs .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
        }

        @media (max-width: 991.98px) {
            .department-chair-programs .programs-table {
                min-width: 0;
            }
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

    @if (! $scopedDepartment)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            This account has no assigned department yet, so program scope cannot be resolved.
        </div>
    @endif

    <div class="program-toolbar super-admin-toolbar d-flex flex-wrap align-items-end justify-content-end gap-3 mb-4">
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment)>
            <span class="material-symbols-outlined fs-5">add</span>
            Program
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header px-4 py-3">
            <h3 class="h4 mb-0">Programs List</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 programs-table mobile-card-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Department</th>
                        <th class="count-cell">Students</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr>
                            <td class="fw-bold mobile-primary-cell" data-label="Program" style="color: var(--psu-navy);">{{ $program->program_name }}</td>
                            <td data-label="Department">
                                <span class="fw-semibold d-block">{{ $program->department?->dept_name ?? 'Not assigned' }}</span>
                                @if ($program->department?->college?->college_name)
                                    <span class="small text-secondary d-block">{{ $program->department->college->college_name }}</span>
                                @endif
                            </td>
                            <td class="count-cell" data-label="Students">{{ $program->student_profiles_count }}</td>
                            <td data-label="Status">
                                <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Actions">
                                <button class="btn btn-sm record-action-trigger" data-bs-target="#viewProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $program->program_name }}">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No programs yet</h4>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment)>
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Add First Program
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
            <span class="small text-secondary">Showing {{ $programs->count() }} {{ $programs->count() === 1 ? 'entry' : 'entries' }}</span>
        </div>
    </section>

    @include('department-chair.programs.partials.create-program-form')
    @include('department-chair.programs.partials.program-popups')
@endsection

@push('scripts')
    @if (request('action') === 'create-program')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('programModal');
                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush
