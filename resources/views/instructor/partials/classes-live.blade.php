@if (! $instructorProfile || $subjects->isEmpty())
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        @if (! $instructorProfile)
            This account does not have an instructor profile yet, so class creation is temporarily unavailable.
        @else
            No active subjects are available yet. Add subjects first from the Super Admin portal before creating classes.
        @endif
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div class="d-flex flex-wrap gap-2">
        <a class="btn class-list-tab {{ $activeClassTab === 'active' ? 'btn-psu' : 'btn-outline-primary' }} d-inline-flex align-items-center gap-2" href="{{ route('instructor.classes', ['tab' => 'active']) }}">
            <span class="material-symbols-outlined fs-5">school</span>
            Active Classes
            <span class="badge text-bg-light border rounded-1">{{ $activeClassesCount }}</span>
        </a>
        <a class="btn class-list-tab {{ $activeClassTab === 'archived' ? 'btn-psu' : 'btn-outline-primary' }} d-inline-flex align-items-center gap-2" href="{{ route('instructor.classes', ['tab' => 'archived']) }}">
            <span class="material-symbols-outlined fs-5">inventory_2</span>
            Archived Classes
            <span class="badge text-bg-light border rounded-1">{{ $archivedClassesCount }}</span>
        </a>
    </div>

    @if ($activeClassTab === 'active')
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty())>
            <span class="material-symbols-outlined fs-5">add</span>
            Create Class
        </button>
    @endif
</div>

<section class="directory-card shadow-sm">
    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
        <h3 class="h4 mb-0">{{ $activeClassTab === 'archived' ? 'Archived Classes' : 'Classes List' }}</h3>
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
                    <th class="text-center">Students</th>
                    <th class="text-center">Join Code</th>
                    <th>School Year</th>
                    <th class="text-center">Actions</th>
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
                        <td class="text-center">{{ $class->students_count }}</td>
                        <td class="text-center">
                            @if ($class->join_code)
                                <span class="badge text-bg-light border rounded-1 px-3 py-2 join-code-pill">{{ $class->join_code }}</span>
                            @else
                                <span class="text-secondary">Open class to generate</span>
                            @endif
                        </td>
                        <td>{{ $class->school_year }}</td>
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex flex-nowrap align-items-center gap-2">
                                <a class="btn btn-outline-primary btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" href="{{ route('instructor.classes.show', ['class' => $class, 'tab' => 'students']) }}" title="View class" aria-label="View class">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                </a>
                                @if ($activeClassTab === 'active')
                                    <button class="btn btn-outline-psu btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" data-bs-target="#editClassModal{{ $class->class_id }}" data-bs-toggle="modal" type="button" title="Edit class" aria-label="Edit class">
                                        <span class="material-symbols-outlined fs-6">edit</span>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" data-bs-target="#archiveClassModal{{ $class->class_id }}" data-bs-toggle="modal" type="button" title="Archive class" aria-label="Archive class">
                                        <span class="material-symbols-outlined fs-6">archive</span>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" data-bs-target="#deleteClassModal{{ $class->class_id }}" data-bs-toggle="modal" type="button" title="Delete class" aria-label="Delete class">
                                        <span class="material-symbols-outlined fs-6">delete</span>
                                    </button>
                                @else
                                    <form action="{{ route('instructor.classes.restore', $class) }}" method="POST" data-ajax-form>
                                        @csrf
                                        <button class="btn btn-outline-success btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" type="submit" title="Restore class" aria-label="Restore class">
                                            <span class="material-symbols-outlined fs-6">settings_backup_restore</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center py-5" colspan="6">
                            <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">school</span></div>
                            <h4 class="h4" style="color: var(--psu-navy);">{{ $activeClassTab === 'archived' ? 'No archived classes yet' : 'No classes yet' }}</h4>
                            <p class="text-secondary mb-4">{{ $activeClassTab === 'archived' ? 'Archived semester classes will appear here as records.' : 'Create your first class record based on the ERD fields.' }}</p>
                            @if ($activeClassTab === 'active')
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty())>
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Create First Class
                                </button>
                            @endif
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
