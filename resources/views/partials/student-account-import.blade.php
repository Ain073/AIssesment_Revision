@once
    @push('styles')
        <style>
            .student-import-modal-header {
                background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
                color: #fff;
            }

            .student-import-dropzone,
            .student-import-summary {
                background: #f8faff;
                border: 1px solid var(--psu-line);
                border-radius: 0.5rem;
            }

            .student-import-dropzone {
                padding: 1.25rem;
            }

            .student-import-summary {
                padding: 1rem;
            }

            .student-import-summary-value {
                font-size: 1.5rem;
                font-weight: 800;
                line-height: 1;
            }

            .student-create-mode {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.35rem;
                padding: 0.35rem;
                margin-bottom: 1.25rem;
                background: #edf2ff;
                border-radius: 0.5rem;
            }

            .student-create-mode .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.45rem;
                border: 0;
                font-weight: 700;
            }

            .student-create-mode .btn.active {
                background: #fff;
                color: var(--psu-navy);
                box-shadow: 0 2px 8px rgba(0, 26, 112, 0.1);
            }
        </style>
    @endpush

    @push('scripts')
        @if ($errors->studentImport->any())
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modal = document.getElementById('importStudentsModal');

                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                });
            </script>
        @endif

        @if ($studentImportPreview)
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modal = document.getElementById('studentImportPreviewModal');

                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                });
            </script>
        @endif
    @endpush
@endonce

<div class="modal fade" id="importStudentsModal" tabindex="-1" aria-labelledby="importStudentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ $studentImportPreviewRoute }}" class="modal-content" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="modal-header student-import-modal-header">
                <h3 class="modal-title h4" id="importStudentsModalLabel">Import Student Accounts</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
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
    <div class="modal fade" id="studentImportPreviewModal" tabindex="-1" aria-labelledby="studentImportPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header student-import-modal-header">
                    <h3 class="modal-title h4" id="studentImportPreviewModalLabel">Student Import Preview</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
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
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Line</th>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th>Account</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentImportPreview['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['line'] }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $row['student_name'] ?: 'Missing name' }}</div>
                                            <div class="small text-secondary">{{ $row['student_number'] ?: 'No student number' }}</div>
                                        </td>
                                        <td>{{ $row['email'] ?: 'No email' }}</td>
                                        <td>{{ $row['program_name'] ?: 'No program' }}</td>
                                        <td>{{ $row['status'] }}</td>
                                        <td style="min-width: 190px;">
                                            <span class="badge {{ $row['result_class'] }} rounded-1">{{ $row['result'] }}</span>
                                            @if ($row['message'])
                                                <div class="small text-secondary mt-1">{{ $row['message'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                    <form action="{{ $studentImportConfirmRoute }}" method="POST">
                        @csrf
                        <input name="import_token" type="hidden" value="{{ $studentImportPreview['token'] }}">
                        <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit" @disabled(($studentImportPreview['summary']['ready'] ?? 0) === 0)>
                            <span class="material-symbols-outlined fs-6">person_add</span>
                            Create {{ $studentImportPreview['summary']['ready'] ?? 0 }} Accounts
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
