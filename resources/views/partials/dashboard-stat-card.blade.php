<article class="dashboard-stat">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <p class="dashboard-stat-label">{{ $stat['label'] }}</p>
            <div class="dashboard-stat-value">{{ $stat['value'] }}</div>
        </div>
        <span class="icon-tile">
            <span class="material-symbols-outlined">{{ $stat['icon'] }}</span>
        </span>
    </div>
</article>
