@once
    @push('styles')
        <x-page-style path="css/pages/partials/student-account-import.css" />
    @endpush
@endonce

<div class="modal fade" id="importStudentsModal" tabindex="-1" aria-labelledby="importStudentsModalLabel" aria-hidden="true" @if ($errors->studentImport->any() && ! $studentImportPreview) data-student-import-auto-open @endif>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ $studentImportPreviewRoute }}" class="modal-content" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="modal-header student-import-modal-header">
                <h3 class="modal-title h4" id="importStudentsModalLabel">Import Student Accounts</h3>
            </div>

            <div class="modal-body p-4">
                <div class="student-create-mode" role="group" aria-label="Student account creation method">
                    <button class="btn" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button">
                        <span class="material-symbols-outlined fs-5">person</span>
                        Manual Entry
                    </button>
                    <button class="btn active" type="button" aria-current="true">
                        <span class="material-symbols-outlined fs-5">upload_file</span>
                        Import File
                    </button>
                </div>

                @if ($errors->studentImport->any())
                    <div class="alert alert-danger">{{ $errors->studentImport->first() }}</div>
                @endif

                <div class="mb-3">
                    <label class="form-label fw-bold text-uppercase small" for="student_import_program">Program</label>
                    <select class="form-select form-select-lg" id="student_import_program" name="import_program_id" required>
                        <option value="">Select program</option>
                        @foreach ($studentImportPrograms as $program)
                            <option value="{{ $program->program_id }}" @selected((string) old('import_program_id') === (string) $program->program_id)>
                                {{ $program->program_name }}@if ($program->department) - {{ $program->department->dept_name }}@endif @if ($program->department?->college) - {{ $program->department->college->shortName() }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border">.txt</span>
                        <span class="badge text-bg-light border">.csv</span>
                        <span class="badge text-bg-light border">.xlsx</span>
                    </div>
                    <a class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ $studentImportSampleRoute }}">
                        <span class="material-symbols-outlined fs-6">download</span>
                        Sample CSV
                    </a>
                </div>

                <div class="student-import-dropzone">
                    <label class="form-label fw-bold text-uppercase small" for="student_account_file">Student File</label>
                    <input class="form-control form-control-lg" id="student_account_file" name="student_file" type="file" accept=".csv,.txt,.xlsx" required>
                    <div class="form-text">Maximum 200 records and 2 MB per file.</div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit">
                    <span class="material-symbols-outlined fs-6">preview</span>
                    Preview Import
                </button>
            </div>
        </form>
    </div>
</div>

@if ($studentImportPreview)
    <div class="modal fade" id="studentImportPreviewModal" tabindex="-1" aria-labelledby="studentImportPreviewModalLabel" aria-hidden="true" data-student-import-auto-open>
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" action="{{ $studentImportReviewRoute }}" method="POST" data-student-import-review-form data-ready-count="{{ $studentImportPreview['summary']['ready'] ?? 0 }}" novalidate>
                @csrf
                <input name="import_token" type="hidden" value="{{ $studentImportPreview['token'] }}">
                <input name="draft_rows" type="hidden" value="{{ old('import_token') === $studentImportPreview['token'] && is_string(old('draft_rows')) ? old('draft_rows') : '' }}">
                <div class="modal-header student-import-modal-header">
                    <h3 class="modal-title h4" id="studentImportPreviewModalLabel">Student Import Preview</h3>
                </div>

                <div class="modal-body p-4">
                    <p class="small text-secondary">Use Edit to correct a record, then select Save &amp; Validate. Accounts are created only when you select Create Accounts.</p>
                    @if ($errors->studentImport->any())
                        <div class="alert alert-danger" role="alert">{{ $errors->studentImport->first() }}</div>
                    @endif
                    <div class="alert alert-warning d-none" data-import-pending role="status">Changes need validation. Select Save &amp; Validate before creating accounts. The counts below show the last validation.</div>
                    <noscript><div class="alert alert-warning">Enable JavaScript to edit and confirm this import.</div></noscript>
                    <div class="row g-3 mb-4">
                        @foreach ([
                            ['label' => 'Records', 'key' => 'total', 'class' => ''],
                            ['label' => 'Ready', 'key' => 'ready', 'class' => 'text-success'],
                            ['label' => 'Duplicate', 'key' => 'duplicate', 'class' => 'text-warning'],
                            ['label' => 'Invalid', 'key' => 'invalid', 'class' => 'text-danger'],
                        ] as $summaryItem)
                            <div class="col-6 col-lg-3">
                                <div class="student-import-summary h-100">
                                    <div class="small text-secondary text-uppercase fw-bold mb-2">{{ $summaryItem['label'] }}</div>
                                    <div class="student-import-summary-value {{ $summaryItem['class'] }}" @if ($summaryItem['key'] === 'total') style="color: var(--psu-navy);" @endif>
                                        {{ $studentImportPreview['summary'][$summaryItem['key']] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="table-responsive border rounded-2">
                        <table class="table table-hover align-middle mb-0 student-import-preview-table">
                            <thead>
                                <tr>
                                    <th>Line</th>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th>Validation</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentImportPreview['rows'] as $rowIndex => $row)
                                    <tr data-import-preview-row>
                                        <td data-label="Line">{{ $row['line'] }}</td>
                                        <td data-label="Student">
                                            <div class="fw-semibold">{{ $row['student_name'] ?: 'Missing name' }}</div>
                                            <div class="small text-secondary">{{ $row['student_number'] ?: 'No student number' }}</div>
                                        </td>
                                        <td data-label="Email">{{ $row['email'] ?: 'No email' }}</td>
                                        <td data-label="Program">{{ $row['program_name'] ?: 'No program' }}</td>
                                        <td data-label="Validation">
                                            <span class="badge {{ $row['result_class'] }} rounded-1" data-import-row-validation="{{ $rowIndex }}">{{ $row['result'] }}</span>
                                            <div class="small text-secondary mt-1" data-import-row-message="{{ $rowIndex }}">{{ $row['message'] }}</div>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-bs-toggle="collapse" data-bs-target="#studentImportEditor{{ $rowIndex }}" aria-expanded="false" aria-controls="studentImportEditor{{ $rowIndex }}" aria-label="Edit record on line {{ $row['line'] }}" type="button">
                                                <span class="material-symbols-outlined fs-6" aria-hidden="true">edit</span> Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-0 border-0" colspan="6">
                                            <div class="collapse" id="studentImportEditor{{ $rowIndex }}">
                                                <div class="p-3 border-bottom" style="background: #f8faff;" data-import-editor-row="{{ $rowIndex }}">
                                                    <div class="row g-3">
                                                        @foreach (['first_name' => 'First Name', 'middle_name' => 'Middle Name (optional)', 'last_name' => 'Last Name', 'student_number' => 'Student Number', 'email' => 'Email'] as $field => $label)
                                                            <div class="{{ in_array($field, ['student_number', 'email'], true) ? 'col-sm-6' : 'col-sm-4' }}">
                                                                <label class="form-label small fw-semibold" for="studentImport{{ $rowIndex }}_{{ $field }}">{{ $label }}</label>
                                                                <input class="form-control" id="studentImport{{ $rowIndex }}_{{ $field }}" data-import-field="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" maxlength="255" value="{{ $row[$field] ?? '' }}" autocomplete="off">
                                                            </div>
                                                        @endforeach
                                                        <div class="col-12">
                                                            <label class="form-label small fw-semibold" for="studentImport{{ $rowIndex }}_program">Program</label>
                                                            <select class="form-select" id="studentImport{{ $rowIndex }}_program" data-import-field="program_id">
                                                                <option value="">Select program</option>
                                                                @foreach ($studentImportPrograms as $program)
                                                                    <option value="{{ $program->program_id }}" @selected((int) ($row['program_id'] ?? 0) === (int) $program->program_id)>{{ $program->program_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" type="submit" data-import-review disabled>
                        <span class="material-symbols-outlined fs-6" aria-hidden="true">fact_check</span> Save &amp; Validate
                    </button>
                    <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" formaction="{{ $studentImportConfirmRoute }}" type="submit" data-import-create disabled>
                        <span class="material-symbols-outlined fs-6" aria-hidden="true">person_add</span>
                        Create {{ $studentImportPreview['summary']['ready'] ?? 0 }} Accounts
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
