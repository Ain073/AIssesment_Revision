<?php

namespace App\Http\Controllers\DepartmentChair;

use Illuminate\View\View;

class ReportController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();

        return view('department-chair.reports', $this->sharedData($user, 'reports'));
    }
}
