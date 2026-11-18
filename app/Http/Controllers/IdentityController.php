<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class IdentityController extends Controller
{
    public function verify(EmailVerificationRequest $r)
    {
        $r->fulfill();

        return redirect('/account')->with('success', __('Email verificata.'));
    }

    public function resend(Request $r)
    {
        if (! $r->user()->hasVerifiedEmail()) {
            $r->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', __('Link di verifica inviato.'));
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return back()->with('success', __('Se l’account esiste, riceverai un link per reimpostare la password.'));
    }

    public function reset(Request $r)
    {
        $d = $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)]]);
        $status = Password::reset($d, function ($u, $password) {
            $u->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($u));
        });

        return $status === Password::PASSWORD_RESET ? redirect('/login')->with('success', __('Password aggiornata.')) : back()->withErrors(['email' => __('Link non valido o scaduto.')]);
    }
}
