<?php

namespace App\Http\Middleware;

use App\Services\FamilyBudgetProvisioner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWhitelisted
{
    /**
     * Garantiza que el usuario autenticado siga estando en la whitelist.
     * Si su email fue removido de ALLOWED_EMAILS, se cierra la sesión.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! FamilyBudgetProvisioner::isAllowed($user->email)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Tu acceso fue revocado.');
        }

        return $next($request);
    }
}
