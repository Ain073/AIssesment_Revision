<?php

namespace App\Support;

use App\Models\PublishAssessment;
use Illuminate\Support\Collection;

class ReportCourseTitle
{
    public static function forAssessment(PublishAssessment $assessment): string
    {
        return self::forAssessments(collect([$assessment]));
    }

    public static function forAssessments(Collection $assessments): string
    {
        return $assessments
            ->groupBy(function (PublishAssessment $published) {
                $subject = $published->assessment?->subject ?: $published->class?->subject;

                return $subject?->getKey() ?? 'no_subject';
            })
            ->map(function (Collection $group): string {
                $first = $group->first();
                $subject = $first?->assessment?->subject ?: $first?->class?->subject;
                $code = $subject?->subject_code ?? 'No code';
                $title = $subject?->subject_name ?? 'No subject';
                $sections = $group
                    ->map(fn (PublishAssessment $published) => $published->class?->section_name ?: $published->class?->displayName())
                    ->filter(fn ($label) => filled($label) && $label !== 'Class')
                    ->unique()
                    ->values();

                return $sections->isNotEmpty()
                    ? trim($code.' - '.$sections->implode(', ').' / '.$title, ' -/')
                    : trim($code.' / '.$title, ' /');
            })
            ->filter()
            ->implode(' | ');
    }
}
