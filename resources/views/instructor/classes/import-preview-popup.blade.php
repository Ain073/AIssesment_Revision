@if ($importPreview)
    <div class="modal fade" id="importPreviewModal" tabindex="-1" aria-labelledby="importPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="importPreviewModalLabel">Import Preview</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card p-3 h-100">
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Rows Found</p>
                                <div class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $importPreview['summary']['total'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card p-3 h-100">
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Ready</p>
                                <div class="display-6 fw-bold text-success">{{ $importPreview['summary']['ready'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card p-3 h-100">
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Already Enrolled</p>
                                <div class="display-6 fw-bold text-info">{{ $importPreview['summary']['already_enrolled'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card p-3 h-100">
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Needs Review</p>
                                <div class="display-6 fw-bold text-warning">{{ ($importPreview['summary']['inactive'] ?? 0) + ($importPreview['summary']['not_found'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 students-table">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importPreview['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['student_number'] }}</td>
                                        <td>{{ $row['student_name'] }}</td>
                                        <td>{{ $row['program_name'] }}</td>
                                        <td><span class="badge {{ $row['status_class'] }} rounded-1">{{ $row['status_badge'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <form action="{{ route('instructor.classes.students.import.confirm', $class) }}" method="POST">
                        @csrf
                        <input type="hidden" name="import_token" value="{{ $importPreview['token'] }}">
                        <button class="btn btn-psu px-4" type="submit" @disabled(($importPreview['summary']['ready'] ?? 0) === 0)>
                            Confirm Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
