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

        .designation-toolbar {
            align-items: flex-end;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .designation-filter {
            width: min(100%, 520px);
        }

        @media (max-width: 767.98px) {
            .designation-toolbar {
                align-items: stretch;
            }

            .designation-action-row,
            .designation-action-row .btn,
            .designation-filter {
                width: 100%;
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

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            You are managing Department Chair designations under <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <div class="designation-toolbar">
        <form action="{{ route('admin-dean.designations') }}" class="designation-filter" method="GET" data-ajax-page-form>
            <label class="form-label fw-bold text-uppercase small" for="departmentDesignationFilter">Department</label>
            <select class="form-select" id="departmentDesignationFilter" name="department_id" onchange="this.form.submit()">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->department_id }}" @selected((int) $selectedDepartmentId === (int) $department->department_id)>
                        {{ $department->dept_name }}
                    </option>
                @endforeach
            </select>
        </form>

        <div class="designation-action-row d-flex align-items-center gap-2 mb-0">
            <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" data-bs-target="#chairHistoryModal" data-bs-toggle="modal" type="button">
                <span class="material-symbols-outlined fs-5">history</span>
                Designation History
            </button>
            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#grantDepartmentChairModal" data-bs-toggle="modal" type="button">
                <span class="material-symbols-outlined fs-5">add</span>
                Designation
            </button>
        </div>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header px-4 py-3">
            <h3 class="h4 mb-0">Department Chair Designation</h3>
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
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Designation
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

    {{-- Department Chair History Modal --}}
    <div class="modal fade" id="chairHistoryModal" tabindex="-1" aria-labelledby="chairHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-1" id="chairHistoryModalLabel">Department Chair History & Term Records</h3>
                        <p class="small text-white-50 mb-0">Institutional timeline and past appointments of Department Chairs</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
                            <thead>
                                <tr>
                                    <th>Instructor</th>
                                    <th>Department</th>
                                    <th>Academic Year</th>
                                    <th>Term Period</th>
                                    <th>Status</th>
                                    <th>Designated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($designationHistory as $history)
                                    @php
                                        $instructorUser = $history->instructor?->user;
                                        $effectivity = $history->effectivity_date ? \Carbon\Carbon::parse($history->effectivity_date)->format('M d, Y') : '—';
                                        $endDate = $history->end_date ? \Carbon\Carbon::parse($history->end_date)->format('M d, Y') : 'Present';
                                    @endphp
                                    <tr>
                                        <td class="mobile-primary-cell" data-label="Instructor">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($instructorUser?->displayName() ?? 'U', 0, 1)) }}</span>
                                                <div>
                                                    <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $instructorUser?->displayName() ?? 'Unknown Teacher' }}</p>
                                                    <p class="small text-secondary mb-0">{{ $instructorUser?->email ?? '' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Department">{{ $history->department?->dept_name ?? '—' }}</td>
                                        <td data-label="Academic Year">
                                            <span class="badge bg-light text-dark border">{{ $history->academic_year }}</span>
                                        </td>
                                        <td data-label="Term Period">
                                            <div class="small fw-semibold text-secondary">
                                                {{ $effectivity }} &rarr; <span class="{{ $history->status === 'active' ? 'text-success fw-bold' : '' }}">{{ $endDate }}</span>
                                            </div>
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge {{ $history->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($history->status) }}
                                            </span>
                                        </td>
                                        <td data-label="Designated By">
                                            <span class="small text-secondary">{{ $history->designatedByUser?->displayName() ?? 'System / Dean' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-4 mobile-empty-cell" colspan="6">
                                            <p class="small text-secondary mb-0">No past designation records found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="grantDepartmentChairModal" tabindex="-1" aria-labelledby="grantDepartmentChairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-0" id="grantDepartmentChairModalLabel">Designate Department Chair</h3>
                        <p class="small text-white-50 mb-0">Assign a faculty member as department chair</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Filter by Department & Small Search Bar --}}
                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-md-6 col-12">
                            <select class="form-select form-select-sm" id="chairDepartmentFilter" onchange="filterSimpleChairList()">
                                <option value="">All Departments</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->department_id }}" @selected((int) $selectedDepartmentId === (int) $department->department_id)>
                                        {{ $department->dept_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-secondary">
                                    <span class="material-symbols-outlined fs-6">search</span>
                                </span>
                                <input class="form-control form-control-sm border-start-0" id="searchChairFacultyInput" placeholder="Search teacher by name, email..." type="text" oninput="filterSimpleChairList()">
                            </div>
                        </div>
                    </div>

                    <div class="designation-picker-list" id="chairFacultyPickerList">
                        @forelse ($availableDepartmentChairTeachers as $teacher)
                            @php
                                $deptId = $teacher->instructorProfile?->department_id ?? '';
                                $deptName = $teacher->instructorProfile?->department?->dept_name ?? 'Not assigned';
                                $displayName = $teacher->displayName();
                            @endphp
                            <div class="designation-picker-card chair-picker-item" role="button" tabindex="0"
                                data-dept-id="{{ $deptId }}"
                                data-search="{{ strtolower($displayName . ' ' . $teacher->email . ' ' . $deptName) }}"
                                onclick="openChairConfirmDialog('{{ $teacher->id }}', '{{ addslashes($displayName) }}', '{{ addslashes($deptName) }}', '{{ addslashes($teacher->email) }}', '{{ route('admin-dean.teachers.department-chair.grant', $teacher) }}')">
                                <span class="avatar">{{ strtoupper(substr($displayName, 0, 1)) }}</span>
                                <span class="designation-picker-info">
                                    <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $displayName }}</span>
                                    <span class="small text-secondary d-block">{{ $teacher->email }}</span>
                                    <span class="small text-secondary d-block">
                                        <strong class="text-dark">{{ $deptName }}</strong>
                                    </span>
                                </span>
                                <span class="designation-picker-action" aria-hidden="true" title="Designate as Chair">
                                    <span class="material-symbols-outlined fs-5">add</span>
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">person_off</span></div>
                                <p class="fw-bold mb-1" style="color: var(--psu-navy);">No available teacher accounts</p>
                                <p class="small text-secondary mb-0">All eligible teachers already have a designation or no teacher account is available yet.</p>
                            </div>
                        @endforelse
                    </div>

                    <div id="noChairFacultyFound" class="text-center py-4 d-none">
                        <p class="small text-secondary mb-0">No matching teacher found for this department or search term.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Styled Confirmation Modal for Chair Designation --}}
    <div class="modal fade" id="chairConfirmModal" tabindex="-1" aria-labelledby="chairConfirmModalLabel" aria-hidden="true" style="z-index: 1065;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <form id="chairConfirmForm" action="" method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                @csrf
                <div class="modal-body p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 shadow-sm" style="width: 56px; height: 56px; background: rgba(0, 26, 112, 0.08); color: var(--psu-navy);">
                        <span class="material-symbols-outlined fs-2">verified_user</span>
                    </div>

                    <h4 class="h5 fw-bold mb-1" id="chairConfirmModalLabel" style="color: var(--psu-navy);">Confirm Chair Designation</h4>
                    <p class="small text-secondary mb-3">Please review the faculty member before confirming designation.</p>

                    <div class="border rounded-3 p-3 text-start mb-3" style="background: #f8fafc;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar flex-shrink-0" id="chairConfirmAvatar">U</span>
                            <div class="overflow-hidden">
                                <p class="fw-bold mb-0 text-truncate" id="chairConfirmName" style="color: var(--psu-navy);">Faculty Name</p>
                                <p class="small text-secondary mb-1 text-truncate" id="chairConfirmEmail">email@psu.edu.ph</p>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-1" id="chairConfirmDept">Department</span>
                            </div>
                        </div>
                    </div>

                    <p class="small text-muted mb-0">This appointment will be officially recorded in institutional records with today's date.</p>
                </div>

                <div class="modal-footer px-4 py-3 bg-light border-0 d-flex gap-2">
                    <button class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1 shadow-sm" type="submit">
                        <span class="material-symbols-outlined fs-6">check</span>
                        Confirm & Designate
                    </button>
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
                            @php
                                $activeDesig = $teacher->instructorProfile?->activeDesignation;
                            @endphp
                            @if ($activeDesig)
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-uppercase small">Academic Year</label>
                                    <div class="form-control bg-light">{{ $activeDesig->academic_year }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-uppercase small">Effectivity Date</label>
                                    <div class="form-control bg-light">{{ \Carbon\Carbon::parse($activeDesig->effectivity_date)->format('M d, Y') }}</div>
                                </div>
                            @endif
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
    function filterSimpleChairList() {
        const deptId = document.getElementById('chairDepartmentFilter')?.value || '';
        const q = (document.getElementById('searchChairFacultyInput')?.value || '').toLowerCase().trim();
        const items = document.querySelectorAll('.chair-picker-item');
        let count = 0;

        items.forEach(item => {
            const itemDeptId = item.getAttribute('data-dept-id') || '';
            const searchData = item.getAttribute('data-search') || '';

            const matchesDept = !deptId || itemDeptId === deptId;
            const matchesQuery = !q || searchData.includes(q);

            if (matchesDept && matchesQuery) {
                item.classList.remove('d-none');
                count++;
            } else {
                item.classList.add('d-none');
            }
        });

        const emptyMsg = document.getElementById('noChairFacultyFound');
        if (emptyMsg) {
            emptyMsg.classList.toggle('d-none', count > 0 || items.length === 0);
        }
    }

    function openChairConfirmDialog(teacherId, teacherName, deptName, teacherEmail, formAction) {
        document.getElementById('chairConfirmForm').action = formAction;
        document.getElementById('chairConfirmName').textContent = teacherName;
        document.getElementById('chairConfirmDept').textContent = deptName;
        document.getElementById('chairConfirmAvatar').textContent = (teacherName.charAt(0) || 'U').toUpperCase();
        const emailEl = document.getElementById('chairConfirmEmail');
        if (emailEl) {
            emailEl.textContent = teacherEmail || '';
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('chairConfirmModal'));
        modal.show();
    }
</script>
@endpush
