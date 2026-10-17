(() => {
    const states = new WeakMap();
    let activeUpload = null;
    const stateFor = (form) => {
        if (! states.has(form)) states.set(form, { previewUrl: null, xhr: null });
        return states.get(form);
    };
    const field = (form) => form.querySelector('input[name="profile_photo"]');
    const feedback = (form, text, tone = 'danger') => {
        if (! form.isConnected) return;
        const box = form.querySelector('[data-photo-upload-feedback]');
        box.textContent = text;
        box.className = `alert alert-${tone} mb-3`;
        box.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
        box.hidden = false;
    };
    const progress = (form, percent, text) => {
        if (! form.isConnected) return;
        form.querySelector('[data-photo-upload-progress]').hidden = false;
        const bar = form.querySelector('[data-photo-progress]');
        if (percent === null) bar.removeAttribute('value');
        else bar.value = percent;
        form.querySelector('[data-photo-upload-status]').textContent = text;
    };
    const releasePreview = (form) => {
        const state = states.get(form);
        if (state?.previewUrl) {
            URL.revokeObjectURL(state.previewUrl);
            state.previewUrl = null;
        }
        form.querySelector('[data-photo-preview]').hidden = true;
        form.querySelector('[data-photo-preview-image]').removeAttribute('src');
    };
    const fileError = (file) => {
        if (! file) return 'Choose an image first.';
        if (file.size > 2 * 1024 * 1024) return 'The image must be 2 MB or smaller.';
        if (! ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
            && ! (! file.type && /\.(jpe?g|png|webp)$/i.test(file.name))) {
            return 'Choose a JPG, PNG, or WEBP image.';
        }
        return null;
    };
    const clearForm = (form) => {
        if (stateFor(form).xhr) return;
        releasePreview(form);
        field(form).value = '';
        form.querySelector('[data-photo-upload-feedback]').hidden = true;
        form.querySelector('[data-photo-upload-progress]').hidden = true;
    };

    document.addEventListener('change', (event) => {
        const input = event.target.closest('input[name="profile_photo"]');
        const form = input?.closest('[data-profile-photo-form]');
        if (! form || stateFor(form).xhr) return;
        releasePreview(form);
        form.querySelector('[data-photo-upload-feedback]').hidden = true;
        form.querySelector('[data-photo-upload-progress]').hidden = true;
        const file = input.files[0];
        if (! file) return;
        const error = fileError(file);
        if (error) return feedback(form, error);
        try {
            const state = stateFor(form);
            state.previewUrl = URL.createObjectURL(file);
            form.querySelector('[data-photo-preview-image]').src = state.previewUrl;
            form.querySelector('[data-photo-preview]').hidden = false;
        } catch (error) {
            feedback(form, 'Preview is unavailable. You can still upload the image.', 'warning');
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-profile-photo-form]');
        if (! form) return;
        event.preventDefault();
        if (activeUpload) {
            if (! stateFor(form).xhr) feedback(form, 'Another picture upload is still running. Please wait.', 'warning');
            return;
        }
        const error = fileError(field(form).files[0]);
        if (error) return feedback(form, error);

        const state = stateFor(form);
        const controls = Array.from(form.querySelectorAll('input[type="file"], button[type="submit"]'),
            (control) => ({ control, disabled: control.disabled }));
        let xhr = null;
        let finished = false;
        const finish = () => {
            if (finished) return;
            finished = true;
            if (activeUpload === xhr) activeUpload = null;
            state.xhr = null;
            delete form.dataset.photoUploading;
            form.removeAttribute('aria-busy');
            controls.forEach(({ control, disabled }) => { control.disabled = disabled; });
            if (! form.isConnected) releasePreview(form);
        };
        const uncertain = () => {
            feedback(form, 'Upload could not be confirmed. Check your connection and refresh your profile before retrying.');
            progress(form, 0, 'Upload not confirmed.');
            finish();
        };
        try {
            // Capture the file before disabling inputs; disabled fields are omitted from FormData.
            const body = new FormData(form);
            xhr = new XMLHttpRequest();
            xhr.open(form.getAttribute('method') || 'POST', form.getAttribute('action'));
            xhr.responseType = 'json';
            xhr.timeout = 60000;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            state.xhr = activeUpload = xhr;
            form.dataset.photoUploading = 'true';
            form.setAttribute('aria-busy', 'true');
            controls.forEach(({ control }) => { control.disabled = true; });
            form.querySelector('[data-photo-upload-feedback]').hidden = true;
            progress(form, 0, 'Uploading...');

            xhr.upload.addEventListener('progress', (event) => {
                const percent = event.lengthComputable && event.total > 0
                    ? Math.min(100, Math.floor(event.loaded / event.total * 100)) : null;
                progress(form, percent, percent === null ? 'Uploading...' : `Uploading: ${percent}%`);
            });
            xhr.upload.addEventListener('load', () => progress(form, 100, 'Upload complete. Saving picture...'));
            xhr.addEventListener('load', () => {
                try {
                    const data = xhr.response && typeof xhr.response === 'object' ? xhr.response : {};
                    window.handlePortalSessionResponse?.(xhr.status, data);
                    if (xhr.status < 200 || xhr.status >= 300) {
                        const message = xhr.status === 413 ? 'The image must be 2 MB or smaller.'
                            : (xhr.status < 500 ? data.errors?.profile_photo?.[0] || data.message : null);
                        if (! message) return uncertain();
                        feedback(form, message);
                        progress(form, 0, 'Picture was not updated.');
                        return;
                    }
                    if (! (xhr.getResponseHeader('Content-Type') || '').includes('application/json')
                        || typeof data.photo_url !== 'string' || ! data.photo_url) return uncertain();
                    const photoUrl = new URL(data.photo_url, window.location.href);
                    if (! ['http:', 'https:'].includes(photoUrl.protocol)) return uncertain();
                    document.querySelectorAll('[data-profile-photo-display]').forEach((container) => {
                        const image = document.createElement('img');
                        image.src = photoUrl.href;
                        image.alt = container.dataset.profilePhotoAlt || 'Profile picture';
                        container.replaceChildren(image);
                    });
                    field(form).value = '';
                    releasePreview(form);
                    progress(form, 100, 'Saved.');
                    feedback(form, 'Profile picture updated.', 'success');
                } catch (error) {
                    uncertain();
                } finally {
                    finish();
                }
            });
            ['error', 'timeout', 'abort'].forEach((event) => xhr.addEventListener(event, uncertain));
            xhr.send(body);
        } catch (error) {
            uncertain();
        }
    });

    document.addEventListener('reset', (event) => {
        if (event.target.matches('[data-profile-photo-form]')) clearForm(event.target);
    });
    document.addEventListener('hidden.bs.modal', (event) => {
        event.target.querySelectorAll('[data-profile-photo-form]').forEach(clearForm);
    });
    window.cleanupProfilePhotoPreviews = (root) => {
        root.querySelectorAll('[data-profile-photo-form]').forEach(releasePreview);
    };
})();
