<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\FamilyBudgetProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /**
     * Redirige a Google para autenticarse.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Maneja el callback de Google: valida la whitelist y loguea al usuario.
     */
    public function callback(FamilyBudgetProvisioner $provisioner): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')
                ->with('error', 'La sesión de Google expiró. Probá de nuevo.');
        }

        $email = (string) $googleUser->getEmail();

        if (! FamilyBudgetProvisioner::isAllowed($email)) {
            return redirect()->route('login')
                ->with('error', 'El email '.$email.' no está autorizado para esta aplicación.');
        }

        $user = $provisioner->provision([
            'name' => $googleUser->getName(),
            'email' => $email,
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended(route('budget'));
    }

    /**
     * Cierra la sesión.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
