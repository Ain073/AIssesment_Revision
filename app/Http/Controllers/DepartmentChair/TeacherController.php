<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Services\StudentAccountImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends BaseController
{
    public function index(Request $request, StudentAccountImportService $importer): View
    {
        $user = $this->currentUser();

        return view(
            'department-chair.users.index',
            $this->sharedData($user, 'teachers') + $this->departmentUserDirectoryData($user, $request, $importer, $request->query('tab', 'teachers'))
        );
    }
}
