{{-- View, edit, and delete program popups --}}
@foreach ($programs as $program)
    <div class="modal fade" id="viewProgramModal{{ $program->program_id }}" tabindex="-1" aria-labelledby="viewProgramModalLabel{{ $program->program_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="viewProgramModalLabel{{ $program->program_id }}">Program Details</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="program-detail-label mb-1">Program</div>
                        <div class="fw-bold" style="color: var(--psu-navy);">{{ $program->program_name }}</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="program-detail-label mb-1">Department</div>
                            <div>{{ $program->department?->dept_name ?? 'Not assigned' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="program-detail-label mb-1">College</div>
                            <div>{{ $program->department?->college?->college_name ?? 'Not assigned' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="program-detail-label mb-1">Status</div>
                            <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                {{ $program->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <div class="program-detail-label mb-1">Students</div>
                            <div class="fw-bold">{{ $program->student_profiles_count }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="program-detail-label mb-1">Subject Mappings</div>
                            <div class="fw-bold">{{ $program->subject_programs_count }}</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                    <button class="btn btn-outline-primary px-4" data-bs-target="#editProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button">Edit</button>
                    <button class="btn btn-danger px-4" data-bs-target="#deleteProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editProgramModal{{ $program->program_id }}" tabindex="-1" aria-labelledby="editProgramModalLabel{{ $program->program_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.programs.update', ['program' => $program, 'college' => $selectedCollegeKey]) }}" class="modal-content" method="POST">
                @csrf
                @method('PUT')
                <input name="is_active" type="hidden" value="0">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="editProgramModalLabel{{ $program->program_id }}">Edit Program</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="edit_department_id_{{ $program->program_id }}">Department</label>
                        <select class="form-select form-select-lg" id="edit_department_id_{{ $program->program_id }}" name="department_id" required>
                            @foreach ($departments as $department)
                                <option value="{{ $department->department_id }}" @selected(old('department_id', $program->department_id) == $department->department_id)>
                                    {{ $department->dept_name }} - {{ $department->college?->college_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="edit_program_name_{{ $program->program_id }}">Program Name</label>
                        <input class="form-control form-control-lg" id="edit_program_name_{{ $program->program_id }}" name="program_name" required type="text" value="{{ old('program_name', $program->program_name) }}">
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" id="edit_is_active_{{ $program->program_id }}" name="is_active" type="checkbox" value="1" @checked(old('is_active', $program->is_active ? '1' : '0') === '1')>
                        <label class="form-check-label fw-semibold" for="edit_is_active_{{ $program->program_id }}">Program is active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteProgramModal{{ $program->program_id }}" tabindex="-1" aria-labelledby="deleteProgramModalLabel{{ $program->program_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.programs.destroy', ['program' => $program, 'college' => $selectedCollegeKey]) }}" class="modal-content" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="deleteProgramModalLabel{{ $program->program_id }}">Delete Program</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Delete <strong>{{ $program->program_name }}</strong>?</p>
                    <p class="text-secondary mb-0">Programs with linked students or subject mappings cannot be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-danger px-4" type="submit">Delete</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
