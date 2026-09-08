<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h4" id="addStudentModalLabel">Add Student to Class</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('instructor.classes.students.store', $class) }}" method="POST" data-ajax-form data-reset-on-success="true" data-reload-page-on-success="true">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="student_number">Student Number</label>
                        <input class="form-control form-control-lg" id="student_number" name="student_number" placeholder="e.g. 2024-00001" required type="text" value="{{ old('student_number') }}">
                    </div>
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit">
                            <span class="material-symbols-outlined fs-6">add</span>
                            Student
                        </button>
                    </div>
                </form>

                <div class="student-import-divider">or</div>

                <button
                    class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                    data-bs-toggle="collapse"
                    data-bs-target="#studentImportPanel"
                    type="button"
                    aria-expanded="{{ $errors->has('student_file') ? 'true' : 'false' }}"
                    aria-controls="studentImportPanel"
                >
                    <span class="material-symbols-outlined">upload_file</span>
                    Import Students
                </button>

                <div class="collapse {{ $errors->has('student_file') ? 'show' : '' }}" id="studentImportPanel">
                    <div class="student-import-panel">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2 small">
                                <span class="badge text-bg-light border">.txt</span>
                                <span class="badge text-bg-light border">.csv</span>
                                <span class="badge text-bg-light border">.xlsx</span>
                            </div>
                            <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.classes.students.import.sample', $class) }}" style="color: var(--psu-navy-2);">
                                Download Sample CSV
                            </a>
                        </div>

                        <form action="{{ route('instructor.classes.students.import.preview', $class) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold text-uppercase small" for="student_file">Roster File</label>
                                <input class="form-control" id="student_file" name="student_file" type="file" accept=".csv,.txt,.xlsx" required>
                                <div class="form-text">Maximum file size: 2 MB.</div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit">
                                    <span class="material-symbols-outlined fs-6">preview</span>
                                    Preview Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
            </div>
        </div>
    </div>
</div>
