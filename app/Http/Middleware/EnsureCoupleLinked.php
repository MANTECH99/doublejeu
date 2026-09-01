<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoupleLinked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->coupleModel || ! $user->coupleModel->isLinked()) {
            return redirect()->route('couple.setup');
        }

        return $next($request);
    }
}
