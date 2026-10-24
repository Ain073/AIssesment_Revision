@php
    $pageConfig = [
        'warningLimit' => $warningLimit,
        'preventCopyPaste' => $publishAssessment->prevent_copy_paste,
        'detectTabSwitch' => $publishAssessment->detect_tab_switch,
        'screenshotProtection' => $publishAssessment->screenshot_protection,
        'oneQuestionMode' => $isOneQuestionMode,
        'questionTimeLimitSeconds' => $questionTimeLimitSeconds,
        'securityEventUrl' => route('student.assessments.security-events.store', $publishAssessment),
        'dueAt' => $dueIso,
        'warnings' => $submission->warning_count,
    ];
@endphp

<script id="assessmentAttemptConfig" type="application/json">@json($pageConfig)</script>
<script src="{{ asset('js/assessment-attempt.js') }}?v={{ filemtime(public_path('js/assessment-attempt.js')) }}"></script>
