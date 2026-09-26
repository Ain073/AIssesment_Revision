@extends('layouts.portal')

@php
    $portalSubtitle  = 'Admin Portal';
    $profileInitials = 'A';
    $profileName     = 'Admin';
    $profileMeta     = 'Admin Account';
    $navItems = [
        ['label' => 'Dashboard',              'icon' => 'dashboard',            'href' => route('super-admin.dashboard'),     'active' => request()->routeIs('super-admin.dashboard')],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance',      'href' => route('super-admin.colleges'),      'active' => request()->routeIs('super-admin.colleges')],
        ['label' => 'Programs',               'icon' => 'school',               'href' => route('super-admin.programs'),      'active' => request()->routeIs('super-admin.programs')],
        ['label' => 'Dean Designation',       'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'),         'active' => request()->routeIs('super-admin.roles')],
        ['label' => 'Passing Rates',          'icon' => 'percent',              'href' => route('super-admin.passing-rates'), 'active' => request()->routeIs('super-admin.passing-rates')],
        ['label' => 'Users',                  'icon' => 'person_search',        'href' => route('super-admin.users'),         'active' => request()->routeIs('super-admin.users')],
        ['label' => 'Audit Trail',            'icon' => 'fact_check',           'href' => route('super-admin.audit-logs'),    'active' => request()->routeIs('super-admin.audit-logs')],
    ];
@endphp

@section('title', 'Audit Trail | AIssessment Admin')
@section('header', 'Audit Trail')

@section('content')

    {{-- Filter toolbar --}}
    <form id="auditFilterForm" method="GET" action="{{ route('super-admin.audit-logs') }}">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">

            {{-- Search --}}
            <div class="flex-grow-1" style="min-width: 200px; max-width: 320px;">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <span class="material-symbols-outlined fs-6 text-secondary">search</span>
                    </span>
                    <input
                        class="form-control border-start-0 ps-0"
                        id="auditSearch"
                        name="search"
                        placeholder="Search actor or description…"
                        type="search"
                        value="{{ request('search') }}"
                        autocomplete="off"
                    >
                </div>
            </div>

            {{-- Role --}}
            <select class="form-select" id="auditRole" name="role" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Roles</option>
                @foreach ($roles as $rKey => $rLabel)
                    <option value="{{ $rKey }}" @selected(request('role') === $rKey)>
                        {{ $rLabel }}
                    </option>
                @endforeach
            </select>

            {{-- Module --}}
            <select class="form-select" id="auditModule" name="module" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Modules</option>
                @foreach ($modules as $m)
                    <option value="{{ $m }}" @selected(request('module') === $m)>{{ $m }}</option>
                @endforeach
            </select>

            {{-- Date range --}}
            <div class="d-flex align-items-center gap-1">
                <input class="form-control" id="auditFrom" name="from" type="date" value="{{ request('from') }}" title="From" style="width: 148px;" onchange="this.form.submit()">
                <span class="text-secondary small">–</span>
                <input class="form-control" id="auditTo"   name="to"   type="date" value="{{ request('to') }}"   title="To"   style="width: 148px;" onchange="this.form.submit()">
            </div>

            {{-- Buttons --}}
            <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit">
                <span class="material-symbols-outlined fs-6">search</span>
                Search
            </button>

            @if (request()->hasAny(['search', 'role', 'module', 'from', 'to']))
                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('super-admin.audit-logs') }}">
                    <span class="material-symbols-outlined fs-6">close</span>
                    Clear
                </a>
            @endif

        </div>
    </form>

    {{-- Audit Log Directory Card --}}
    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0 text-white">Event Log</h3>
            <span class="badge rounded-1" style="background: rgba(255, 255, 255, 0.2); color: #fff;">
                {{ number_format($logs->total()) }} {{ $logs->total() === 1 ? 'event' : 'events' }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 mobile-card-table audit-log-table">
                <thead>
                    <tr>
                        <th class="ps-4">Timestamp</th>
                        <th>Actor</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th class="pe-4">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            {{-- Timestamp --}}
                            <td class="ps-4 mobile-primary-cell" data-label="Timestamp">
                                <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $log->created_at->format('M d, Y') }}</p>
                                <p class="small text-secondary mb-0">{{ $log->created_at->format('h:i A') }}</p>
                            </td>

                            {{-- Actor --}}
                            <td data-label="Actor">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ strtoupper(substr($log->actorName(), 0, 1)) }}</span>
                                    <div>
                                        <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $log->actorName() }}</p>
                                        <p class="small text-secondary mb-0">{{ $log->user?->email ?? 'Deleted account' }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Role --}}
                            <td data-label="Role">
                                <span class="badge text-bg-light border text-secondary rounded-1 fw-medium">
                                    {{ $log->userRoleLabel() }}
                                </span>
                            </td>

                            {{-- Action --}}
                            <td data-label="Action">
                                <span class="fw-semibold text-dark">{{ ucfirst(strtolower($log->action)) }}</span>
                            </td>

                            {{-- Module --}}
                            <td class="text-secondary" data-label="Module">
                                {{ $log->module }}
                            </td>

                            {{-- Description --}}
                            <td data-label="Description">
                                <span class="text-dark">{{ $log->description }}</span>
                            </td>

                            {{-- IP Address --}}
                            <td class="pe-4 small text-secondary" data-label="IP Address" style="font-family: monospace;">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 mobile-empty-cell" colspan="7">
                                <div class="empty-icon mb-3">
                                    <span class="material-symbols-outlined fs-2">manage_search</span>
                                </div>
                                <h4 class="h4" style="color: var(--psu-navy);">No audit events found</h4>
                                <p class="text-secondary mb-4">Try adjusting your filters or date range.</p>
                                @if (request()->hasAny(['search', 'role', 'module', 'from', 'to']))
                                    <a class="btn btn-outline-secondary" href="{{ route('super-admin.audit-logs') }}">Clear all filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Table footer strip --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 py-3 border-top" style="background: #eff4ff;">
            <span class="small text-secondary">
                @if ($logs->total() > 0)
                    Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ number_format($logs->total()) }} entries
                @else
                    Showing 0 entries
                @endif
            </span>

            @if ($logs->hasPages())
                <div class="d-flex align-items-center gap-1">
                    @if ($logs->onFirstPage())
                        <span class="btn btn-sm btn-outline-secondary disabled">
                            <span class="material-symbols-outlined fs-6">chevron_left</span>
                        </span>
                    @else
                        <a class="btn btn-sm btn-outline-secondary" href="{{ $logs->previousPageUrl() }}">
                            <span class="material-symbols-outlined fs-6">chevron_left</span>
                        </a>
                    @endif

                    @foreach ($logs->getUrlRange(max(1, $logs->currentPage() - 2), min($logs->lastPage(), $logs->currentPage() + 2)) as $page => $url)
                        @if ($page === $logs->currentPage())
                            <span class="btn btn-sm btn-psu">{{ $page }}</span>
                        @else
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($logs->hasMorePages())
                        <a class="btn btn-sm btn-outline-secondary" href="{{ $logs->nextPageUrl() }}">
                            <span class="material-symbols-outlined fs-6">chevron_right</span>
                        </a>
                    @else
                        <span class="btn btn-sm btn-outline-secondary disabled">
                            <span class="material-symbols-outlined fs-6">chevron_right</span>
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </section>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('auditSearch');
    const tableBody = document.querySelector('.audit-log-table tbody');
    const countBadge = document.querySelector('.directory-header .badge');
    if (!searchInput || !tableBody) return;

    const dataRows = Array.from(tableBody.querySelectorAll('tr:not(.mobile-empty-cell)'));
    if (dataRows.length === 0) return;

    const defaultBadgeText = countBadge ? countBadge.textContent.trim() : '';
    let noMatchRow = null;

    function filterTable() {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        dataRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const matches = query === '' || text.includes(query);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        if (visibleCount === 0 && query !== '') {
            if (!noMatchRow) {
                noMatchRow = document.createElement('tr');
                noMatchRow.className = 'no-live-match-row';
                noMatchRow.innerHTML = '<td colspan="7" class="text-center py-4 text-secondary small"><span class="material-symbols-outlined fs-3 d-block mb-1 text-muted">search_off</span>No matching entries on this page. Press <strong>Enter</strong> or click <strong>Search</strong> to query the full database.</td>';
                tableBody.appendChild(noMatchRow);
            }
            noMatchRow.style.display = '';
        } else if (noMatchRow) {
            noMatchRow.style.display = 'none';
        }

        if (countBadge) {
            if (query !== '') {
                countBadge.textContent = `${visibleCount} of ${dataRows.length} displayed`;
            } else {
                countBadge.textContent = defaultBadgeText;
            }
        }
    }

    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('search', filterTable);
});
</script>
@endpush

