<?php

namespace App\Http\Requests\Agent;

use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;

class RequestComplementsRequest extends FormRequest
{
    /**
     * Demande de pièces ou d’informations manquantes.
     *
     * Réservée au chargé de crédit et à l’administrateur. Cette action
     * renvoie officiellement le dossier au client (statut VERIFICATION_REQUIRED)
     * pour qu’il puisse, par exemple, transmettre un justificatif de domicile.
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
            'comment' => ['required', 'string', 'min:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'comment' => 'message au client',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.min' => 'Merci de préciser au client les pièces ou informations à transmettre (au moins quelques mots).',
        ];
    }
}
