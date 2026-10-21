@if ($importPreview)
    @php
        $previousExclusions = old('import_token') === $importPreview['token'] && is_array(old('excluded_rows')) ? old('excluded_rows') : [];
        $previousExclusions = array_map('intval', array_filter($previousExclusions, fn ($value) => is_int($value) || (is_string($value) && ctype_digit($value))));
    @endphp
    <div class="modal fade" id="importPreviewModal" tabindex="-1" aria-labelledby="importPreviewModalLabel" aria-hidden="true" data-class-roster-import-auto-open>
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" action="{{ route('instructor.classes.students.import.confirm', $class) }}" method="POST" data-class-roster-import>
                @csrf
                <input type="hidden" name="import_token" value="{{ $importPreview['token'] }}">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="importPreviewModalLabel">Import Preview</h3>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <p class="small text-secondary mb-0">Remove extra IDs before confirming. Undo restores them to this preview.</p>
                        <span class="badge text-bg-light border">Removed from import: <span data-roster-excluded-count>0</span></span>
                    </div>
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
                                <div class="display-6 fw-bold text-success" data-roster-ready-count>{{ $importPreview['summary']['ready'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card p-3 h-100">
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Already Enrolled</p>
                                <div class="display-6 fw-bold text-info" data-roster-enrolled-count>{{ $importPreview['summary']['already_enrolled'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card p-3 h-100">
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Needs Review</p>
                                <div class="display-6 fw-bold text-warning" data-roster-review-count>{{ ($importPreview['summary']['inactive'] ?? 0) + ($importPreview['summary']['not_found'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 students-table class-import-preview-table">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importPreview['rows'] as $rowIndex => $row)
                                    <tr data-roster-row data-roster-status="{{ $row['status_key'] ?? (['Ready' => 'ready', 'Already Enrolled' => 'already_enrolled', 'Inactive' => 'inactive', 'Not Found' => 'not_found'][$row['status_badge']] ?? '') }}">
                                        <td class="class-import-identity" data-label="Student Number">{{ $row['student_number'] }}</td>
                                        <td class="class-import-identity" data-label="Student">{{ $row['student_name'] }}</td>
                                        <td class="class-import-identity" data-label="Program">{{ $row['program_name'] }}</td>
                                        <td data-label="Status"><span class="badge {{ $row['status_class'] }} rounded-1" data-roster-status-badge>{{ $row['status_badge'] }}</span></td>
                                        <td class="text-end">
                                            <input type="hidden" name="excluded_rows[]" value="{{ $rowIndex }}" data-roster-exclusion @disabled(! in_array($rowIndex, $previousExclusions, true))>
                                            <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 text-nowrap" data-roster-toggle type="button" disabled>
                                                <span class="material-symbols-outlined fs-6" data-roster-toggle-icon aria-hidden="true">remove_circle_outline</span>
                                                <span data-roster-toggle-label>Remove from Import</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit" data-roster-confirm @disabled(($importPreview['summary']['ready'] ?? 0) === 0)>
                        Confirm Import (<span data-roster-confirm-count>{{ $importPreview['summary']['ready'] ?? 0 }}</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
