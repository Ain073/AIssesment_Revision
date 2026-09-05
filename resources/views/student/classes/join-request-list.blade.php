<div class="table-responsive">
    <table class="table table-hover mb-0 compact-data-table">
        <thead>
            <tr>
                <th>Class</th>
                <th>Teacher</th>
                <th>Requested</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($joinRequests as $joinRequest)
                <tr>
                    <td>
                        <div class="fw-bold" style="color: var(--psu-navy);">{{ $joinRequest->class?->class_name ?? 'Class removed' }}</div>
                        <div class="small text-secondary">{{ $joinRequest->class?->subject?->subject_code ?? 'No subject' }}</div>
                    </td>
                    <td>{{ $joinRequest->class?->instructorProfile?->user?->displayName() ?? 'Not assigned' }}</td>
                    <td>{{ $joinRequest->updated_at?->format('M d, Y g:i A') ?? 'Recently' }}</td>
                    <td>
                        @php
                            $badgeClass = match ($joinRequest->status) {
                                'approved' => 'text-bg-success',
                                'rejected' => 'text-bg-danger',
                                default => 'text-bg-warning',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} rounded-1">{{ ucfirst($joinRequest->status) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="text-center py-5" colspan="4">
                        <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">link</span></div>
                        <h3 class="h4" style="color: var(--psu-navy);">No join requests yet</h3>
                        <p class="text-secondary mb-0">Enter a class code or open a join link from your teacher to send one.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
