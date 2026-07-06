<section class="profile-card">
    <div class="profile-card-header">
        <h3 class="brand-text h4 mb-1" style="color: var(--psu-navy);">Profile Picture</h3>
        <p class="text-secondary mb-0">Upload a clear photo for your account display.</p>
    </div>

    <form action="{{ route('profile.photo.update') }}" class="profile-card-body" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label fw-bold" for="profile_photo">Choose image</label>
            <input class="form-control @error('profile_photo') is-invalid @enderror" id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
            <div class="form-text">Accepted formats: JPG, PNG, or WEBP. Maximum file size: 2 MB.</div>
            @error('profile_photo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit">
            <span class="material-symbols-outlined fs-5">upload</span>
            Update Picture
        </button>
    </form>
</section>
