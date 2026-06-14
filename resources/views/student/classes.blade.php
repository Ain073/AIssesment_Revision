@extends('layouts.portal')

@section('title', 'Classes | AIssessment Student')
@section('header', 'Classes')

@push('styles')
    <style>
        .class-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
        }

        .table thead th {
            background: #edf2ff;
            color: var(--psu-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1rem 1.25rem;
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .join-code-input {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div data-poll-url="{{ route('student.classes.live') }}" data-poll-interval="5000">
        @include('student.partials.classes-live')
    </div>

    <div class="modal fade" id="joinClassModal" tabindex="-1" aria-labelledby="joinClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('student.classes.join-code.request') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="joinClassModalLabel">Join Class</h3>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="join_code">Class Code</label>
                    <input class="form-control form-control-lg join-code-input" id="join_code" name="join_code" placeholder="ABC123" required type="text" value="{{ old('join_code') }}" maxlength="20">
                    <div class="form-text">Enter the code shared by your teacher. Your request still needs teacher approval.</div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" type="submit">
                        <span class="material-symbols-outlined fs-5">send</span>
                        Send Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="joinRequestsModal" tabindex="-1" aria-labelledby="joinRequestsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4" id="joinRequestsModalLabel">Join Requests</h3>
                        <p class="small text-secondary mb-0">Pending requests still need teacher confirmation.</p>
                    </div>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>

                <div class="modal-body p-0" data-poll-url="{{ route('student.classes.requests.live') }}" data-poll-interval="5000">
                    @include('student.partials.join-requests-table')
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($errors->has('join_code'))
        <script>
            const joinClassModal = document.getElementById('joinClassModal');

            if (joinClassModal) {
                bootstrap.Modal.getOrCreateInstance(joinClassModal).show();
            }
        </script>
    @endif
@endpush
