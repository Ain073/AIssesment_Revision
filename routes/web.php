<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SuperAdmin\CollegeController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('no_cache')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::middleware(['super_admin', 'no_cache'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [CollegeController::class, 'dashboard'])->name('dashboard');
        Route::get('/colleges', [CollegeController::class, 'index'])->name('colleges');
        Route::post('/colleges', [CollegeController::class, 'storeCollege'])->name('colleges.store');
        Route::post('/departments', [CollegeController::class, 'storeDepartment'])->name('departments.store');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::post('/authorization/grant', [RoleController::class, 'grant'])->name('roles.grant');
        Route::delete('/authorization/revoke', [RoleController::class, 'revoke'])->name('roles.revoke');
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
