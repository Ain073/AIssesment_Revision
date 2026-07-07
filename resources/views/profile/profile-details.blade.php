<section class="profile-card">
    <div class="profile-card-header">
        <h3 class="brand-text h4 mb-1" style="color: var(--psu-navy);">Account Details</h3>
    </div>

    <div class="profile-card-body">
        <div class="profile-detail-list">
            @foreach ($detailRows as $row)
                <div class="profile-detail-item">
                    <p class="profile-detail-label">{{ $row['label'] }}</p>
                    <p class="profile-detail-value">{{ $row['value'] ?: 'Not assigned' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
