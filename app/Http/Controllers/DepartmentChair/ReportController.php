<?php

namespace App\Http\Controllers\DepartmentChair;

use Illuminate\View\View;

class ReportController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();

        return view('department-chair.reports.index', $this->sharedData($user, 'reports'));
    }
}
