<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card p-4">
            <p class="small fw-bold text-secondary text-uppercase mb-2">Total Classes</p>
            <div class="d-flex align-items-baseline gap-2">
                <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalClasses }}</span>
                <span class="small text-secondary">Classes under your instructor account</span>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card p-4">
            <p class="small fw-bold text-secondary text-uppercase mb-2">Latest School Year</p>
            <div class="d-flex align-items-baseline gap-2">
                <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $latestSchoolYear ?? 'N/A' }}</span>
                <span class="small text-secondary">Based on your saved class records</span>
            </div>
        </div>
    </div>
</div>

<div class="alert {{ ! $instructorProfile ? 'alert-warning' : ($subjects->isEmpty() ? 'alert-warning' : 'alert-primary') }} border-0 shadow-sm mb-4">
    @if (! $instructorProfile)
        This account does not have an instructor profile yet, so class creation is temporarily unavailable.
    @elseif ($subjects->isEmpty())
        No active subjects are available yet. Add subjects first from the Super Admin portal before creating classes.
    @else
        This follows the ERD class structure. New class records will automatically use your instructor profile and selected subject.
    @endif
</div>

<div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
    <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty())>
        <span class="material-symbols-outlined fs-5">add</span>
        Create Class
    </button>
</div>

<section class="directory-card shadow-sm">
    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
        <h3 class="h4 mb-0">Classes List</h3>
        <span class="small text-white-50">
            {{ $instructorProfile?->department?->dept_name ?? 'No department assigned yet' }}
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 classes-table">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Students</th>
                    <th>Join Code</th>
                    <th>School Year</th>
                    <th>Department</th>
                    <th>College</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $class)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="class-icon"><span class="material-symbols-outlined">school</span></span>
                                <div>
                                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $class->class_name }}</div>
                                    <div class="small text-secondary">Class ID: {{ $class->class_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($class->subject)
                                <div class="fw-bold" style="color: var(--psu-navy);">{{ $class->subject->subject_code }}</div>
                                <div class="small text-secondary">{{ $class->subject->subject_name }}</div>
                            @else
                                <span class="text-secondary">Not selected</span>
                            @endif
                        </td>
                        <td>{{ $class->students_count }}</td>
                        <td>
                            @if ($class->join_code)
                                <span class="badge text-bg-light border rounded-1 px-3 py-2 join-code-pill">{{ $class->join_code }}</span>
                            @else
                                <span class="text-secondary">Open class to generate</span>
                            @endif
                        </td>
                        <td>{{ $class->school_year }}</td>
                        <td>{{ $class->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</td>
                        <td>{{ $class->instructorProfile?->department?->college?->college_name ?? 'Not assigned' }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" href="{{ route('instructor.classes.show', ['class' => $class, 'tab' => 'students']) }}">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                    View
                                </a>
                                <button class="btn btn-outline-psu btn-sm d-inline-flex align-items-center gap-1" data-bs-target="#editClassModal{{ $class->class_id }}" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-6">edit</span>
                                    Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" data-bs-target="#deleteClassModal{{ $class->class_id }}" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-6">delete</span>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center py-5" colspan="8">
                            <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">school</span></div>
                            <h4 class="h4" style="color: var(--psu-navy);">No classes yet</h4>
                            <p class="text-secondary mb-4">Create your first class record based on the ERD fields.</p>
                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty())>
                                <span class="material-symbols-outlined fs-5">add</span>
                                Create First Class
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
        <span class="small text-secondary">Showing {{ $classes->count() }} {{ $classes->count() === 1 ? 'entry' : 'entries' }}</span>
        <span class="small text-secondary">Handled by {{ $profileName }}</span>
    </div>
</section>

@foreach ($classes as $class)
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
@endforeach
