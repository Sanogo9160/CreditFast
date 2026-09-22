<?php

namespace App\Models;

use App\Enums\BankAccountPartyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountApplicationParty extends Model
{
    protected $fillable = [
        'bank_account_application_id',
        'role',
        'sort_order',
        'first_name',
        'last_name',
        'date_of_birth',
        'birth_place',
        'nationality',
        'function_in_company',
        'id_document_type',
        'id_document_number',
        'address',
        'phone',
        'link_with_company',
        'photo_path',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'role' => BankAccountPartyRole::class,
            'date_of_birth' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(BankAccountApplication::class, 'bank_account_application_id');
    }
}
