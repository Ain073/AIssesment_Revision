@foreach ($classes as $class)
    @if ($activeClassTab === 'active')
        <div class="modal fade" id="editClassModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="editClassModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('instructor.classes.update', $class) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="editClassModalLabel{{ $class->class_id }}">Edit Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="edit_subject_id_{{ $class->class_id }}">Subject</label>
                            <select class="form-select form-select-lg" id="edit_subject_id_{{ $class->class_id }}" name="subject_id" required>
                                <option value="">Select subject</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->subject_id }}" @selected($class->subject_id == $subject->subject_id)>
                                        {{ $subject->subject_code }} - {{ $subject->subject_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_year_level_{{ $class->class_id }}">Year Level</label>
                                <select class="form-select form-select-lg" id="edit_year_level_{{ $class->class_id }}" name="year_level" required>
                                    @foreach ([1, 2, 3, 4] as $yearLevel)
                                        <option value="{{ $yearLevel }}" @selected((int) $class->year_level === $yearLevel)>{{ \App\Support\YearLevel::label($yearLevel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_section_name_{{ $class->class_id }}">Section Name</label>
                                <input class="form-control form-control-lg" id="edit_section_name_{{ $class->class_id }}" name="section_name" required type="text" value="{{ $class->section_name ?? $class->class_name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_school_year_{{ $class->class_id }}">Academic Year</label>
                                <input class="form-control form-control-lg" id="edit_school_year_{{ $class->class_id }}" name="school_year" placeholder="e.g. 2026-2027" required type="text" value="{{ $class->school_year }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="archiveClassModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="archiveClassModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('instructor.classes.archive', $class) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="archiveClassModalLabel{{ $class->class_id }}">Archive Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $class->displayName() }}</p>
                        <p class="text-secondary mb-0">This will move the class to archived records. It will no longer appear in active class lists or publishing selections.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-psu px-4" type="submit">Archive Class</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="deleteClassModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="deleteClassModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('instructor.classes.destroy', $class) }}" class="modal-content" method="POST" data-ajax-form>
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="deleteClassModalLabel{{ $class->class_id }}">Delete Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $class->displayName() }}</p>
                        <p class="text-secondary mb-0">This will remove the class record, its enrolled student links, join requests, and published assessment publications.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Delete Class</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
