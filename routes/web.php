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

Route::middleware(['guest', 'no_cache'])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
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
        Route::post('/colleges', [CollegeController::class, 'storeCollege'])->name('colleges.store');
        Route::delete('/colleges/{college}', [CollegeController::class, 'destroyCollege'])->name('colleges.destroy');
        Route::post('/departments', [CollegeController::class, 'storeDepartment'])->name('departments.store');
        Route::delete('/departments/{department}', [CollegeController::class, 'destroyDepartment'])->name('departments.destroy');
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
        Route::get('/classes', [InstructorDashboardController::class, 'classes'])->name('classes');
        Route::post('/classes', [InstructorDashboardController::class, 'storeClass'])->name('classes.store');
        Route::get('/assessments', [InstructorDashboardController::class, 'assessments'])->name('assessments');
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
    });

Route::middleware(['instructor', 'no_cache'])
    ->prefix('department-chair')
    ->name('department-chair.')
    ->group(function () {
        Route::get('/dashboard', [DepartmentChairDashboardController::class, 'index'])->name('dashboard');
        Route::get('/subjects', [DepartmentChairDashboardController::class, 'subjects'])->name('subjects');
        Route::post('/subjects', [DepartmentChairDashboardController::class, 'storeSubject'])->name('subjects.store');
        Route::get('/teachers', [DepartmentChairDashboardController::class, 'teachers'])->name('teachers');
        Route::get('/students', [DepartmentChairDashboardController::class, 'students'])->name('students');
        Route::get('/reports', [DepartmentChairDashboardController::class, 'reports'])->name('reports');
    });

Route::middleware(['student', 'no_cache'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/classes', [StudentDashboardController::class, 'classes'])->name('classes');
        Route::get('/assessments', [StudentDashboardController::class, 'assessments'])->name('assessments');
        Route::get('/results', [StudentDashboardController::class, 'results'])->name('results');
    });
