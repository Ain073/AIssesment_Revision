<section class="dashboard-card">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">Teaching Summary</h3>
        <a class="btn btn-sm btn-light d-inline-flex align-items-center gap-1 fw-semibold text-navy" href="{{ route('instructor.classes') }}">
            <span class="material-symbols-outlined fs-6">school</span>
            Classes
        </a>
    </div>

    <div class="p-3">
        <div class="dashboard-mini-metrics" aria-label="Teaching overview">
            @foreach ($teachingStats as $stat)
                <span class="dashboard-mini-metric">
                    <strong>{{ $stat['value'] }}</strong>{{ $stat['label'] }}
                </span>
            @endforeach
        </div>

        @if ($teachingClasses->isNotEmpty())
            <div class="dashboard-list px-0 pb-0">
                @foreach ($teachingClasses->take(3) as $row)
                    @php($class = $row['class'])
                    <article class="dashboard-list-item">
                        <div class="dashboard-list-title">{{ $class->displayName() }}</div>
                        <div class="dashboard-list-meta">
                            {{ $class->subject?->subject_code ?? 'No subject' }}
                            @if ($class->subject?->subject_name)
                                &middot; {{ $class->subject->subject_name }}
                            @endif
                        </div>
                        <div class="dashboard-list-meta mt-1">
                            {{ $class->students_count ?? $class->enrolledStudentsCount() }} students &middot; {{ $row['published_count'] }} published assessments
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="dashboard-empty border rounded-2 mt-3">No active teaching classes yet.</div>
        @endif
    </div>
</section>
