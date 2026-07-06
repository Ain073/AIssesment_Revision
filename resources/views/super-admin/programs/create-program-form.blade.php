{{-- Create program form --}}
<div class="modal fade" id="programModal" tabindex="-1" aria-labelledby="programModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('super-admin.programs.store') }}" class="modal-content" method="POST">
            @csrf
            <input name="is_active" type="hidden" value="0">
            <div class="modal-header">
                <h3 class="modal-title h4" id="programModalLabel">New Program</h3>
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
                <div class="mb-3">
                    <label class="form-label fw-bold text-uppercase small" for="program_name">Program Name</label>
                    <input class="form-control form-control-lg" id="program_name" name="program_name" placeholder="e.g. Bachelor of Science in Hospitality Management" required type="text" value="{{ old('program_name') }}">
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                    <label class="form-check-label fw-semibold" for="is_active">Program is active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit" @disabled($colleges->isEmpty())>Save Program</button>
            </div>
        </form>
    </div>
</div>
