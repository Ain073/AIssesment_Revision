@extends('layouts.portal')

@section('title', $reportTypeLabel.' Report | AIssessment Department Chair')
@section('header', 'Report Details')

@push('styles')
    <style>
        .report-view-card,
        .report-summary-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 16px 32px rgba(0, 26, 112, 0.06);
        }

        .report-view-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 68%, #8f8a73 120%);
            color: #fff;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .report-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .report-summary-card {
            padding: 1.25rem;
        }

        .report-summary-card .value {
            color: var(--psu-navy);
            font-size: 1.8rem;
            font-weight: 900;
            line-height: 1;
        }

        .report-info-table th {
            width: 210px;
            color: var(--psu-muted);
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.05em;
        }

        .report-content-box {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            padding: 1rem;
            min-height: 120px;
            background: #fbfcff;
            white-space: pre-wrap;
        }

        @media (max-width: 991.98px) {
            .report-summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .report-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('department-chair.reports') }}" style="color: var(--psu-navy-2);">
                &larr; Back to Reports
            </a>
            <h1 class="brand-text mt-2 mb-0" style="color: var(--psu-navy);">{{ $reportTypeLabel }} Report</h1>
        </div>
        <span class="badge text-bg-success rounded-1 px-3 py-2">Finalized</span>
    </div>

    <section class="report-view-card overflow-hidden">
        <div class="report-view-header">
            <p class="small text-white-50 text-uppercase fw-bold mb-2">{{ $assessment?->subject?->subject_code ?? $class?->subject?->subject_code ?? 'No subject' }}</p>
            <h2 class="brand-text h2 mb-2">{{ $assessment?->title ?? 'Untitled assessment' }}</h2>
            <p class="mb-0 text-white-50">
                {{ $class?->class_name ?? 'No class' }} {{ $class?->school_year ? '| '.$class->school_year : '' }}
            </p>
        </div>

        <div class="p-4">
            <div class="table-responsive mb-4">
                <table class="table report-info-table mb-0">
                    <tbody>
                        <tr>
                            <th>Instructor</th>
                            <td>{{ $assessment?->instructorProfile?->user?->displayName() ?? 'No instructor' }}</td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td>{{ $department?->dept_name ?? 'Not set' }}</td>
                        </tr>
                        <tr>
                            <th>Course Code / Title</th>
                            <td>{{ $report->course_code_title ?: (($assessment?->subject?->subject_code ?? '').' / '.($assessment?->subject?->subject_name ?? '')) }}</td>
                        </tr>
                        <tr>
                            <th>Finalized On</th>
                            <td>{{ $report->updated_at?->format('M d, Y h:i A') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="report-summary-grid">
                <article class="report-summary-card">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Students</p>
                    <div class="value">{{ $analytics['students_count'] }}</div>
                </article>
                <article class="report-summary-card">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Takers</p>
                    <div class="value">{{ $analytics['takers_count'] }}</div>
                </article>
                <article class="report-summary-card">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Mean Score</p>
                    <div class="value">{{ $analytics['mean_score'] }}</div>
                </article>
                <article class="report-summary-card">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Passing Rate</p>
                    <div class="value">{{ $analytics['passing_rate'] }}%</div>
                </article>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-lg-6">
                    <h3 class="h5 fw-bold" style="color: var(--psu-navy);">Concepts / Skills Most Learned</h3>
                    <div class="report-content-box">{{ $report->concept_most_learned_skills ?: 'No content provided.' }}</div>
                </div>
                <div class="col-lg-6">
                    <h3 class="h5 fw-bold" style="color: var(--psu-navy);">Concepts / Skills Least Learned</h3>
                    <div class="report-content-box">{{ $report->concept_least_learned_skills ?: 'No content provided.' }}</div>
                </div>
                <div class="col-lg-6">
                    <h3 class="h5 fw-bold" style="color: var(--psu-navy);">Issues / Concerns Encountered</h3>
                    <div class="report-content-box">{{ $report->issues_concern ?: 'No content provided.' }}</div>
                </div>
                <div class="col-lg-6">
                    <h3 class="h5 fw-bold" style="color: var(--psu-navy);">
                        {{ $report->report_type === \App\Models\Report::TYPE_FORMATIVE ? 'Interventions Done' : 'Future Plans to Improve the Curriculum' }}
                    </h3>
                    <div class="report-content-box">
                        {{ $report->report_type === \App\Models\Report::TYPE_FORMATIVE
                            ? ($report->interventions_done ?: 'No content provided.')
                            : ($report->future_plans_curriculum ?: 'No content provided.') }}
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
