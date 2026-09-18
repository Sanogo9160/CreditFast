<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            abort(403, 'Ce compte n’est pas actif pour le moment. Merci de contacter votre agence si besoin.');
        }

        return $next($request);
    }
}
