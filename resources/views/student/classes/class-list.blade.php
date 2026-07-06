<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="brand-text mb-1" style="color: var(--psu-navy);">My Classes</h1>
        <p class="text-secondary mb-0">Classes appear here after your teacher adds you or approves your join request.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
        <span class="badge text-bg-primary rounded-1 px-3 py-2">
            {{ $enrolledClasses->count() }} enrolled
        </span>
        @if (($archivedClasses ?? collect())->isNotEmpty())
            <span class="badge text-bg-light border rounded-1 px-3 py-2">
                {{ $archivedClasses->count() }} past
            </span>
        @endif
        <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-target="#joinRequestsModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">pending_actions</span>
            Requests
            <span class="badge text-bg-light border rounded-1">{{ $joinRequests->count() }}</span>
        </button>
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#joinClassModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">login</span>
            Join Class
        </button>
    </div>
</div>

@if ($enrolledClasses->isNotEmpty())
    <div class="row g-4 mb-4">
        @foreach ($enrolledClasses as $class)
            <div class="col-lg-6">
                <section class="class-card p-4 h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <p class="small fw-bold text-secondary text-uppercase mb-2">{{ $class->subject?->subject_code ?? 'No subject' }}</p>
                            <h2 class="h4 mb-2" style="color: var(--psu-navy);">{{ $class->class_name }}</h2>
                            <p class="text-secondary mb-3">{{ $class->subject?->subject_name ?? 'Subject not assigned' }}</p>
                        </div>
                        <span class="material-symbols-outlined fs-2" style="color: var(--psu-navy-2);">school</span>
                    </div>
                    <div class="d-grid gap-2 small">
                        <div>
                            <span class="fw-bold text-secondary text-uppercase">Teacher:</span>
                            {{ $class->instructorProfile?->user?->displayName() ?? 'Not assigned' }}
                        </div>
                        <div>
                            <span class="fw-bold text-secondary text-uppercase">School Year:</span>
                            {{ $class->school_year }}
                        </div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@else
    <section class="class-card p-5 text-center mb-4">
        <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">school</span></div>
        <h2 class="h4" style="color: var(--psu-navy);">No enrolled classes yet</h2>
        <p class="text-secondary mb-0">Enter the class code or open the join link from your teacher, then wait for approval.</p>
    </section>
@endif

@if (($archivedClasses ?? collect())->isNotEmpty())
    <section class="class-card p-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="small fw-bold text-secondary text-uppercase mb-1">Records</p>
                <h2 class="h4 mb-0" style="color: var(--psu-navy);">Past Classes</h2>
            </div>
            <span class="material-symbols-outlined fs-2" style="color: var(--psu-navy-2);">inventory_2</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>School Year</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($archivedClasses as $class)
                        <tr>
                            <td class="fw-bold" style="color: var(--psu-navy);">{{ $class->class_name }}</td>
                            <td>
                                <div class="fw-semibold">{{ $class->subject?->subject_code ?? 'No subject' }}</div>
                                <div class="small text-secondary">{{ $class->subject?->subject_name ?? 'Subject not assigned' }}</div>
                            </td>
                            <td>{{ $class->instructorProfile?->user?->displayName() ?? 'Not assigned' }}</td>
                            <td>{{ $class->school_year }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
