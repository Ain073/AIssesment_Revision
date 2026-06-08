<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        if (Auth::user()->status !== 'active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account is inactive. Please contact the administrator.'])
                ->onlyInput('email');
        }

        return redirect()->intended($this->redirectPath());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectPath(): string
    {
        $role = DB::table('roles')
            ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', Auth::id())
            ->value('roles.role_name');

        return match ($role) {
            'super_admin' => route('super-admin.dashboard'),
            default => route('login'),
        };
    }
}
