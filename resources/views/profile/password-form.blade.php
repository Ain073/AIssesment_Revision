<section class="profile-card">
    <div class="profile-card-header">
        <h3 class="brand-text h4 mb-1" style="color: var(--psu-navy);">Change Password</h3>
    </div>

    <form action="{{ route('profile.password.update') }}" class="profile-card-body" method="POST">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold" for="current_password">Current Password</label>
                <input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                @error('current_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold" for="password">New Password</label>
                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold" for="password_confirmation">Confirm New Password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
        </div>

        <button class="btn btn-psu d-inline-flex align-items-center gap-2 mt-3" type="submit">
            <span class="material-symbols-outlined fs-5">lock_reset</span>
            Update Password
        </button>
    </form>
</section>
