<section class="directory-card shadow-sm">
    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
        <h3 class="h4 mb-0">Students in Class</h3>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 students-table compact-data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Performance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($enrolledStudents as $student)
                    @php($performance = $studentPerformance->get($student->student_profile_id))
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar">{{ strtoupper(substr($student->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                <div>
                                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $student->user?->displayName() ?? 'Unnamed student' }}</div>
                                    <div class="small text-secondary">{{ $student->student_number ?? 'No student number' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($student->program)
                                <div class="fw-semibold">{{ $student->program->program_name }}</div>
                                <div class="small text-secondary">{{ collect([$student->program->department?->dept_name, $student->program->department?->college?->college_name])->filter()->join(' - ') }}</div>
                            @else
                                <span class="text-secondary">Not assigned</span>
                            @endif
                        </td>
                        <td>
                            @if ($performance['has_results'])
                                <div class="student-performance">
                                    <div class="student-performance-row">
                                        <div
                                            class="student-performance-track {{ $performance['passed'] ? 'passed' : 'failed' }}"
                                            role="img"
                                            aria-label="{{ $performance['percentage'] }} percent performance"
                                        >
                                            <span
                                                class="student-performance-fill {{ $performance['passed'] ? 'passed' : 'failed' }}"
                                                style="width: {{ $performance['percentage'] }}%;"
                                            ></span>
                                        </div>
                                        <span class="student-performance-percent {{ $performance['passed'] ? 'text-success' : 'text-danger' }}">
                                            {{ $performance['percentage'] }}%
                                        </span>
                                    </div>
                                </div>
                            @else
                                <span class="text-secondary small">No results</span>
                            @endif
                        </td>
                        <td>
                            @if (! $class->archived_at)
                                <form action="{{ route('instructor.classes.students.destroy', ['class' => $class, 'studentProfile' => $student]) }}" method="POST" onsubmit="return confirm('Remove this student from the class?');" data-ajax-form>
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                        <span class="material-symbols-outlined fs-6">delete</span>
                                        Remove
                                    </button>
                                </form>
                            @else
                                <span class="text-secondary small">Record only</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center py-5" colspan="4">
                            <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">groups</span></div>
                            <h4 class="h4" style="color: var(--psu-navy);">No students enrolled yet</h4>
                            @if (! $class->archived_at)
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#addStudentModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Student
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
        <span class="small text-secondary">Showing {{ $enrolledStudents->count() }} {{ $enrolledStudents->count() === 1 ? 'student' : 'students' }}</span>
    </div>
</section>
