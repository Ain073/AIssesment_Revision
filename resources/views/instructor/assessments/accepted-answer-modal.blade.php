<div class="modal fade" id="acceptedAnswerModal" tabindex="-1" aria-labelledby="acceptedAnswerModalTitle" aria-hidden="true" data-accepted-answer-modal>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h4" id="acceptedAnswerModalTitle">Add Accepted Alternative</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close" data-alternative-close></button>
            </div>
            <div class="modal-body">
                <div class="alert d-none" role="status" aria-live="polite" data-alternative-message></div>
                <p class="text-secondary" data-alternative-loading>Loading accepted answers...</p>
                <div class="d-none" data-alternative-content>
                    <h3 class="h5" data-alternative-question></h3>
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Currently Accepted Answers</p>
                    <ul data-alternative-accepted></ul>
                    <p class="small text-secondary">Applies to all existing and future attempts using this published question. Student answers are preserved.</p>
                    <form data-alternative-preview-form>
                        @csrf
                        <label class="form-label fw-semibold" for="modalAlternativeAnswer">New Alternative Answer</label>
                        <input class="form-control" id="modalAlternativeAnswer" name="alternative_answer" type="text" maxlength="1000" required aria-describedby="modalAlternativeHelp" data-alternative-input>
                        <p class="form-text" id="modalAlternativeHelp">Enter one accepted spelling, singular/plural form, alias, or acronym. Up to 10 accepted answers including the primary answer.</p>
                        <button class="btn btn-outline-primary" type="submit" data-alternative-preview-button>Preview Score Changes</button>
                    </form>
                    <section class="border-top mt-4 pt-3 d-none" data-alternative-preview>
                        <h3 class="h5">Score Change Preview</h3>
                        <p class="mb-2" data-alternative-summary></p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead><tr><th scope="col">Student</th><th scope="col">Attempt</th><th scope="col">Answer</th><th scope="col">Question Score</th></tr></thead>
                                <tbody data-alternative-changes></tbody>
                            </table>
                        </div>
                        <p class="small text-secondary">Showing up to 50 affected attempts. Review previously saved report explanations or AI drafts after correcting scores.</p>
                        <form data-alternative-save-form>
                            @csrf
                            <input name="preview_token" type="hidden" data-alternative-token>
                            <button class="btn btn-psu" type="submit" data-alternative-save-button disabled>Save Alternative</button>
                        </form>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal" data-alternative-close>Close</button>
            </div>
        </div>
    </div>
</div>
