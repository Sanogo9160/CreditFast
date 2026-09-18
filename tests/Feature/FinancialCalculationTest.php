<?php

namespace Tests\Feature;

use App\Enums\RepaymentCapacityStatus;
use App\Services\FinancialCalculationService;
use Tests\TestCase;

class FinancialCalculationTest extends TestCase
{
    public function test_disposable_income_calculation(): void
    {
        $service = new FinancialCalculationService;
        $disposable = $service->calculateDisposableIncome(500000, 100000, 200000, 50000);

        $this->assertEquals(350000.0, $disposable);
    }

    public function test_estimated_monthly_payment_calculation(): void
    {
        $service = new FinancialCalculationService;
        $monthlyPayment = $service->calculateEstimatedMonthlyPayment(1200000, 12, 12.0);

        $this->assertGreaterThan(100000, $monthlyPayment);
        $this->assertLessThan(120000, $monthlyPayment);
    }

    public function test_repayment_capacity_evaluation(): void
    {
        $service = new FinancialCalculationService;

        $sufficient = $service->evaluateRepaymentCapacity(300000, 100000, 20.0);
        $this->assertEquals(RepaymentCapacityStatus::Sufficient, $sufficient);

        $insufficient = $service->evaluateRepaymentCapacity(100000, 95000, 20.0);
        $this->assertEquals(RepaymentCapacityStatus::Insufficient, $insufficient);
    }

    public function test_debt_to_income_ratio_calculation(): void
    {
        $service = new FinancialCalculationService;

        $this->assertEquals(30.0, $service->calculateDebtToIncomeRatio(0, 50000, 100000, 500000));
        $this->assertEquals(100.0, $service->calculateDebtToIncomeRatio(0, 0, 100000, 0));
    }

    public function test_preview_schedule_starts_the_month_after_the_value_date(): void
    {
        $service = new FinancialCalculationService;
        $rows = $service->previewSchedule(1200000, 3, 12.0, now());

        $this->assertCount(3, $rows);
        $this->assertEquals(now()->addMonths(1)->toDateString(), $rows[0]['due_date']);
        $this->assertEquals(now()->addMonths(3)->toDateString(), $rows[2]['due_date']);
    }
}
