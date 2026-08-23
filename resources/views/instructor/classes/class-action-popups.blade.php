@foreach ($classes as $class)
    @if ($activeClassTab === 'active')
        <div class="modal fade" id="joinRequestsModal{{ $class->class_id }}" tabindex="-1" aria-labelledby="joinRequestsModalLabel{{ $class->class_id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h4 mb-1" id="joinRequestsModalLabel{{ $class->class_id }}">Join Requests</h3>
                            <p class="small text-white-50 mb-0">{{ $class->class_name }}{{ $class->join_code ? ' - ' . $class->join_code : '' }}</p>
                        </div>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        @if ($class->joinRequests->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 compact-data-table join-requests-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Program</th>
                                            <th>Requested</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($class->joinRequests as $joinRequest)
                                            @php($student = $joinRequest->studentProfile)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <span class="class-icon rounded-circle flex-shrink-0">{{ strtoupper(substr($student?->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                                        <div>
                                                            <div class="fw-bold" style="color: var(--psu-navy);">{{ $student?->user?->displayName() ?? 'Unknown student' }}</div>
                                                            <div class="small text-secondary">{{ $student?->student_number ?? 'No student number' }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($student?->program)
                                                        <div class="fw-semibold">{{ $student->program->program_name }}</div>
                                                        <div class="small text-secondary">{{ $student->program->college?->college_name }}</div>
                                                    @else
                                                        <span class="text-secondary">Not assigned</span>
                                                    @endif
                                                </td>
                                                <td>{{ $joinRequest->requested_at?->format('M d, Y g:i A') ?? 'Recently' }}</td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                                        <form action="{{ route('instructor.classes.join-requests.approve', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" data-ajax-form data-reload-page-on-success="true">
                                                            @csrf
                                                            <button class="btn btn-success btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" type="submit" title="Approve request" aria-label="Approve request">
                                                                <span class="material-symbols-outlined fs-6">check</span>
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('instructor.classes.join-requests.reject', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" onsubmit="return confirm('Reject this join request?');" data-ajax-form data-reload-page-on-success="true">
                                                            @csrf
                                                            <button class="btn btn-outline-danger btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" type="submit" title="Reject request" aria-label="Reject request">
                                                                <span class="material-symbols-outlined fs-6">close</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-5 text-center">
                                <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">person_add_disabled</span></div>
                                <h4 class="h5 mb-1" style="color: var(--psu-navy);">No pending requests</h4>
                                <p class="text-secondary mb-0">Students who request to join this class will appear here.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

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
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="edit_class_name_{{ $class->class_id }}">Class Name</label>
                            <input class="form-control form-control-lg" id="edit_class_name_{{ $class->class_id }}" name="class_name" required type="text" value="{{ $class->class_name }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold text-uppercase small" for="edit_school_year_{{ $class->class_id }}">School Year</label>
                            <input class="form-control form-control-lg" id="edit_school_year_{{ $class->class_id }}" name="school_year" required type="text" value="{{ $class->school_year }}">
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
                        <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $class->class_name }}</p>
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
                        <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $class->class_name }}</p>
                        <p class="text-secondary mb-0">This will remove the class record, its enrolled student links, join requests, and class assessment publications.</p>
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
