    {{-- Add Admin/Dean access popup --}}
    <div class="modal fade" id="grantAdminDeanModal" tabindex="-1" aria-labelledby="grantAdminDeanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.roles.grant') }}" class="modal-content" method="POST">
                @csrf
                <input name="form_mode" type="hidden" value="grant_admin_dean">
                <input name="role_name" type="hidden" value="admin_dean">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="grantAdminDeanModalLabel">Authorize Admin/Dean</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="admin_dean_user_id">Teacher Account</label>
                    <select class="form-select form-select-lg" id="admin_dean_user_id" name="user_id" required @disabled($availableAdminDeanTeachers->isEmpty())>
                        @forelse ($availableAdminDeanTeachers as $user)
                            <option value="{{ $user->id }}" @selected(old('form_mode') === 'grant_admin_dean' && (int) old('user_id') === $user->id)>{{ $user->displayName() }} - {{ $user->email }}</option>
                        @empty
                            <option>No available teacher accounts</option>
                        @endforelse
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($availableAdminDeanTeachers->isEmpty())>Confirm Authorization</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add Department Chair access popup --}}
    <div class="modal fade" id="grantDepartmentChairModal" tabindex="-1" aria-labelledby="grantDepartmentChairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.roles.grant') }}" class="modal-content" method="POST">
                @csrf
                <input name="form_mode" type="hidden" value="grant_department_chair">
                <input name="role_name" type="hidden" value="department_chair">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="grantDepartmentChairModalLabel">Authorize Department Chair</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="department_chair_user_id">Teacher Account</label>
                    <select class="form-select form-select-lg" id="department_chair_user_id" name="user_id" required @disabled($availableDepartmentChairTeachers->isEmpty())>
                        @forelse ($availableDepartmentChairTeachers as $user)
                            <option value="{{ $user->id }}" @selected(old('form_mode') === 'grant_department_chair' && (int) old('user_id') === $user->id)>{{ $user->displayName() }} - {{ $user->email }}</option>
                        @empty
                            <option>No available teacher accounts</option>
                        @endforelse
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($availableDepartmentChairTeachers->isEmpty())>Confirm Authorization</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Remove Admin/Dean access popups --}}
    @foreach ($adminDeans as $user)
        <div class="modal fade" id="viewAdminDeanModal{{ $user->id }}" tabindex="-1" aria-labelledby="viewAdminDeanModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h4 mb-1" id="viewAdminDeanModalLabel{{ $user->id }}">Authorization Details</h3>
                            <p class="small text-white-50 mb-0">Admin/Dean Access</p>
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
                        <h3 class="modal-title h4" id="removeAdminDeanModalLabel{{ $user->id }}">Remove Authorization</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove Admin/Dean authorization from this teacher?</p>
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

    {{-- Remove Department Chair access popups --}}
    @foreach ($departmentChairs as $user)
        <div class="modal fade" id="viewDepartmentChairModal{{ $user->id }}" tabindex="-1" aria-labelledby="viewDepartmentChairModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h4 mb-1" id="viewDepartmentChairModalLabel{{ $user->id }}">Authorization Details</h3>
                            <p class="small text-white-50 mb-0">Department Chair Access</p>
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
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                        <button class="btn btn-danger px-4" data-bs-target="#removeDepartmentChairModal{{ $user->id }}" data-bs-toggle="modal" type="button">Remove</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="removeDepartmentChairModal{{ $user->id }}" tabindex="-1" aria-labelledby="removeDepartmentChairModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.roles.revoke') }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <input name="user_id" type="hidden" value="{{ $user->id }}">
                    <input name="role_name" type="hidden" value="department_chair">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="removeDepartmentChairModalLabel{{ $user->id }}">Remove Authorization</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove Department Chair authorization from this teacher?</p>
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
