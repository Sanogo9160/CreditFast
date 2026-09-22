<?php

namespace App\Http\Requests\BankAccount;

use App\Enums\BankAccountDocumentType;
use App\Enums\ClientType;
use App\Models\BankAccountApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreBankAccountApplicationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BankAccountApplication $application */
        $application = $this->route('bankAccountApplication');

        return $this->user()?->can('uploadDocument', $application) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BankAccountApplication $application */
        $application = $this->route('bankAccountApplication');
        $client = $application->client;
        $allowed = $client?->client_type === ClientType::LegalEntity
            ? BankAccountDocumentType::forLegalEntity()
            : BankAccountDocumentType::forPhysicalPerson();

        return [
            'document_type' => ['required', new Enum(BankAccountDocumentType::class), Rule::in(array_map(
                fn (BankAccountDocumentType $type) => $type->value,
                $allowed
            ))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
