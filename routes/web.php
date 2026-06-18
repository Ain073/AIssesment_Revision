<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AdminDean\DashboardController as AdminDeanDashboardController;
use App\Http\Controllers\DepartmentChair\DashboardController as DepartmentChairDashboardController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\SuperAdmin\CollegeController;
use App\Http\Controllers\SuperAdmin\RoleController;
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
});

Route::middleware(['auth', 'no_cache'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::middleware(['super_admin', 'no_cache'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [CollegeController::class, 'dashboard'])->name('dashboard');
        Route::get('/colleges', [CollegeController::class, 'index'])->name('colleges');
        Route::get('/programs', [CollegeController::class, 'programs'])->name('programs');
        Route::get('/subjects', [CollegeController::class, 'subjects'])->name('subjects');
        Route::post('/colleges', [CollegeController::class, 'storeCollege'])->name('colleges.store');
        Route::delete('/colleges/{college}', [CollegeController::class, 'destroyCollege'])->name('colleges.destroy');
        Route::post('/departments', [CollegeController::class, 'storeDepartment'])->name('departments.store');
        Route::delete('/departments/{department}', [CollegeController::class, 'destroyDepartment'])->name('departments.destroy');
        Route::post('/programs', [CollegeController::class, 'storeProgram'])->name('programs.store');
        Route::post('/subjects', [CollegeController::class, 'storeSubject'])->name('subjects.store');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::post('/authorization/grant', [RoleController::class, 'grant'])->name('roles.grant');
        Route::delete('/authorization/revoke', [RoleController::class, 'revoke'])->name('roles.revoke');
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

Route::middleware(['instructor', 'no_cache'])
    ->prefix('instructor')
    ->name('instructor.')
    ->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/pending-work', [InstructorDashboardController::class, 'pendingWorkPartial'])->name('dashboard.pending-work');
        Route::get('/classes', [InstructorDashboardController::class, 'classes'])->name('classes');
        Route::post('/classes', [InstructorDashboardController::class, 'storeClass'])->name('classes.store');
        Route::get('/classes/live', [InstructorDashboardController::class, 'classesLive'])->name('classes.live');
        Route::put('/classes/{class}', [InstructorDashboardController::class, 'updateClass'])->name('classes.update');
        Route::post('/classes/{class}/archive', [InstructorDashboardController::class, 'archiveClass'])->name('classes.archive');
        Route::post('/classes/{class}/restore', [InstructorDashboardController::class, 'restoreClass'])->name('classes.restore');
        Route::delete('/classes/{class}', [InstructorDashboardController::class, 'destroyClass'])->name('classes.destroy');
        Route::get('/classes/{class}', [InstructorDashboardController::class, 'showClass'])->name('classes.show');
        Route::post('/classes/{class}/students', [InstructorDashboardController::class, 'storeClassStudent'])->name('classes.students.store');
        Route::delete('/classes/{class}/students/{studentProfile}', [InstructorDashboardController::class, 'destroyClassStudent'])->name('classes.students.destroy');
        Route::post('/classes/{class}/join-requests/{joinRequest}/approve', [InstructorDashboardController::class, 'approveClassJoinRequest'])->name('classes.join-requests.approve');
        Route::post('/classes/{class}/join-requests/{joinRequest}/reject', [InstructorDashboardController::class, 'rejectClassJoinRequest'])->name('classes.join-requests.reject');
        Route::get('/classes/{class}/students/import-sample', [InstructorDashboardController::class, 'downloadClassStudentsImportSample'])->name('classes.students.import.sample');
        Route::post('/classes/{class}/students/import-preview', [InstructorDashboardController::class, 'previewClassStudentsImport'])->name('classes.students.import.preview');
        Route::post('/classes/{class}/students/import-confirm', [InstructorDashboardController::class, 'confirmClassStudentsImport'])->name('classes.students.import.confirm');
        Route::get('/assessments', [InstructorDashboardController::class, 'assessments'])->name('assessments');
        Route::get('/assessments/create', [InstructorDashboardController::class, 'createAssessment'])->name('assessments.create');
        Route::get('/assessments/publish', [InstructorDashboardController::class, 'publishAssessmentForm'])->name('assessments.publish.form');
        Route::post('/assessments/publish', [InstructorDashboardController::class, 'publishSelectedAssessment'])->name('assessments.publish.selected');
        Route::post('/assessments', [InstructorDashboardController::class, 'storeAssessment'])->name('assessments.store');
        Route::get('/assessments/{assessment}', [InstructorDashboardController::class, 'showAssessment'])->name('assessments.show');
        Route::put('/assessments/{assessment}', [InstructorDashboardController::class, 'updateAssessment'])->name('assessments.update');
        Route::delete('/assessments/{assessment}', [InstructorDashboardController::class, 'destroyAssessment'])->name('assessments.destroy');
        Route::post('/assessments/{assessment}/items', [InstructorDashboardController::class, 'storeAssessmentItem'])->name('assessments.items.store');
        Route::post('/assessments/{assessment}/publish', [InstructorDashboardController::class, 'publishAssessment'])->name('assessments.publish');
        Route::get('/students', [InstructorDashboardController::class, 'students'])->name('students');
    });

Route::middleware(['admin_dean', 'no_cache'])
    ->prefix('admin-dean')
    ->name('admin-dean.')
    ->group(function () {
        Route::get('/dashboard', [AdminDeanDashboardController::class, 'index'])->name('dashboard');
        Route::get('/departments', [AdminDeanDashboardController::class, 'departments'])->name('departments');
        Route::post('/departments', [AdminDeanDashboardController::class, 'storeDepartment'])->name('departments.store');
        Route::get('/programs', [AdminDeanDashboardController::class, 'programs'])->name('programs');
        Route::post('/programs', [AdminDeanDashboardController::class, 'storeProgram'])->name('programs.store');
        Route::get('/teachers', [AdminDeanDashboardController::class, 'teachers'])->name('teachers');
        Route::get('/students', [AdminDeanDashboardController::class, 'students'])->name('students');
        Route::post('/users', [AdminDeanDashboardController::class, 'storeUser'])->name('users.store');
    });

Route::middleware(['department_chair', 'no_cache'])
    ->prefix('department-chair')
    ->name('department-chair.')
    ->group(function () {
        Route::get('/dashboard', [DepartmentChairDashboardController::class, 'index'])->name('dashboard');
        Route::get('/teachers', [DepartmentChairDashboardController::class, 'teachers'])->name('teachers');
        Route::get('/students', [DepartmentChairDashboardController::class, 'students'])->name('students');
        Route::post('/users', [DepartmentChairDashboardController::class, 'storeUser'])->name('users.store');
        Route::get('/reports', [DepartmentChairDashboardController::class, 'reports'])->name('reports');
    });

Route::middleware(['student', 'no_cache'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/classes', [StudentDashboardController::class, 'classes'])->name('classes');
        Route::get('/classes/live', [StudentDashboardController::class, 'classesLive'])->name('classes.live');
        Route::get('/classes/requests/live', [StudentDashboardController::class, 'classRequestsLive'])->name('classes.requests.live');
        Route::post('/classes/join-code', [StudentDashboardController::class, 'requestClassJoinByCode'])->name('classes.join-code.request');
        Route::get('/classes/join/{token}', [StudentDashboardController::class, 'showClassJoinLink'])->name('classes.join.show');
        Route::post('/classes/join/{token}', [StudentDashboardController::class, 'requestClassJoin'])->name('classes.join.request');
        Route::get('/assessments', [StudentDashboardController::class, 'assessments'])->name('assessments');
        Route::get('/assessments/live', [StudentDashboardController::class, 'assessmentsLive'])->name('assessments.live');
        Route::get('/assessments/{classAssessment}/start', [StudentDashboardController::class, 'startAssessment'])->name('assessments.start');
        Route::post('/assessments/{classAssessment}/submit', [StudentDashboardController::class, 'submitAssessment'])->name('assessments.submit');
        Route::get('/assessments/{classAssessment}/take', [StudentDashboardController::class, 'takeAssessment'])->name('assessments.take');
        Route::get('/results', [StudentDashboardController::class, 'results'])->name('results');
    });
