<?php

return [
    // Keep report logos on the same persistent object store as profile photos when configured.
    'logo_disk' => env('REPORT_LOGO_DISK', in_array(env('PROFILE_PHOTO_DRIVER'), ['s3', 'r2'], true) ? 's3' : 'public'),

    'paper_sizes' => [
        'a4' => [
            'label' => 'A4',
            'description' => '11.69 x 8.27 in',
            'width' => '11.69in',
            'height' => '8.27in',
            'margin' => '0.75in 0.25in 0.35in',
            'preview_ratio' => '0.9',
        ],
        'short' => [
            'label' => 'Short / Letter',
            'description' => '11 x 8.5 in',
            'width' => '11in',
            'height' => '8.5in',
            'margin' => '0.75in 0.25in 0.35in',
            'preview_ratio' => '0.846',
        ],
        'long' => [
            'label' => 'Long Bond / Folio',
            'description' => '13 x 8.5 in',
            'width' => '13in',
            'height' => '8.5in',
            'margin' => '0.75in 0.25in 0.35in',
            'preview_ratio' => '1',
        ],
    ],
    'columns' => [
        'takers' => 'Total Number of Students Who Took the Assessment',
        'items' => 'Number of Items',
        'high' => 'Highest Score',
        'low' => 'Lowest Score',
        'mean' => 'Mean Score',
        'most' => 'Concepts / Skills Most Learned',
        'least' => 'Concepts / Skills Least Learned',
        'issues' => 'Issues / Concerns Encountered',
        'action' => 'Future Plans to Improve the Curriculum',
    ],
    'formative' => [
        'mean' => 'Mean Score and Percentage of Students Who Passed',
        'action' => 'Interventions Done',
    ],
];
