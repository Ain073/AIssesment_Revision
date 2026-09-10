<button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-target="#joinRequestsModal" data-bs-toggle="modal" type="button">
    <span class="material-symbols-outlined fs-5">person_add</span>
    Requests
    <span class="badge rounded-pill text-bg-primary">{{ $pendingJoinRequests->count() }}</span>
</button>
