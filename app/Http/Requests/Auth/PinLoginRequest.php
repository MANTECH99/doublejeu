<?php

namespace App\Http\Requests\Auth;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PinLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6'],
            'device_token' => ['required', 'string', 'size:64'],
        ];
    }

    /**
     * Le compte visé : le jeton de l'appareil de confiance, et rien d'autre.
     *
     * Un PIN seul ne désigne aucun compte : sans jeton, il faut repasser par Face ID
     * ou le formulaire email / mot de passe.
     */
    public function utilisateur(): ?User
    {
        return DeviceToken::query()
            ->where('token', $this->string('device_token')->toString())
            ->first()?->user;
    }

    /**
     * Ensure the pin request is not rate limited.
     *
     * Un PIN n'a que 6 chiffres : la fenêtre de blocage est de 15 minutes, pas de 60 secondes.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'pin' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Le verrouillage suit le compte quand le jeton est connu, l'adresse IP sinon.
     */
    public function throttleKey(): string
    {
        $user = $this->utilisateur();

        return 'pin|'.($user ? 'u'.$user->id : 'i'.$this->ip());
    }

    /**
     * Enregistre un échec et renvoie le temps de blocage restant.
     */
    public function lockOut(): int
    {
        RateLimiter::hit($this->throttleKey(), 900);

        return RateLimiter::availableIn($this->throttleKey());
    }
}
