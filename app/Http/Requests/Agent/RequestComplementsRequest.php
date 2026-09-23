<?php

namespace App\Http\Requests\Agent;

use App\Enums\ComplementSubject;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RequestComplementsRequest extends FormRequest
{
    /**
     * Demande de pièces ou d’informations manquantes.
     *
     * Réservée au chargé de crédit et à l’administrateur. Cette action
     * renvoie officiellement le dossier au client (statut VERIFICATION_REQUIRED).
     */
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return $this->user()?->can('requestComplements', $creditRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', new Enum(ComplementSubject::class)],
            'detail' => ['required', 'string', 'min:5'],
            'comment' => ['nullable', 'string', 'min:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject' => 'sujet',
            'detail' => 'détail',
            'comment' => 'message au client',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detail.min' => 'Merci de préciser au client les pièces ou informations à transmettre (au moins 5 caractères).',
        ];
    }
}
