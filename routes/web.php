<?php

use App\Http\Controllers\AdminDean\DashboardController as AdminDeanDashboardController;
use App\Http\Controllers\AdminDean\DepartmentController as AdminDeanDepartmentController;
use App\Http\Controllers\AdminDean\ProgramController as AdminDeanProgramController;
use App\Http\Controllers\AdminDean\StudentController as AdminDeanStudentController;
use App\Http\Controllers\AdminDean\TeacherController as AdminDeanTeacherController;
use App\Http\Controllers\AdminDean\UserController as AdminDeanUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DepartmentChair\DashboardController as DepartmentChairDashboardController;
use App\Http\Controllers\DepartmentChair\ReportController as DepartmentChairReportController;
use App\Http\Controllers\DepartmentChair\StudentController as DepartmentChairStudentController;
use App\Http\Controllers\DepartmentChair\TeacherController as DepartmentChairTeacherController;
use App\Http\Controllers\DepartmentChair\UserController as DepartmentChairUserController;
use App\Http\Controllers\Instructor\AssessmentController as InstructorAssessmentController;
use App\Http\Controllers\Instructor\AssessmentPublishController as InstructorAssessmentPublishController;
use App\Http\Controllers\Instructor\AssessmentResultController as InstructorAssessmentResultController;
use App\Http\Controllers\Instructor\ClassController as InstructorClassController;
use App\Http\Controllers\Instructor\ClassStudentController as InstructorClassStudentController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Instructor\ReportController as InstructorReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Student\AssessmentController as StudentAssessmentController;
use App\Http\Controllers\Student\ClassController as StudentClassController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\ResultController as StudentResultController;
use App\Http\Controllers\SuperAdmin\CollegeDepartmentController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\ProgramController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\SubjectController;
use App\Http\Controllers\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['no_cache'])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
});

Route::middleware(['guest', 'no_cache'])->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware(['auth', 'no_cache'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/photo', [ProfileController::class, 'updatePhoto'])->middleware('throttle:10,1')->name('profile.photo.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/portal-search', [SearchController::class, 'search'])->middleware('throttle:30,1')->name('portal.search');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware(['super_admin', 'no_cache'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/colleges', [CollegeDepartmentController::class, 'index'])->name('colleges');
        Route::post('/colleges', [CollegeDepartmentController::class, 'storeCollege'])->name('colleges.store');
        Route::put('/colleges/{college}', [CollegeDepartmentController::class, 'updateCollege'])->name('colleges.update');
        Route::delete('/colleges/{college}', [CollegeDepartmentController::class, 'destroyCollege'])->name('colleges.destroy');
        Route::post('/departments', [CollegeDepartmentController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [CollegeDepartmentController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [CollegeDepartmentController::class, 'destroyDepartment'])->name('departments.destroy');
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::post('/subjects/active-semester', [SubjectController::class, 'activateSemester'])->name('subjects.semester.activate');
        Route::put('/subjects/{subjectProgram}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subjectProgram}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::post('/authorization/grant', [RoleController::class, 'grant'])->name('roles.grant');
        Route::delete('/authorization/revoke', [RoleController::class, 'revoke'])->name('roles.revoke');
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::get('/users/student-import-sample', [UserController::class, 'downloadStudentImportSample'])->name('users.students.import.sample');
        Route::post('/users/student-import-preview', [UserController::class, 'previewStudentImport'])->middleware('throttle:10,1')->name('users.students.import.preview');
        Route::post('/users/student-import-confirm', [UserController::class, 'confirmStudentImport'])->middleware('throttle:10,1')->name('users.students.import.confirm');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

Route::middleware(['instructor', 'no_cache'])
    ->prefix('instructor')
    ->name('instructor.')
    ->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/classes', [InstructorClassController::class, 'classes'])->name('classes');
        Route::post('/classes', [InstructorClassController::class, 'storeClass'])->name('classes.store');
        Route::get('/classes/live', [InstructorClassController::class, 'classesLive'])->name('classes.live');
        Route::put('/classes/{class}', [InstructorClassController::class, 'updateClass'])->name('classes.update');
        Route::post('/classes/{class}/archive', [InstructorClassController::class, 'archiveClass'])->name('classes.archive');
        Route::post('/classes/{class}/restore', [InstructorClassController::class, 'restoreClass'])->name('classes.restore');
        Route::delete('/classes/{class}', [InstructorClassController::class, 'destroyClass'])->name('classes.destroy');
        Route::get('/classes/{class}', [InstructorClassController::class, 'showClass'])->name('classes.show');
        Route::post('/classes/{class}/students', [InstructorClassStudentController::class, 'storeClassStudent'])->name('classes.students.store');
        Route::delete('/classes/{class}/students/{studentProfile}', [InstructorClassStudentController::class, 'destroyClassStudent'])->name('classes.students.destroy');
        Route::post('/classes/{class}/join-requests/{joinRequest}/approve', [InstructorClassStudentController::class, 'approveClassJoinRequest'])->name('classes.join-requests.approve');
        Route::post('/classes/{class}/join-requests/{joinRequest}/reject', [InstructorClassStudentController::class, 'rejectClassJoinRequest'])->name('classes.join-requests.reject');
        Route::get('/classes/{class}/students/import-sample', [InstructorClassStudentController::class, 'downloadClassStudentsImportSample'])->name('classes.students.import.sample');
        Route::post('/classes/{class}/students/import-preview', [InstructorClassStudentController::class, 'previewClassStudentsImport'])->middleware('throttle:10,1')->name('classes.students.import.preview');
        Route::post('/classes/{class}/students/import-confirm', [InstructorClassStudentController::class, 'confirmClassStudentsImport'])->middleware('throttle:10,1')->name('classes.students.import.confirm');
        Route::get('/assessments', [InstructorAssessmentController::class, 'assessments'])->name('assessments');
        Route::get('/assessments/create', [InstructorAssessmentController::class, 'createAssessment'])->name('assessments.create');
        Route::get('/assessments/publish', [InstructorAssessmentPublishController::class, 'publishAssessmentForm'])->name('assessments.publish.form');
        Route::post('/assessments/publish', [InstructorAssessmentPublishController::class, 'publishSelectedAssessment'])->name('assessments.publish.selected');
        Route::post('/assessments', [InstructorAssessmentController::class, 'storeAssessment'])->name('assessments.store');
        Route::get('/class-assessments/{classAssessment}/results', [InstructorAssessmentResultController::class, 'assessmentResults'])->name('assessments.results');
        Route::get('/assessments/{assessment}', [InstructorAssessmentController::class, 'showAssessment'])->name('assessments.show');
        Route::put('/assessments/{assessment}', [InstructorAssessmentController::class, 'updateAssessment'])->name('assessments.update');
        Route::delete('/assessments/{assessment}', [InstructorAssessmentController::class, 'destroyAssessment'])->name('assessments.destroy');
        Route::post('/assessments/{assessment}/items', [InstructorAssessmentController::class, 'storeAssessmentItem'])->name('assessments.items.store');
        Route::put('/assessments/{assessment}/items/{item}', [InstructorAssessmentController::class, 'updateAssessmentItem'])->name('assessments.items.update');
        Route::delete('/assessments/{assessment}/items/{item}', [InstructorAssessmentController::class, 'destroyAssessmentItem'])->name('assessments.items.destroy');
        Route::post('/assessments/{assessment}/publish', [InstructorAssessmentPublishController::class, 'publishAssessment'])->name('assessments.publish');
        Route::get('/reports', [InstructorReportController::class, 'reports'])->name('reports');
        Route::post('/reports/prepare', [InstructorReportController::class, 'prepareReports'])->name('reports.prepare');
        Route::get('/reports/build', [InstructorReportController::class, 'showReportSheet'])->name('reports.build');
        Route::post('/reports/build', [InstructorReportController::class, 'saveReportSheet'])->name('reports.save');
        Route::post('/reports/ai-drafts', [InstructorReportController::class, 'generateAiDrafts'])->middleware('throttle:10,1')->name('reports.ai-drafts');
    });

Route::middleware(['admin_dean', 'no_cache'])
    ->prefix('admin-dean')
    ->name('admin-dean.')
    ->group(function () {
        Route::get('/dashboard', [AdminDeanDashboardController::class, 'index'])->name('dashboard');
        Route::get('/departments', [AdminDeanDepartmentController::class, 'index'])->name('departments');
        Route::post('/departments', [AdminDeanDepartmentController::class, 'store'])->name('departments.store');
        Route::get('/programs', [AdminDeanProgramController::class, 'index'])->name('programs');
        Route::post('/programs', [AdminDeanProgramController::class, 'store'])->name('programs.store');
        Route::get('/teachers', [AdminDeanTeacherController::class, 'index'])->name('teachers');
        Route::get('/students', [AdminDeanStudentController::class, 'index'])->name('students');
        Route::get('/students/import-sample', [AdminDeanStudentController::class, 'downloadImportSample'])->name('students.import.sample');
        Route::post('/students/import-preview', [AdminDeanStudentController::class, 'previewImport'])->middleware('throttle:10,1')->name('students.import.preview');
        Route::post('/students/import-confirm', [AdminDeanStudentController::class, 'confirmImport'])->middleware('throttle:10,1')->name('students.import.confirm');
        Route::post('/users', [AdminDeanUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AdminDeanUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminDeanUserController::class, 'destroy'])->name('users.destroy');
    });

Route::middleware(['department_chair', 'no_cache'])
    ->prefix('department-chair')
    ->name('department-chair.')
    ->group(function () {
        Route::get('/dashboard', [DepartmentChairDashboardController::class, 'index'])->name('dashboard');
        Route::get('/teachers', [DepartmentChairTeacherController::class, 'index'])->name('teachers');
        Route::get('/students', [DepartmentChairStudentController::class, 'index'])->name('students');
        Route::get('/students/import-sample', [DepartmentChairStudentController::class, 'downloadImportSample'])->name('students.import.sample');
        Route::post('/students/import-preview', [DepartmentChairStudentController::class, 'previewImport'])->middleware('throttle:10,1')->name('students.import.preview');
        Route::post('/students/import-confirm', [DepartmentChairStudentController::class, 'confirmImport'])->middleware('throttle:10,1')->name('students.import.confirm');
        Route::post('/users', [DepartmentChairUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [DepartmentChairUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [DepartmentChairUserController::class, 'destroy'])->name('users.destroy');
        Route::get('/reports', [DepartmentChairReportController::class, 'index'])->name('reports');
        Route::get('/reports/{classAssessment}/{type}', [DepartmentChairReportController::class, 'show'])->name('reports.show');
    });

Route::middleware(['student', 'no_cache'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/classes', [StudentClassController::class, 'classes'])->name('classes');
        Route::get('/classes/live', [StudentClassController::class, 'classesLive'])->name('classes.live');
        Route::get('/classes/requests/live', [StudentClassController::class, 'classRequestsLive'])->name('classes.requests.live');
        Route::post('/classes/join-code', [StudentClassController::class, 'requestClassJoinByCode'])->middleware('throttle:10,1')->name('classes.join-code.request');
        Route::get('/classes/join/{token}', [StudentClassController::class, 'showClassJoinLink'])->name('classes.join.show');
        Route::post('/classes/join/{token}', [StudentClassController::class, 'requestClassJoin'])->name('classes.join.request');
        Route::get('/assessments', [StudentAssessmentController::class, 'assessments'])->name('assessments');
        Route::get('/assessments/live', [StudentAssessmentController::class, 'assessmentsLive'])->name('assessments.live');
        Route::get('/assessments/{classAssessment}/submitted', [StudentAssessmentController::class, 'submittedAssessment'])->name('assessments.submitted');
        Route::get('/assessments/{classAssessment}/start', [StudentAssessmentController::class, 'startAssessment'])->name('assessments.start');
        Route::post('/assessments/{classAssessment}/security-events', [StudentAssessmentController::class, 'recordSecurityEvent'])
            ->middleware('throttle:30,1')
            ->name('assessments.security-events.store');
        Route::post('/assessments/{classAssessment}/submit', [StudentAssessmentController::class, 'submitAssessment'])->name('assessments.submit');
        Route::get('/assessments/{classAssessment}/take', [StudentAssessmentController::class, 'takeAssessment'])->name('assessments.take');
        Route::get('/results', [StudentResultController::class, 'results'])->name('results');
    });
