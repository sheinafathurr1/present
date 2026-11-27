<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username', 'password' => 'kata sandi']);

        $credentials = [
            'username' => mb_strtolower(trim($data['username'])),
            'password' => $data['password'],
        ];

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $inactive = Auth::getProvider()->retrieveByCredentials(['username' => $credentials['username']]);

            throw ValidationException::withMessages([
                'username' => $inactive && ! $inactive->is_active && Auth::getProvider()->validateCredentials($inactive, $credentials)
                    ? 'Akun ini sudah dinonaktifkan. Hubungi admin sekolah.'
                    : 'Username atau kata sandi salah.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($request->user()->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
