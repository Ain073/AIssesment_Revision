<section
    class="directory-card shadow-sm {{ $isActivePanel ? '' : 'd-none' }}"
    data-table-tab-panel="{{ $tabKey }}"
    @if (! $isActivePanel) hidden @endif
>
    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
        <h3 class="h4 mb-0">{{ $heading }}</h3>
        <span class="small text-white-50">
            {{ $instructorProfile?->department?->dept_name ?? 'No department assigned yet' }}
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 classes-table compact-data-table">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Subject</th>
                    <th class="text-center">Students</th>
                    <th class="text-center">Join Code</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($panelClasses as $class)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="class-icon"><span class="material-symbols-outlined">school</span></span>
                                <div>
                                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $class->displayName() }}</div>
                                    @if ($class->year_level)
                                        <div class="small text-secondary">Year Level {{ $class->year_level }}</div>
                                    @endif
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
                                <span class="text-secondary small">Open class to generate</span>
                            @endif
                        </td>
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex flex-nowrap align-items-center gap-2">
                                <a class="btn btn-outline-primary btn-sm action-icon-btn d-inline-flex align-items-center justify-content-center" href="{{ route('instructor.classes.show', ['class' => $class, 'tab' => 'students']) }}" title="View class" aria-label="View class">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                </a>
                                @if ($tabKey === 'active')
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
                        <td class="text-center py-5" colspan="5">
                            <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">school</span></div>
                            <h4 class="h4" style="color: var(--psu-navy);">{{ $emptyHeading }}</h4>
                            @if ($tabKey === 'active')
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
        <span class="small text-secondary">Showing {{ $panelClasses->count() }} {{ $panelClasses->count() === 1 ? 'entry' : 'entries' }}</span>
    </div>
</section>
