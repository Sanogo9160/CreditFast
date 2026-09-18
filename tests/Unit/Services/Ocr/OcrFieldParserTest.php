<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrFieldParser;
use PHPUnit\Framework\TestCase;

class OcrFieldParserTest extends TestCase
{
    public function test_extracts_salary_and_balance_from_french_bank_statement_text(): void
    {
        $parser = new OcrFieldParser;
        $text = "Relevé BOA Mali\nSalaire 450 000 FCFA\nSolde moyen 900 000 FCFA\nBamako Mali";

        $fields = $parser->parse($text, 'RELEVE_BANCAIRE');

        $this->assertSame(450000.0, $fields['verified_monthly_income']);
        $this->assertSame(900000.0, $fields['average_balance']);
        $this->assertSame('Bamako', $fields['address']);
        $this->assertArrayNotHasKey('verified_monthly_expenses', $fields);
    }

    public function test_leaves_income_absent_when_text_has_no_amounts(): void
    {
        $parser = new OcrFieldParser;

        $fields = $parser->parse('Carte nationale d’identité République du Mali Bamako', 'PIECE_IDENTITE');

        $this->assertArrayNotHasKey('verified_monthly_income', $fields);
        $this->assertSame([], $fields['amounts_detected']);
    }

    public function test_does_not_treat_a_calendar_year_as_an_amount(): void
    {
        $parser = new OcrFieldParser;
        $text = "BULLETIN DE PAIE\nSalaire net a payer 320000 FCFA\nPeriode septembre 2026 Bamako";

        $fields = $parser->parse($text, 'BULLETIN_PAIE');

        $this->assertSame(320000.0, $fields['verified_monthly_income']);
        $this->assertSame([320000.0], $fields['amounts_detected']);
    }
}
