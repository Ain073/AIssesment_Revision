<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SuperAdmin\CollegeController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/super-admin/dashboard', [CollegeController::class, 'dashboard'])->name('super-admin.dashboard');
Route::get('/super-admin/colleges', [CollegeController::class, 'index'])->name('super-admin.colleges');
Route::post('/super-admin/colleges', [CollegeController::class, 'storeCollege'])->name('super-admin.colleges.store');
Route::post('/super-admin/departments', [CollegeController::class, 'storeDepartment'])->name('super-admin.departments.store');
Route::get('/super-admin/roles', [RoleController::class, 'index'])->name('super-admin.roles');
Route::post('/super-admin/authorization/grant', [RoleController::class, 'grant'])->name('super-admin.roles.grant');
Route::delete('/super-admin/authorization/revoke', [RoleController::class, 'revoke'])->name('super-admin.roles.revoke');
Route::get('/super-admin/users', [UserController::class, 'index'])->name('super-admin.users');
Route::post('/super-admin/users', [UserController::class, 'store'])->name('super-admin.users.store');
Route::put('/super-admin/users/{user}', [UserController::class, 'update'])->name('super-admin.users.update');
Route::delete('/super-admin/users/{user}', [UserController::class, 'destroy'])->name('super-admin.users.destroy');
