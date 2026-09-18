<?php

namespace App\Http\Requests\Loan;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecordRepaymentRequest extends FormRequest
{
    /**
     * Enregistrement d’un remboursement réservé au chargé de crédit et à l’administrateur.
     * Le client peut consulter l’échéancier, pas le modifier.
     */
    public function authorize(): bool
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');

        if (! $loan instanceof Loan || $this->user() === null) {
            return false;
        }

        Gate::authorize('recordRepayment', $loan);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
