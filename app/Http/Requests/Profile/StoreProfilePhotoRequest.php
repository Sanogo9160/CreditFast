<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('credit.profile_photo.max_kilobytes', 2048);

        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKilobytes],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo' => 'photo de profil',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Merci de joindre une photo de profil.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Merci d’envoyer une image JPG, PNG ou WEBP.',
            'photo.max' => 'La photo ne peut pas dépasser 2 Mo. Merci d’en choisir une plus légère.',
        ];
    }
}
