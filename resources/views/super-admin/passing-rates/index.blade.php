@extends('layouts.portal')

@php
    $portalSubtitle = 'Admin Panel';
    $profileInitials = 'A';
    $profileName = 'Admin';
    $profileMeta = 'Admin Account';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search records...';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => false],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => false],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => false],
        ['label' => 'Dean Designation', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => false],
        ['label' => 'Passing Rates', 'icon' => 'percent', 'href' => route('super-admin.passing-rates'), 'active' => true],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => false],
    ];
@endphp

@section('title', 'Passing Rates | AIssessment Admin')
@section('header', 'Passing Rates')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('super-admin.passing-rates.update') }}" method="POST">
        @csrf
        @method('PUT')

        <section class="directory-card shadow-sm">
            <div class="directory-header px-4 py-3">
                <h3 class="h4 mb-0">Passing Rate Settings</h3>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Year Level</th>
                            <th style="width: 260px;">Passing Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td>
                                    <span class="fw-bold" style="color: var(--psu-navy);">{{ $rate['label'] }}</span>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input
                                            class="form-control"
                                            name="rates[{{ $rate['year_level'] }}]"
                                            type="number"
                                            min="1"
                                            max="100"
                                            step="0.01"
                                            value="{{ old('rates.'.$rate['year_level'], $rate['passing_rate']) }}"
                                            required
                                        >
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 px-4 py-3 border-top">
                <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit">
                    <span class="material-symbols-outlined fs-5">save</span>
                    Save Rates
                </button>
            </div>
        </section>
    </form>
@endsection
