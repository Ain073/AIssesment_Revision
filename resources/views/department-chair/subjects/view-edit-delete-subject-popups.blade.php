    {{-- View, edit, and delete popups --}}
    @php
        $subjectRoutePrefix = $subjectRoutePrefix ?? 'department-chair';
    @endphp
@php
    $semesterOptions = $semesters ?? ['First Semester', 'Second Semester', 'Summer'];
@endphp

@foreach ($subjects as $subject)
        @php
            $subjectSemester = $subject->semester?->semester_name ?? 'Not assigned';
            $subjectIsActive = $subject->is_active && $activeSemester === $subjectSemester;
        @endphp
        <div class="modal fade" id="viewSubjectModal{{ $subject->subject_id }}" tabindex="-1" aria-labelledby="viewSubjectModalLabel{{ $subject->subject_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="viewSubjectModalLabel{{ $subject->subject_id }}">Subject Details</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <div class="col-sm-5">
                                <div class="subject-detail-label mb-1">Subject Code</div>
                                <div class="fw-bold" style="color: var(--psu-navy);">{{ $subject->subject_code }}</div>
                            </div>
                            <div class="col-sm-7">
                                <div class="subject-detail-label mb-1">Status</div>
                                <span class="badge {{ $subjectIsActive ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $subjectIsActive ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-12">
                                <div class="subject-detail-label mb-1">Subject Name</div>
                                <div class="fw-semibold">{{ $subject->subject_name }}</div>
                            </div>
                            <div class="col-12">
                                <div class="subject-detail-label mb-1">Program</div>
                                <div class="fw-semibold">{{ $subject->program?->program_name ?? 'Not assigned' }}</div>
                                <div class="small text-secondary">
                                    {{ collect([$subject->program?->department?->dept_name, $subject->program?->department?->college?->college_name])->filter()->join(' - ') ?: 'No department' }}
                                </div>
                            </div>
                            <div class="col-sm-5">
                                <div class="subject-detail-label mb-1">Year Level</div>
                                <div>{{ $subject->yearLevelLabel() }}</div>
                            </div>
                            <div class="col-sm-7">
                                <div class="subject-detail-label mb-1">Semester</div>
                                <div>{{ $subjectSemester }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                        <button class="btn btn-outline-primary px-4" data-bs-target="#editSubjectModal{{ $subject->subject_id }}" data-bs-toggle="modal" type="button">Edit</button>
                        <button class="btn btn-danger px-4" data-bs-target="#deleteSubjectModal{{ $subject->subject_id }}" data-bs-toggle="modal" type="button">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editSubjectModal{{ $subject->subject_id }}" tabindex="-1" aria-labelledby="editSubjectModalLabel{{ $subject->subject_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route($subjectRoutePrefix.'.subjects.update', ['subject' => $subject, 'program' => $selectedProgramKey, 'year_level' => $selectedYearLevel, 'semester' => $selectedSemester]) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    @method('PUT')
                    <input name="is_active" type="hidden" value="0">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="editSubjectModalLabel{{ $subject->subject_id }}">Edit Subject</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="edit_program_id_{{ $subject->subject_id }}">Program</label>
                            <select class="form-select" id="edit_program_id_{{ $subject->subject_id }}" name="program_id" required>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->program_id }}" @selected($subject->program_id === $program->program_id)>{{ $program->program_name }} - {{ $program->department?->dept_name }} - {{ $program->department?->college?->college_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-uppercase small" for="edit_subject_code_{{ $subject->subject_id }}">Subject Code</label>
                                <input class="form-control" id="edit_subject_code_{{ $subject->subject_id }}" name="subject_code" required type="text" value="{{ $subject->subject_code }}">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-uppercase small" for="edit_subject_name_{{ $subject->subject_id }}">Subject Name</label>
                                <input class="form-control" id="edit_subject_name_{{ $subject->subject_id }}" name="subject_name" required type="text" value="{{ $subject->subject_name }}">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-uppercase small" for="edit_year_level_{{ $subject->subject_id }}">Year Level</label>
                                <select class="form-select" id="edit_year_level_{{ $subject->subject_id }}" name="year_level" required>
                                    @foreach ([1, 2, 3, 4] as $yearLevel)
                                        <option value="{{ $yearLevel }}" @selected((int) $subject->year_level === $yearLevel)>{{ \App\Support\YearLevel::label($yearLevel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-uppercase small" for="edit_semester_{{ $subject->subject_id }}">Semester</label>
                                <select class="form-select" id="edit_semester_{{ $subject->subject_id }}" name="semester" required>
                                    @foreach ($semesterOptions as $semester)
                                        <option value="{{ $semester }}" @selected($subjectSemester === $semester)>{{ $semester }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" id="edit_is_active_{{ $subject->subject_id }}" name="is_active" type="checkbox" value="1" @checked($subject->is_active)>
                            <label class="form-check-label fw-semibold" for="edit_is_active_{{ $subject->subject_id }}">Subject is enabled</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                        <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="deleteSubjectModal{{ $subject->subject_id }}" tabindex="-1" aria-labelledby="deleteSubjectModalLabel{{ $subject->subject_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <form action="{{ route($subjectRoutePrefix.'.subjects.destroy', ['subject' => $subject, 'program' => $selectedProgramKey, 'year_level' => $selectedYearLevel, 'semester' => $selectedSemester]) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="deleteSubjectModalLabel{{ $subject->subject_id }}">Delete Subject</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">Delete <strong>{{ $subject->subject_code }}</strong> from:</p>
                        <p class="fw-semibold mb-0">{{ $subject->program?->program_name }}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
