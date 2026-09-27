<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class DeviceTokenController extends Controller
{
    /**
     * Confier cet appareil : le jeton évite de ressaisir l'email au déverrouillage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $token = DeviceToken::issue($request->user(), $request->string('name')->toString());

        return response()->json([
            'token' => $token->token,
            'name' => $request->user()->name,
        ]);
    }

    /**
     * Retirer un appareil de confiance.
     */
    public function destroy(Request $request, DeviceToken $deviceToken): RedirectResponse
    {
        $request->user()->deviceTokens()->whereKey($deviceToken)->firstOrFail()->delete();

        return Redirect::route('profile.edit')->with('flash', [
            'message' => 'Cet appareil ne peut plus se déverrouiller avec le code PIN.',
            'type' => 'info',
        ]);
    }
}
