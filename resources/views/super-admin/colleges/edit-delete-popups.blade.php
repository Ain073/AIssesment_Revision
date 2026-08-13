{{-- Edit and delete popups for colleges and departments --}}
@foreach ($colleges as $college)
    <div class="modal fade" id="editCollegeModal{{ $college->college_id }}" tabindex="-1" aria-labelledby="editCollegeModalLabel{{ $college->college_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.colleges.update', $college) }}" class="modal-content" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="editCollegeModalLabel{{ $college->college_id }}">Edit College</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="edit_college_name_{{ $college->college_id }}">College Name</label>
                    <input class="form-control form-control-lg" id="edit_college_name_{{ $college->college_id }}" name="college_name" required type="text" value="{{ old('college_name', $college->college_name) }}">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteCollegeModal{{ $college->college_id }}" tabindex="-1" aria-labelledby="deleteCollegeModalLabel{{ $college->college_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.colleges.destroy', $college) }}" class="modal-content" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="deleteCollegeModalLabel{{ $college->college_id }}">Delete College</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="delete-warning-icon mb-3">
                        <span class="material-symbols-outlined fs-2">warning</span>
                    </div>
                    <h4 class="h5 fw-bold" style="color: var(--psu-navy);">Delete {{ $college->college_name }}?</h4>
                    <p class="text-secondary mb-0">
                        This will also remove related departments and programs under this college.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-danger px-4" type="submit">Delete College</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@foreach ($departments as $department)
    <div class="modal fade" id="editDepartmentModal{{ $department->department_id }}" tabindex="-1" aria-labelledby="editDepartmentModalLabel{{ $department->department_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.departments.update', $department) }}" class="modal-content" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="editDepartmentModalLabel{{ $department->department_id }}">Edit Department</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="edit_department_college_id_{{ $department->department_id }}">College</label>
                        <select class="form-select form-select-lg" id="edit_department_college_id_{{ $department->department_id }}" name="college_id" required>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->college_id }}" @selected(old('college_id', $department->college_id) == $college->college_id)>{{ $college->college_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold text-uppercase small" for="edit_dept_name_{{ $department->department_id }}">Department Name</label>
                        <input class="form-control form-control-lg" id="edit_dept_name_{{ $department->department_id }}" name="dept_name" required type="text" value="{{ old('dept_name', $department->dept_name) }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteDepartmentModal{{ $department->department_id }}" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel{{ $department->department_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.departments.destroy', $department) }}" class="modal-content" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="deleteDepartmentModalLabel{{ $department->department_id }}">Delete Department</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="delete-warning-icon mb-3">
                        <span class="material-symbols-outlined fs-2">warning</span>
                    </div>
                    <h4 class="h5 fw-bold" style="color: var(--psu-navy);">Delete {{ $department->dept_name }}?</h4>
                    <p class="text-secondary mb-0">
                        Linked instructor profiles may lose this department assignment.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-danger px-4" type="submit">Delete Department</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
