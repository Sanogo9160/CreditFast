<?php

namespace App\Services;

use App\Models\AccountNumberSequence;
use App\Models\Caisse;
use App\Models\Guichet;
use Illuminate\Support\Facades\DB;

class AccountNumberGenerator
{
    /**
     * Format: {CAISSE_CODE}-{GUICHET_CODE}-{YYYY}{seq 6 digits}
     * Example: BKO-G01-2026000001
     */
    public function generate(Caisse $caisse, Guichet $guichet): string
    {
        return DB::transaction(function () use ($caisse, $guichet) {
            $year = (int) now()->format('Y');

            $sequence = AccountNumberSequence::query()
                ->where('caisse_id', $caisse->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = AccountNumberSequence::create([
                    'caisse_id' => $caisse->id,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = AccountNumberSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence++;
            $sequence->save();

            $padded = str_pad((string) $sequence->last_sequence, 6, '0', STR_PAD_LEFT);

            return sprintf(
                '%s-%s-%d%s',
                strtoupper($caisse->code),
                strtoupper($guichet->code),
                $year,
                $padded
            );
        });
    }
}
