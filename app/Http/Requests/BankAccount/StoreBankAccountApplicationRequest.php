<?php

namespace App\Http\Requests\BankAccount;

use App\Enums\ClientType;
use App\Models\BankAccountApplication;
use App\Models\Guichet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class StoreBankAccountApplicationRequest extends FormRequest
{
    abstract protected function expectedClientType(): ClientType;

    /**
     * @return array<string, mixed>
     */
    abstract protected function typeSpecificRules(): array;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('create', BankAccountApplication::class)) {
            return false;
        }

        return $user->client?->client_type === $this->expectedClientType();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'caisse_id' => ['required', 'integer', 'exists:caisses,id'],
            'guichet_id' => [
                'required',
                'integer',
                Rule::exists('guichets', 'id')->where(fn ($q) => $q
                    ->where('caisse_id', $this->input('caisse_id'))
                    ->where('is_active', true)),
            ],
            'city' => ['nullable', 'string', 'max:100'],
            'residential_zone' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'adhesion_place' => ['nullable', 'string', 'max:150'],
            'adhesion_date' => ['nullable', 'date'],
        ], $this->typeSpecificRules());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $guichetId = $this->integer('guichet_id');
            if ($guichetId < 1) {
                return;
            }

            $guichet = Guichet::query()->with(['caisse', 'cashDesks'])->find($guichetId);
            if ($guichet && ! $guichet->isSelectable()) {
                $validator->errors()->add('guichet_id', 'Le guichet doit être actif et disposer d’au moins une case active.');
            }
        });
    }
}
