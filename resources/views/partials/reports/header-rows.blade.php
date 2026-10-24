<tr>
    <td class="report-logo-cell" rowspan="2">
        <img alt="PSU logo" class="report-logo" src="{{ asset('images/psu-logo-transparent.png') }}">
    </td>
    <th colspan="8">
        <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
        <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
    </th>
</tr>
<tr>
    <th class="report-period" colspan="8">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</th>
</tr>
<tr>
    <th>Campus</th>
    <td colspan="8">{{ strtoupper($reportMeta['campus']) }}</td>
</tr>
<tr>
    <th>College</th>
    <td colspan="4">{{ strtoupper($reportMeta['college']) }}</td>
    <th colspan="2">Course Code/Title</th>
    <td colspan="2">
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
    <th colspan="2">No. of Students</th>
    <td colspan="2">{{ $reportMeta['students_count'] }}</td>
</tr>
<tr>
    <td class="report-note" colspan="9">{{ $reportMeta['note'] }}</td>
</tr>
