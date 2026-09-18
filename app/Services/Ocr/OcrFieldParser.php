<?php

namespace App\Services\Ocr;

class OcrFieldParser
{
    /**
     * Targeted extraction from OCR text. Missing values stay absent:
     * absence of a field is not filled with invented amounts.
     *
     * @return array<string, mixed>
     */
    public function parse(string $text, string $documentType): array
    {
        $type = strtoupper($documentType);
        $amounts = $this->extractAmounts($text);
        $fields = [
            'document_type' => $type,
            'amounts_detected' => $amounts,
        ];

        $address = $this->extractAddress($text);
        if ($address !== null) {
            $fields['address'] = $address;
        }

        $documentNumber = $this->extractDocumentNumber($text);
        if ($documentNumber !== null) {
            $fields['document_number'] = $documentNumber;
        }

        return match ($type) {
            'RELEVE_BANCAIRE' => $this->withBankStatementFields($fields, $text, $amounts),
            'PREUVE_REVENU', 'ATTESTATION_REVENU', 'BULLETIN_PAIE' => $this->withIncomeFields($fields, $text, $amounts),
            'FACTURE_ELECTRICITE', 'CONTRAT_BAIL', 'JUSTIFICATIF_DOMICILE' => $this->withExpenseFields($fields, $text, $amounts),
            'PIECE_IDENTITE', 'CNI', 'PASSEPORT' => $fields,
            default => $this->withGenericAmountFields($fields, $text, $amounts),
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<float>  $amounts
     * @return array<string, mixed>
     */
    protected function withBankStatementFields(array $fields, string $text, array $amounts): array
    {
        $income = $this->amountNearKeywords($text, ['salaire', 'revenu', 'virement', 'credit', 'crédit', 'encaissement']);
        $balance = $this->amountNearKeywords($text, ['solde moyen', 'solde', 'available balance', 'closing']);

        if ($income !== null) {
            $fields['verified_monthly_income'] = $income;
        } elseif ($amounts !== []) {
            $plausible = array_values(array_filter($amounts, fn (float $value): bool => $value >= 10000 && $value <= 50000000));
            if ($plausible !== []) {
                $fields['verified_monthly_income'] = max($plausible);
            }
        }

        if ($balance !== null) {
            $fields['average_balance'] = $balance;
        } elseif (isset($fields['verified_monthly_income']) === false && $amounts !== []) {
            $fields['average_balance'] = max($amounts);
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<float>  $amounts
     * @return array<string, mixed>
     */
    protected function withIncomeFields(array $fields, string $text, array $amounts): array
    {
        $income = $this->amountNearKeywords($text, ['revenu', 'salaire', 'net a payer', 'net à payer', 'chiffre d\'affaires', 'mensuel']);
        if ($income === null && $amounts !== []) {
            $income = max($amounts);
        }

        if ($income !== null) {
            $fields['verified_monthly_income'] = $income;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<float>  $amounts
     * @return array<string, mixed>
     */
    protected function withExpenseFields(array $fields, string $text, array $amounts): array
    {
        $expense = $this->amountNearKeywords($text, ['loyer', 'facture', 'montant', 'total a payer', 'total à payer', 'charges']);
        if ($expense === null && $amounts !== []) {
            $expense = max($amounts);
        }

        if ($expense !== null) {
            $fields['verified_monthly_expenses'] = $expense;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<float>  $amounts
     * @return array<string, mixed>
     */
    protected function withGenericAmountFields(array $fields, string $text, array $amounts): array
    {
        if ($this->containsAny($text, ['revenu', 'salaire', 'chiffre'])) {
            return $this->withIncomeFields($fields, $text, $amounts);
        }

        if ($this->containsAny($text, ['loyer', 'facture', 'charges'])) {
            return $this->withExpenseFields($fields, $text, $amounts);
        }

        return $fields;
    }

    /**
     * @return list<float>
     */
    public function extractAmounts(string $text): array
    {
        $normalized = str_replace("\u{00A0}", ' ', $text);
        preg_match_all(
            '/(\d{1,3}(?:[ .\s]\d{3})+|\d{4,12})(?:[.,]\d{2})?(?:\s*(?:FCFA|F\s*CFA|XOF))?/iu',
            $normalized,
            $matches
        );

        $values = [];
        foreach ($matches[0] as $index => $fullMatch) {
            $raw = $matches[1][$index];
            $amount = $this->toFloat($raw);
            if ($amount < 1000) {
                continue;
            }

            if ($this->looksLikeACalendarYear($amount, $raw, $fullMatch)) {
                continue;
            }

            $values[] = $amount;
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function amountNearKeywords(string $text, array $keywords): ?float
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($line, $keyword) === false) {
                    continue;
                }

                $amounts = $this->extractAmounts($line);
                if ($amounts !== []) {
                    return max($amounts);
                }
            }
        }

        $haystack = mb_strtolower($text);
        foreach ($keywords as $keyword) {
            $offset = mb_stripos($haystack, mb_strtolower($keyword));
            if ($offset === false) {
                continue;
            }

            $window = mb_substr($text, $offset, 80);
            $amounts = $this->extractAmounts($window);
            if ($amounts !== []) {
                return max($amounts);
            }
        }

        return null;
    }

    protected function extractAddress(string $text): ?string
    {
        if (preg_match('/\bBamako\b/iu', $text, $match) === 1) {
            return 'Bamako';
        }

        if (preg_match('/\bCommune\s+[IVX]+\b/iu', $text, $match) === 1) {
            return trim($match[0]);
        }

        if (preg_match('/^[^\n]{8,80}Mali\b/ium', $text, $match) === 1) {
            return trim($match[0]);
        }

        return null;
    }

    protected function extractDocumentNumber(string $text): ?string
    {
        if (preg_match('/\b(?:N[°o]|No|CNI|NINA)[\s:.-]*([A-Z0-9\-]{5,20})/iu', $text, $match) === 1) {
            return strtoupper($match[1]);
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    protected function containsAny(string $text, array $needles): bool
    {
        $haystack = mb_strtolower($text);
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function toFloat(string $raw): float
    {
        $raw = trim(str_replace("\u{00A0}", ' ', $raw));
        $raw = str_replace(' ', '', $raw);

        if (preg_match('/,\d{2}$/', $raw) === 1) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(['.', ','], '', $raw);
        }

        return (float) $raw;
    }

    protected function looksLikeACalendarYear(float $amount, string $raw, string $fullMatch): bool
    {
        if ($amount < 1900 || $amount > 2100) {
            return false;
        }

        if (preg_match('/FCFA|F\s*CFA|XOF/iu', $fullMatch) === 1) {
            return false;
        }

        $compact = str_replace([' ', '.', ','], '', $raw);

        return preg_match('/^\d{4}$/', $compact) === 1;
    }
}
