@if ($pendingJoinRequests->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-hover mb-0 compact-data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Requested</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pendingJoinRequests as $joinRequest)
                    @php($student = $joinRequest->studentProfile)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar">{{ strtoupper(substr($student?->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                <div>
                                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $student?->user?->displayName() ?? 'Unknown student' }}</div>
                                    <div class="small text-secondary">{{ $student?->student_number ?? 'No student number' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($student?->program)
                                <div class="fw-semibold">{{ $student->program->program_name }}</div>
                                <div class="small text-secondary">{{ collect([$student->program->department?->dept_name, $student->program->department?->college?->college_name])->filter()->join(' - ') }}</div>
                            @else
                                <span class="text-secondary">Not assigned</span>
                            @endif
                        </td>
                        <td>{{ $joinRequest->updated_at?->format('M d, Y g:i A') ?? 'Recently' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                <form action="{{ route('instructor.classes.join-requests.approve', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" data-ajax-form>
                                    @csrf
                                    <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                        <span class="material-symbols-outlined fs-6">check</span>
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('instructor.classes.join-requests.reject', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" onsubmit="return confirm('Reject this join request?');" data-ajax-form>
                                    @csrf
                                    <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                        <span class="material-symbols-outlined fs-6">close</span>
                                        Reject
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
