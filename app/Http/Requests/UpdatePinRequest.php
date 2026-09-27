<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\ErrorBag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[ErrorBag('updatePin')]
class UpdatePinRequest extends FormRequest
{
    /**
     * Les PIN que tout le monde devine en trois essais : ils sont refusés à la création.
     *
     * @var list<string>
     */
    public const PIN_FAIBLES = [
        '123456', '654321', '012345', '543210', '121212', '100000', '000001', '696969',
    ];

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
            /* Sans le mot de passe, un téléphone déverrouillé suffirait à poser un PIN. */
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'digits:6', 'not_in:'.implode(',', self::PIN_FAIBLES)],
            'pin_confirmation' => ['required', 'same:pin'],
            'appareil' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Ton mot de passe est nécessaire pour poser un code PIN.',
            'pin.not_in' => 'Ce code est trop facile à deviner. Choisis-en un autre.',
        ];
    }

    /**
     * Run additional validation: un seul chiffre répété se devine sans essayer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pin = (string) $this->input('pin');

            if (preg_match('/^(\d)\1{5}$/', $pin)) {
                $validator->errors()->add('pin', 'Ce code est trop facile à deviner. Choisis-en un autre.');
            }
        });
    }
}
