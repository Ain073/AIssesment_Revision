{{-- Create department form --}}
<div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('super-admin.departments.store') }}" class="modal-content" method="POST">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title h4" id="departmentModalLabel">New Department</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold text-uppercase small" for="college_id">College</label>
                    <select class="form-select form-select-lg" id="college_id" name="college_id" required @disabled($colleges->isEmpty())>
                        @forelse ($colleges as $college)
                            <option value="{{ $college->college_id }}" @selected(old('college_id') == $college->college_id)>{{ $college->college_name }}</option>
                        @empty
                            <option>No colleges available yet</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="form-label fw-bold text-uppercase small" for="dept_name">Department Name</label>
                    <input class="form-control form-control-lg" id="dept_name" name="dept_name" placeholder="e.g. Department of Information Technology" required type="text" value="{{ old('dept_name') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit" @disabled($colleges->isEmpty())>Save Department</button>
            </div>
        </form>
    </div>
</div>
