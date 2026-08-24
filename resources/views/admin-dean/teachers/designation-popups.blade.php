@foreach ($teachers as $teacher)
    @if (! $teacher->hasRole('admin_dean'))
        @if (! $teacher->hasRole('department_chair'))
            <div class="modal fade" id="grantDepartmentChairDesignationModal{{ $teacher->id }}" tabindex="-1" aria-labelledby="grantDepartmentChairDesignationModalLabel{{ $teacher->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form action="{{ route('admin-dean.teachers.department-chair.grant', $teacher) }}" class="modal-content" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h3 class="modal-title h4" id="grantDepartmentChairDesignationModalLabel{{ $teacher->id }}">Designate Department Chair</h3>
                            <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Designate this teacher as Department Chair?</p>
                            <div class="border rounded p-3" style="background: #eff4ff;">
                                <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $teacher->displayName() }}</p>
                                <p class="small text-secondary mb-0">{{ $teacher->instructorProfile?->department?->dept_name ?? 'No department' }}</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                            <button class="btn btn-psu px-4" type="submit">Confirm Designation</button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="modal fade" id="removeDepartmentChairDesignationModal{{ $teacher->id }}" tabindex="-1" aria-labelledby="removeDepartmentChairDesignationModalLabel{{ $teacher->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form action="{{ route('admin-dean.teachers.department-chair.revoke', $teacher) }}" class="modal-content" method="POST">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h3 class="modal-title h4" id="removeDepartmentChairDesignationModalLabel{{ $teacher->id }}">Remove Designation</h3>
                            <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Remove Department Chair designation from this teacher?</p>
                            <div class="border rounded p-3" style="background: #eff4ff;">
                                <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $teacher->displayName() }}</p>
                                <p class="small text-secondary mb-0">{{ $teacher->instructorProfile?->department?->dept_name ?? 'No department' }}</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                            <button class="btn btn-danger px-4" type="submit">Confirm Remove</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
@endforeach
