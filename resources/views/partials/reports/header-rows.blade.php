<tr class="report-heading-row">
    <td class="report-logo-cell" rowspan="2">
        <img alt="Report logo" class="report-logo" src="{{ $reportLogoUrl }}">
        @if ($courseFieldMode === 'input')
            <button aria-label="Edit report logo" class="btn btn-sm btn-outline-primary report-logo-edit d-print-none {{ $isFinalized ? 'd-none' : '' }}" data-bs-target="#reportLogoModal" data-bs-toggle="modal" id="editReportLogoButton" type="button">Edit Logo</button>
        @endif
    </td>
    <th colspan="8">
        <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
        <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
    </th>
</tr>
<tr class="report-period-row">
    <th class="report-period" colspan="8">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</th>
</tr>
<tr>
    <th>Campus</th>
    <td colspan="8">
        @if ($courseFieldMode === 'input')
            <input class="report-header-input" name="campus" type="text" maxlength="120" value="{{ old('campus', $reportMeta['campus']) }}" required @if ($isFinalized) readonly tabindex="-1" @endif>
        @elseif ($courseFieldMode === 'mirror')
            <span class="report-print-mirror" data-print-field-name="campus">{{ strtoupper(old('campus', $reportMeta['campus'])) }}</span>
        @else
            {{ strtoupper($reportMeta['campus']) }}
        @endif
    </td>
</tr>
<tr>
    <th>College</th>
    <td colspan="4">{{ strtoupper($reportMeta['college']) }}</td>
    <th>Course Code/Title</th>
    <td colspan="3">
        @if ($courseFieldMode === 'input')
            <input class="report-header-input" name="course_code_title" type="text" value="{{ $reportMeta['course_code_title'] }}" readonly tabindex="-1" data-report-generated>
        @elseif ($courseFieldMode === 'mirror')
            <span class="report-print-mirror" data-print-field-name="course_code_title">{{ $reportMeta['course_code_title'] }}</span>
        @else
            {{ $reportMeta['course_code_title'] }}
        @endif
    </td>
</tr>
<tr>
    <th>Department</th>
    <td colspan="4">{{ strtoupper($reportMeta['department']) }}</td>
    <th>No. of Students</th>
    <td colspan="3">{{ $reportMeta['students_count'] }}</td>
</tr>
<tr>
    <td class="report-note" colspan="9">{{ $reportMeta['note'] }}</td>
</tr>
