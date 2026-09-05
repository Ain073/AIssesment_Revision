@extends('layouts.portal')

@section('title', 'Chair Designation | AIssessment Dean')
@section('header', 'Chair Designation')

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
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
            border-radius: 50%;
            font-weight: 800;
            flex-shrink: 0;
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
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            You are managing Department Chair designations under <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Department Chair Designation</h3>
            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white d-inline-flex align-items-center gap-1" data-bs-target="#grantDepartmentChairModal" data-bs-toggle="modal" type="button">
                <span class="material-symbols-outlined fs-6">person_add</span>
                Add Designation
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 mobile-card-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departmentChairs as $teacher)
                        <tr>
                            <td class="mobile-primary-cell" data-label="Teacher">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ strtoupper(substr($teacher->displayName(), 0, 1)) }}</span>
                                    <div>
                                        <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $teacher->displayName() }}</p>
                                        <p class="small text-secondary mb-0">{{ $teacher->instructorProfile?->employee_number ?? 'Not assigned' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Email">{{ $teacher->email }}</td>
                            <td data-label="Department">
                                {{ $teacher->instructorProfile?->department?->dept_name ?? 'Not assigned' }}
                            </td>
                            <td data-label="Status">
                                <span class="badge {{ $teacher->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ ucfirst($teacher->status) }}
                                </span>
                            </td>
                            <td class="text-end" data-label="Action">
                                <button class="btn btn-sm record-action-trigger" data-bs-target="#viewDepartmentChairModal{{ $teacher->id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $teacher->displayName() }}">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">supervisor_account</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No Department Chair designation yet</h4>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2 mt-2" data-bs-target="#grantDepartmentChairModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">person_add</span>
                                    Add Designation
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
            <span class="small text-secondary">Showing {{ $departmentChairs->count() }} {{ $departmentChairs->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Available teachers: {{ $availableDepartmentChairTeachers->count() }}</span>
        </div>
    </section>

    <div class="modal fade" id="grantDepartmentChairModal" tabindex="-1" aria-labelledby="grantDepartmentChairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ $availableDepartmentChairTeachers->isNotEmpty() ? route('admin-dean.teachers.department-chair.grant', $availableDepartmentChairTeachers->first()) : '#' }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="grantDepartmentChairModalLabel">Designate Department Chair</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="department_chair_user_id">Teacher Account</label>
                    <select class="form-select form-select-lg" id="department_chair_user_id" name="user_id" required @disabled($availableDepartmentChairTeachers->isEmpty())>
                        @forelse ($availableDepartmentChairTeachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->displayName() }} - {{ $teacher->email }}</option>
                        @empty
                            <option>No available teacher accounts</option>
                        @endforelse
                    </select>
                    <div class="small text-secondary mt-2">Choose a teacher from your college.</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" data-designation-submit type="submit" @disabled($availableDepartmentChairTeachers->isEmpty())>Confirm Designation</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($departmentChairs as $teacher)
        <div class="modal fade" id="viewDepartmentChairModal{{ $teacher->id }}" tabindex="-1" aria-labelledby="viewDepartmentChairModalLabel{{ $teacher->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h4 mb-1" id="viewDepartmentChairModalLabel{{ $teacher->id }}">Designation Details</h3>
                            <p class="small text-white-50 mb-0">Department Chair Designation</p>
                        </div>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold text-uppercase small">Teacher</label>
                                <div class="form-control bg-light">{{ $teacher->displayName() }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Email</label>
                                <div class="form-control bg-light">{{ $teacher->email }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Status</label>
                                <div class="form-control bg-light">{{ ucfirst($teacher->status) }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-uppercase small">Department</label>
                                <div class="form-control bg-light">{{ $teacher->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                        <button class="btn btn-danger px-4" data-bs-target="#removeDepartmentChairModal{{ $teacher->id }}" data-bs-toggle="modal" type="button">Remove</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="removeDepartmentChairModal{{ $teacher->id }}" tabindex="-1" aria-labelledby="removeDepartmentChairModalLabel{{ $teacher->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('admin-dean.teachers.department-chair.revoke', $teacher) }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="removeDepartmentChairModalLabel{{ $teacher->id }}">Remove Designation</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove Department Chair designation from this teacher?</p>
                        <div class="border rounded p-3" style="background: #eff4ff;">
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $teacher->displayName() }}</p>
                            <p class="small text-secondary mb-0">{{ $teacher->email }}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Confirm Remove</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.addEventListener('change', (event) => {
            const select = event.target.closest('#department_chair_user_id');

            if (! select) {
                return;
            }

            const form = select.closest('form');
            const selectedId = select.value;
            const currentAction = form.getAttribute('action');

            if (! selectedId || ! currentAction || currentAction === '#') {
                return;
            }

            form.setAttribute('action', currentAction.replace(/\/teachers\/\d+\/department-chair-designation$/, `/teachers/${selectedId}/department-chair-designation`));
        });
    </script>
@endpush
