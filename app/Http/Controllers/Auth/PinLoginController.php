<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PinLoginController extends Controller
{
    /**
     * L'appareil est-il encore de confiance ? L'écran ne propose le PIN que si oui.
     */
    public function device(Request $request): JsonResponse
    {
        $token = $request->string('device_token')->toString();
        $appareil = $token === ''
            ? null
            : DeviceToken::query()->where('token', $token)->with('user')->first();

        if (! $appareil || ! $appareil->user?->hasPin()) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json(['ok' => true, 'nom' => $appareil->user->name]);
    }

    /**
     * Authenticate with the six digits of the code pin.
     *
     * Le jeton de l'appareil désigne le compte : sans lui, un PIN seul ne veut rien dire.
     */
    public function store(PinLoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $request->utilisateur();
        $jeton = $request->string('device_token')->toString();

        /* Un jeton inconnu se traite comme un code faux : on ne confirme rien à l'attaquant. */
        $valide = $user !== null
            && $user->hasPin()
            && Hash::check($request->string('pin')->toString(), (string) $user->pin_hash);

        if (! $valide) {
            $request->lockOut();

            return response()->json([
                'ok' => false,
                'code' => 'pin_incorrect',
                'message' => 'Code incorrect.',
            ], 422);
        }

        RateLimiter::clear($request->throttleKey());

        DeviceToken::query()->where('token', $jeton)->first()?->markUsed();

        /* Sans remember : le code est redemandé à chaque ouverture, comme avec Face ID. */
        Auth::guard('web')->login($user);

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'redirect' => redirect()->intended(route('dashboard', absolute: false))->getTargetUrl(),
        ]);
    }
}
