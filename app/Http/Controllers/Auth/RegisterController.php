<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $r)
    {
        $r->validate([
//            'name' => 'required|string|max:255',
//            'email' => 'required|email|unique:users',
//            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $r->name,
            'email' => $r->email,
            'password' => Hash::make($r->password),
        ]);

        Auth::login($user);

        return redirect()->intended('/preferences');
    }
}
