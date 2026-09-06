<section class="dashboard-card">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">Departments</h3>
        <span class="badge text-bg-light border rounded-1">{{ $departmentRows->count() }} total</span>
    </div>

    @if ($departmentRows->isNotEmpty())
        <div class="dashboard-list">
            @foreach ($departmentRows->take(3) as $row)
                <article class="dashboard-list-item">
                    <div class="dashboard-list-title">{{ $row['department']->dept_name }}</div>
                    <div class="dashboard-list-meta">
                        {{ $row['programs_count'] }} programs &middot; {{ $row['teachers_count'] }} teachers
                    </div>
                    <div class="dashboard-list-meta mt-1">
                        Chair:
                        @if ($row['chairs']->isNotEmpty())
                            <span class="fw-semibold text-navy">
                                {{ $row['chairs']->map(fn ($chair) => $chair->displayName())->join(', ') }}
                            </span>
                        @else
                            <span class="fst-italic">No chair assigned</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="dashboard-card-footer d-flex align-items-center justify-content-between gap-2">
            <span class="small text-secondary">
                @if ($departmentRows->count() > 3)
                    + {{ $departmentRows->count() - 3 }} more
                @else
                    Latest department summary
                @endif
            </span>
            <a class="small fw-semibold text-decoration-none text-navy" href="{{ route('admin-dean.departments') }}">
                View all
            </a>
        </div>
    @else
        <div class="dashboard-empty">No departments found for this college.</div>
    @endif
</section>
