<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdatePinRequest;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Upload a profile photo, en attente de validation.
     *
     * L'ancienne photo n'est pas touchée : elle ne part qu'au clic sur « Valider ».
     */
    public function uploadPhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        /* Une photo déjà en attente est abandonnée plutôt que d'accumuler les fichiers. */
        $this->oublierPhotoEnAttente($user);

        $path = $request->file('photo')->store('profile-photos', 'public');
        $user->forceFill(['avatar_url_pending' => $path])->save();

        return Redirect::route('profile.edit')->with('flash', [
            'message' => 'Photo chargée. Vérifie-la, puis clique sur « Mettre à jour ».',
            'type' => 'info',
        ]);
    }

    /**
     * Valide la photo en attente : elle remplace l'ancienne.
     */
    public function applyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasPendingPhoto()) {
            return Redirect::route('profile.edit')->with('flash', [
                'message' => 'Aucune photo à mettre à jour.',
                'type' => 'info',
            ]);
        }

        if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->forceFill([
            'avatar_url' => $user->avatar_url_pending,
            'avatar_url_pending' => null,
        ])->save();

        return Redirect::route('profile.edit')->with('flash', [
            'message' => 'Photo de profil mise à jour.',
            'type' => 'success',
        ]);
    }

    /**
     * Abandonne la photo en attente : la photo actuelle reste en place.
     */
    public function cancelPhoto(Request $request): RedirectResponse
    {
        $this->oublierPhotoEnAttente($request->user());

        return Redirect::route('profile.edit')->with('flash', [
            'message' => 'Photo abandonnée.',
            'type' => 'info',
        ]);
    }

    /**
     * Supprime le fichier d'une photo en attente, s'il existe encore.
     */
    private function oublierPhotoEnAttente(User $user): void
    {
        if (! $user->hasPendingPhoto()) {
            return;
        }

        if (Storage::disk('public')->exists($user->avatar_url_pending)) {
            Storage::disk('public')->delete($user->avatar_url_pending);
        }

        $user->forceFill(['avatar_url_pending' => null])->save();
    }

    /**
     * Remove the user's profile photo.
     */
    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->oublierPhotoEnAttente($user);

        if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->update(['avatar_url' => null]);

        return Redirect::route('profile.edit')->with('flash', [
            'message' => 'Photo de profil supprimée.',
            'type' => 'info',
        ]);
    }

    /**
     * Pose ou remplace le code PIN, et confie l'appareil courant.
     */
    public function updatePin(UpdatePinRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill(['pin_hash' => Hash::make($request->string('pin')->toString())])->save();

        $token = DeviceToken::issue($user, $request->string('appareil')->toString());

        return Redirect::route('profile.edit')
            ->with('flash', [
                'message' => 'Code PIN enregistré. Il te sera demandé à la prochaine ouverture.',
                'type' => 'success',
            ])
            ->with('pin', [
                'token' => $token->token,
                'nom' => $user->name,
            ]);
    }

    /**
     * Supprime le code PIN et tous les appareils qui s'appuient dessus.
     */
    public function destroyPin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->deviceTokens()->delete();
        $user->forceFill(['pin_hash' => null])->save();

        return Redirect::route('profile.edit')->with('flash', [
            'message' => 'Code PIN supprimé. Tu te connectes avec Face ID ou ton mot de passe.',
            'type' => 'info',
        ]);
    }

    /**
     * Enregistre le fuseau horaire détecté par le navigateur.
     */
    public function timezone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();

        if ($user->timezone !== $data['timezone']) {
            $user->forceFill(['timezone' => $data['timezone']])->save();
        }

        return response()->json(['ok' => true]);
    }
}
