<section class="profile-card">
    <div class="profile-card-header">
        <h3 class="brand-text h4 mb-1" style="color: var(--psu-navy);">Profile Picture</h3>
    </div>

    <form action="{{ route('profile.photo.update') }}" class="profile-card-body" method="POST" enctype="multipart/form-data" data-profile-photo-form>
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

        <div class="mb-3" data-photo-preview hidden>
            <img class="rounded border" data-photo-preview-image alt="Selected profile picture preview" width="96" height="96" style="object-fit: cover;">
            <p class="form-text mb-0">Preview only. Click Update Picture to save.</p>
        </div>
        <div class="mb-3" data-photo-upload-progress hidden>
            <progress class="w-100" data-photo-progress max="100" value="0" aria-label="Profile picture upload progress"></progress>
            <p class="small mb-0" data-photo-upload-status role="status" aria-live="polite"></p>
        </div>
        <div class="mb-3" data-photo-upload-feedback role="status" aria-live="polite" hidden></div>

        <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit">
            <span class="material-symbols-outlined fs-5">upload</span>
            Update Picture
        </button>
    </form>
</section>
