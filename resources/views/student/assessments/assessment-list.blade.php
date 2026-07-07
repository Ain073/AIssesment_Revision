@if ($classAssessments->isNotEmpty())
    <div class="row g-4">
        @foreach ($classAssessments as $classAssessment)
            @php
                $statusClass = match ($classAssessment->student_status) {
                    'available' => 'text-bg-success',
                    'completed' => 'text-bg-secondary',
                    default => 'text-bg-warning',
                };
            @endphp

            <div class="col-xl-6">
                <section class="assessment-card h-100">
                    <div class="directory-header px-4 py-3 d-flex justify-content-between gap-3">
                        <div>
                            <h2 class="h4 mb-1">{{ $classAssessment->assessment?->title ?? 'Untitled Assessment' }}</h2>
                            <p class="small text-white-50 mb-0">{{ $classAssessment->class?->class_name ?? 'Class' }}</p>
                        </div>
                        <span class="badge {{ $statusClass }} rounded-1 align-self-start">{{ ucfirst($classAssessment->student_status) }}</span>
                    </div>

                    <div class="p-4">
                        <div class="assessment-meta mb-3">
                            <span class="badge text-bg-primary rounded-1">{{ $classAssessment->assessment?->subject?->subject_code ?? 'No subject' }}</span>
                            @php $itemCount = $classAssessment->assessment?->items?->count() ?? 0; @endphp
                            <span class="badge text-bg-light border rounded-1">{{ $itemCount }} item{{ $itemCount === 1 ? '' : 's' }}</span>
                            <span class="badge text-bg-light border rounded-1">{{ $classAssessment->attempt_limit }} attempt{{ $classAssessment->attempt_limit === 1 ? '' : 's' }}</span>
                        </div>

                        <p class="text-secondary mb-3">
                            Teacher: {{ $classAssessment->class?->instructorProfile?->user?->displayName() ?? 'Not assigned' }}
                        </p>

                        <div class="d-grid gap-2 small mb-4">
                            <div>
                                <span class="fw-bold text-secondary text-uppercase">Available:</span>
                                {{ $classAssessment->available_at?->format('M d, Y h:i A') ?? 'Now' }}
                            </div>
                            <div>
                                <span class="fw-bold text-secondary text-uppercase">Due:</span>
                                {{ $classAssessment->due_at?->format('M d, Y h:i A') ?? 'No due date' }}
                            </div>
                        </div>

                        @if ($classAssessment->student_status === 'available')
                            <a class="btn btn-psu portal-ajax-link d-inline-flex align-items-center gap-2" href="{{ route('student.assessments.take', $classAssessment) }}">
                                <span class="material-symbols-outlined fs-5">edit_document</span>
                                Take Assessment
                            </a>
                        @else
                            <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="button" disabled>
                                <span class="material-symbols-outlined fs-5">lock</span>
                                Take Assessment
                            </button>
                        @endif
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@else
    <section class="directory-card p-5 text-center">
        <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">assignment</span></div>
        <h2 class="h4" style="color: var(--psu-navy);">No pending or ongoing assessments</h2>
    </section>
@endif
