<?php

namespace App\Http\Requests\CreditRequest;

use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return $this->user()?->can('uploadDocument', $creditRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:80'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_type' => 'type de document',
            'file' => 'fichier',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Merci de joindre un fichier PDF, JPG ou PNG.',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo. Merci d’en choisir un plus léger.',
        ];
    }
}
