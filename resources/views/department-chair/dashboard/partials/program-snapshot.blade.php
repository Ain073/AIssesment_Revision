<section class="dashboard-card">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">Programs</h3>
        <span class="badge text-bg-light border rounded-1">{{ $programRows->count() }} total</span>
    </div>

    @if ($programRows->isNotEmpty())
        <div class="dashboard-list">
            @foreach ($programRows->take(3) as $row)
                <article class="dashboard-list-item">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="dashboard-list-title">{{ $row['program']->program_name }}</div>
                            <div class="dashboard-list-meta">
                                {{ $row['students_count'] }} students &middot; {{ $row['subjects_count'] }} subjects
                            </div>
                        </div>
                        <span class="badge {{ $row['program']->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                            {{ $row['program']->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($programRows->count() > 3)
            <div class="dashboard-card-footer">
                <span class="small text-secondary">+ {{ $programRows->count() - 3 }} more</span>
            </div>
        @endif
    @else
        <div class="dashboard-empty">No programs found for this department.</div>
    @endif
</section>
