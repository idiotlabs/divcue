<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $r->validate([
//            'email' => 'required|email',
//            'password' => 'required',
        ]);

        if (Auth::attempt($r->only('email', 'password'), $r->filled('remember'))) {
            $r->session()->regenerate();
            return redirect()->intended('/preferences');
        }

        return back()->withErrors(['email' => '자격 증명이 올바르지 않습니다.'])->onlyInput('email');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/');
    }
}
