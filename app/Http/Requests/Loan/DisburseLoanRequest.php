<?php

namespace App\Http\Requests\Loan;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DisburseLoanRequest extends FormRequest
{
    /**
     * Décaissement réservé au chargé de crédit et à l’administrateur.
     * Un client qui consulte son prêt n’a pas ce droit (HTTP 403).
     */
    public function authorize(): bool
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');

        if (! $loan instanceof Loan || $this->user() === null) {
            return false;
        }

        Gate::authorize('disburse', $loan);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disbursed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
