<div class="modal fade" id="securitySettingsModal" tabindex="-1" aria-labelledby="securitySettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header publish-header">
                <div>
                    <h3 class="modal-title h4" id="securitySettingsModalLabel">Security Settings</h3>
                </div>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-3">
                    <label class="security-box mb-0">
                        <input class="form-check-input mt-1" name="prevent_copy_paste" type="checkbox" value="1" @checked(old('prevent_copy_paste'))>
                        <span>
                            <span class="fw-bold d-block" style="color: var(--psu-navy);">No Copy / Paste</span>
                            <span class="small text-secondary">Blocks copy, paste, cut, and right click.</span>
                        </span>
                    </label>

                    <label class="security-box mb-0">
                        <input class="form-check-input mt-1" name="detect_tab_switch" type="checkbox" value="1" @checked(old('detect_tab_switch'))>
                        <span>
                            <span class="fw-bold d-block" style="color: var(--psu-navy);">Detect Tab Switch</span>
                            <span class="small text-secondary">Adds warning when student leaves the assessment tab.</span>
                        </span>
                    </label>

                    <label class="security-box mb-0">
                        <input class="form-check-input mt-1" name="screenshot_protection" type="checkbox" value="1" @checked(old('screenshot_protection'))>
                        <span>
                            <span class="fw-bold d-block" style="color: var(--psu-navy);">Screenshot Protection</span>
                            <span class="small text-secondary">Blocks print/screenshot shortcuts when possible and records a warning.</span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-psu px-4" data-bs-dismiss="modal" type="button">Done</button>
            </div>
        </div>
    </div>
</div>
