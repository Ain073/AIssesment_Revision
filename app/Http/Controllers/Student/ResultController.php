<?php

namespace App\Http\Controllers\Student;

use Illuminate\View\View;

class ResultController extends BaseController
{
    public function results(): View
    {
        return view('student.results', $this->placeholderPageData(
            'results',
            'Results',
            'Released scores and assessment results will be organized here.',
            [
                'View released scores',
                'Review released assessment results',
                'Show answer review only when allowed by the instructor',
            ],
        ));
    }
}
