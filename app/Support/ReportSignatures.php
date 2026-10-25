<?php

namespace App\Support;

use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\User;

class ReportSignatures
{
    public static function forAssessment(PublishAssessment $publishAssessment, ?Report $report = null): array
    {
        if ($report?->report_status === Report::STATUS_FINALIZED) {
            return [
                'prepared_by_name' => $report->prepared_by_name,
                'reviewed_by_name' => $report->reviewed_by_name,
            ];
        }

        $publishAssessment->loadMissing('assessment.instructorProfile.user');
        $profile = $publishAssessment->assessment?->instructorProfile;
        $departmentId = $profile?->department_id;
        $chairs = $departmentId ? User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'department_chair'))
            ->whereHas('instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->limit(2)
            ->get() : collect();

        return [
            'prepared_by_name' => $profile?->user?->displayName(),
            'reviewed_by_name' => $chairs->count() === 1 ? $chairs->first()->displayName() : null,
        ];
    }
}
