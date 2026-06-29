<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Instructor\Helpers\InstructorAssessmentHelper;
use App\Http\Controllers\Instructor\Helpers\InstructorClassAccessHelper;
use App\Http\Controllers\Instructor\Helpers\InstructorClassHelper;
use App\Http\Controllers\Instructor\Helpers\InstructorLayoutHelper;
use App\Http\Controllers\Instructor\Helpers\InstructorReportHelper;

class BaseController extends Controller
{
    use InstructorAssessmentHelper;
    use InstructorClassAccessHelper;
    use InstructorClassHelper;
    use InstructorLayoutHelper;
    use InstructorReportHelper;
}
