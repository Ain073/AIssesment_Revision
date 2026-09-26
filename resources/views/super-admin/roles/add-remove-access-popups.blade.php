    {{-- Add Dean designation popup (modal-lg with college filter & search) --}}
    <div class="modal fade" id="grantAdminDeanModal" tabindex="-1" aria-labelledby="grantAdminDeanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-0" id="grantAdminDeanModalLabel">Designate Dean</h3>
                        <p class="small text-white-50 mb-0">Assign a faculty member as college dean</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Filter by College & Small Search Bar --}}
                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-md-6 col-12">
                            <select class="form-select form-select-sm" id="deanCollegeFilter" onchange="filterDeanList()">
                                <option value="">All Colleges</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->college_id }}">{{ $college->college_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-secondary">
                                    <span class="material-symbols-outlined fs-6">search</span>
                                </span>
                                <input class="form-control form-control-sm border-start-0" id="searchDeanFacultyInput" placeholder="Search by name, email, department..." type="text" oninput="filterDeanList()">
                            </div>
                        </div>
                    </div>

                    <div class="designation-picker-list" id="deanFacultyPickerList">
                        @forelse ($availableAdminDeanTeachers as $user)
                            @php
                                $collegeId = $user->instructorProfile?->department?->college_id ?? '';
                                $collegeName = $user->instructorProfile?->department?->college?->college_name ?? 'No college';
                                $deptName = $user->instructorProfile?->department?->dept_name ?? 'No department';
                                $displayName = $user->displayName();
                            @endphp
                            <div class="designation-picker-card dean-picker-item" role="button" tabindex="0"
                                data-college-id="{{ $collegeId }}"
                                data-search="{{ strtolower($displayName . ' ' . $user->email . ' ' . $collegeName . ' ' . $deptName) }}"
                                onclick="openDeanConfirmDialog('{{ $user->id }}', '{{ addslashes($displayName) }}', '{{ addslashes($collegeName) }}', '{{ addslashes($deptName) }}', '{{ addslashes($user->email) }}')">
                                <span class="avatar">{{ strtoupper(substr($displayName, 0, 1)) }}</span>
                                <span class="designation-picker-info">
                                    <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $displayName }}</span>
                                    <span class="small text-secondary d-block">{{ $user->email }}</span>
                                    <span class="small text-secondary d-block">
                                        <strong class="text-dark">{{ $collegeName }}</strong> &bull; {{ $deptName }}
                                    </span>
                                </span>
                                <span class="designation-picker-action" aria-hidden="true" title="Designate as Dean">
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

                    <div id="noDeanFacultyFound" class="text-center py-4 d-none">
                        <p class="small text-secondary mb-0">No matching teacher found for this college or search term.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Styled Confirmation Modal for Dean Designation --}}
    <div class="modal fade" id="deanConfirmModal" tabindex="-1" aria-labelledby="deanConfirmModalLabel" aria-hidden="true" style="z-index: 1065;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <form action="{{ route('super-admin.roles.grant') }}" method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                @csrf
                <input name="form_mode" type="hidden" value="grant_admin_dean">
                <input name="role_name" type="hidden" value="admin_dean">
                <input id="deanConfirmUserId" name="user_id" type="hidden" value="">

                <div class="modal-body p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 shadow-sm" style="width: 56px; height: 56px; background: rgba(0, 26, 112, 0.08); color: var(--psu-navy);">
                        <span class="material-symbols-outlined fs-2">verified_user</span>
                    </div>

                    <h4 class="h5 fw-bold mb-1" id="deanConfirmModalLabel" style="color: var(--psu-navy);">Confirm Dean Designation</h4>
                    <p class="small text-secondary mb-3">Please review the faculty member before confirming designation.</p>

                    <div class="border rounded-3 p-3 text-start mb-3" style="background: #f8fafc;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar flex-shrink-0" id="deanConfirmAvatar">U</span>
                            <div class="overflow-hidden">
                                <p class="fw-bold mb-0 text-truncate" id="deanConfirmName" style="color: var(--psu-navy);">Faculty Name</p>
                                <p class="small text-secondary mb-1 text-truncate" id="deanConfirmEmail">email@psu.edu.ph</p>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-1" id="deanConfirmCollege">College</span>
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

    {{-- Remove Dean designation popups --}}
    @foreach ($adminDeans as $user)
        <div class="modal fade" id="viewAdminDeanModal{{ $user->id }}" tabindex="-1" aria-labelledby="viewAdminDeanModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h4 mb-1" id="viewAdminDeanModalLabel{{ $user->id }}">Designation Details</h3>
                            <p class="small text-white-50 mb-0">Dean Designation</p>
                        </div>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold text-uppercase small">Teacher</label>
                                <div class="form-control bg-light">{{ $user->displayName() }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Email</label>
                                <div class="form-control bg-light">{{ $user->email }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Status</label>
                                <div class="form-control bg-light">{{ ucfirst($user->status) }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">College</label>
                                <div class="form-control bg-light">{{ $user->instructorProfile?->department?->college?->college_name ?? 'Not assigned' }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Department</label>
                                <div class="form-control bg-light">{{ $user->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</div>
                            </div>
                            @php
                                $activeDesig = $user->instructorProfile?->activeDesignation;
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
                        <button class="btn btn-danger px-4" data-bs-target="#removeAdminDeanModal{{ $user->id }}" data-bs-toggle="modal" type="button">Remove</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="removeAdminDeanModal{{ $user->id }}" tabindex="-1" aria-labelledby="removeAdminDeanModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.roles.revoke') }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <input name="user_id" type="hidden" value="{{ $user->id }}">
                    <input name="role_name" type="hidden" value="admin_dean">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="removeAdminDeanModalLabel{{ $user->id }}">Remove Designation</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove Dean designation from this teacher?</p>
                        <div class="border rounded p-3" style="background: #eff4ff;">
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                            <p class="small text-secondary mb-0">{{ $user->email }}</p>
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
