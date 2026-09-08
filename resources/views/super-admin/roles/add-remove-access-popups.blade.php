    {{-- Add Dean designation popup --}}
    <div class="modal fade" id="grantAdminDeanModal" tabindex="-1" aria-labelledby="grantAdminDeanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="grantAdminDeanModalLabel">Designate Dean</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3">Choose a teacher account to designate as dean.</p>
                    <div class="designation-picker-list">
                        @forelse ($availableAdminDeanTeachers as $user)
                            <form action="{{ route('super-admin.roles.grant') }}" class="designation-picker-form" method="POST">
                                @csrf
                                <input name="form_mode" type="hidden" value="grant_admin_dean">
                                <input name="role_name" type="hidden" value="admin_dean">
                                <input name="user_id" type="hidden" value="{{ $user->id }}">
                                <button class="designation-picker-card" type="submit">
                                    <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                    <span class="designation-picker-info">
                                        <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $user->displayName() }}</span>
                                        <span class="small text-secondary d-block">{{ $user->email }}</span>
                                        <span class="small text-secondary d-block">Teacher Account</span>
                                    </span>
                                    <span class="designation-picker-action" aria-hidden="true">
                                        <span class="material-symbols-outlined fs-5">add</span>
                                    </span>
                                </button>
                            </form>
                        @empty
                            <div class="text-center py-4">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">person_off</span></div>
                                <p class="fw-bold mb-1" style="color: var(--psu-navy);">No available teacher accounts</p>
                                <p class="small text-secondary mb-0">All eligible teachers already have a designation or no teacher account is available yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                </div>
            </div>
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
