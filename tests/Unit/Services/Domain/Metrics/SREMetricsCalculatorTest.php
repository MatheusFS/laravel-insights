<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Services\Domain\Metrics;

use MatheusFS\Laravel\Insights\Services\Domain\Metrics\SREMetricsCalculator;
use PHPUnit\Framework\TestCase;

class SREMetricsCalculatorTest extends TestCase
{
    private SREMetricsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SREMetricsCalculator();
    }

    public function test_calculates_perfect_sli_with_zero_errors(): void
    {
        $result = $this->calculator->calculateForService(
            total_requests: 10000,
            total_5xx: 0,
            slo_target: 98.5,
            sla_target: 95.0
        );

        // SLI deve ser 100%
        $this->assertEquals(100.0, $result['sli']['value']);
        $this->assertEquals('%', $result['sli']['unit']);

        // SLO não deve estar violado
        $this->assertFalse($result['slo']['breached']);
        $this->assertEquals(98.5, $result['slo']['target']);

        // SLA não deve estar em risco
        $this->assertFalse($result['sla']['at_risk']);
        $this->assertEquals(95.0, $result['sla']['target']);

        // Error Budget
        $this->assertEquals(5.0, $result['error_budget']['total']); // 1 - 0.95 = 0.05 = 5%
        $this->assertEquals(0.0, $result['error_budget']['used']);
        $this->assertEquals(5.0, $result['error_budget']['remaining']);
        $this->assertFalse($result['error_budget']['depleted']);

        // Status
        $this->assertTrue($result['status']['operational']);
        $this->assertTrue($result['status']['healthy']);
    }

    public function test_calculates_sli_with_errors_within_slo(): void
    {
        // 10000 requests, 100 erros 5xx = 99% SLI (dentro do SLO de 98.5%)
        $result = $this->calculator->calculateForService(
            total_requests: 10000,
            total_5xx: 100,
            slo_target: 98.5,
            sla_target: 95.0
        );

        // SLI = 1 - (100/10000) = 0.99 = 99%
        $this->assertEquals(99.0, $result['sli']['value']);

        // SLO não deve estar violado (99% > 98.5%)
        $this->assertFalse($result['slo']['breached']);

        // SLA não deve estar em risco
        $this->assertFalse($result['sla']['at_risk']);

        // Error Budget
        $this->assertEquals(5.0, $result['error_budget']['total']);
        $this->assertEquals(1.0, $result['error_budget']['used']); // 100/10000 = 1%
        $this->assertEquals(4.0, $result['error_budget']['remaining']); // 5% - 1% = 4%
        $this->assertFalse($result['error_budget']['depleted']);

        // Status
        $this->assertTrue($result['status']['operational']);
        $this->assertTrue($result['status']['healthy']);
    }

    public function test_detects_slo_violation(): void
    {
        // 10000 requests, 200 erros 5xx = 98% SLI (abaixo do SLO de 98.5%)
        $result = $this->calculator->calculateForService(
            total_requests: 10000,
            total_5xx: 200,
            slo_target: 98.5,
            sla_target: 95.0
        );

        // SLI = 1 - (200/10000) = 0.98 = 98%
        $this->assertEquals(98.0, $result['sli']['value']);

        // SLO deve estar violado (98% < 98.5%)
        $this->assertTrue($result['slo']['breached']);

        // SLA ainda não está em risco (98% > 95%)
        $this->assertFalse($result['sla']['at_risk']);

        // Error Budget
        $this->assertEquals(2.0, $result['error_budget']['used']); // 200/10000 = 2%
        $this->assertEquals(3.0, $result['error_budget']['remaining']); // 5% - 2% = 3%
        $this->assertFalse($result['error_budget']['depleted']);

        // Status
        $this->assertFalse($result['status']['operational']); // SLO violado
        $this->assertFalse($result['status']['healthy']);
        $this->assertTrue($result['status']['slo_violation']);
    }

    public function test_detects_sla_risk(): void
    {
        // 10000 requests, 600 erros 5xx = 94% SLI (abaixo do SLA de 95%)
        $result = $this->calculator->calculateForService(
            total_requests: 10000,
            total_5xx: 600,
            slo_target: 98.5,
            sla_target: 95.0
        );

        // SLI = 1 - (600/10000) = 0.94 = 94%
        $this->assertEquals(94.0, $result['sli']['value']);

        // SLO deve estar violado
        $this->assertTrue($result['slo']['breached']);

        // SLA deve estar em risco (94% < 95%)
        $this->assertTrue($result['sla']['at_risk']);

        // Error Budget esgotado
        $this->assertEquals(5.0, $result['error_budget']['total']);
        $this->assertEquals(6.0, $result['error_budget']['used']); // 600/10000 = 6%
        $this->assertEquals(-1.0, $result['error_budget']['remaining']); // 5% - 6% = -1%
        $this->assertTrue($result['error_budget']['depleted']);

        // Status
        $this->assertFalse($result['status']['operational']);
        $this->assertFalse($result['status']['healthy']);
        $this->assertTrue($result['status']['sla_risk']);
    }

    public function test_handles_zero_requests(): void
    {
        $result = $this->calculator->calculateForService(
            total_requests: 0,
            total_5xx: 0
        );

        // Deve retornar métricas vazias mas válidas
        $this->assertEquals(100.0, $result['sli']['value']);
        $this->assertEquals(0, $result['raw']['total_requests']);
        $this->assertEquals(0, $result['raw']['total_5xx']);
        $this->assertTrue($result['status']['healthy']);
    }

    public function test_calculates_for_multiple_services(): void
    {
        $services = [
            'API' => [
                'total_requests' => 50000,
                'total_5xx' => 100,
            ],
            'UI' => [
                'total_requests' => 30000,
                'total_5xx' => 50,
            ],
        ];

        $result = $this->calculator->calculateForMultipleServices($services, 98.5, 95.0);

        // Verificar que ambos os serviços foram calculados
        $this->assertArrayHasKey('API', $result);
        $this->assertArrayHasKey('UI', $result);

        // API: 50000 - 100 = 99.8% SLI
        $this->assertEquals(99.8, $result['API']['sli']['value']);
        $this->assertFalse($result['API']['slo']['breached']);

        // UI: 30000 - 50 = 99.833...% SLI
        $this->assertGreaterThan(99.8, $result['UI']['sli']['value']);
        $this->assertFalse($result['UI']['slo']['breached']);
    }

    public function test_sli_formula_is_correct(): void
    {
        // SLI = 1 - (total_5xx / total_requests)
        $result = $this->calculator->calculateForService(
            total_requests: 1000,
            total_5xx: 25, // 2.5% de erros
            slo_target: 98.5,
            sla_target: 95.0
        );

        // SLI deve ser 97.5% (1 - 0.025 = 0.975)
        $this->assertEquals(97.5, $result['sli']['value']);
    }

    public function test_error_budget_formula_is_correct(): void
    {
        // Error Budget Total = 1 - SLA
        // Error Budget Used = total_5xx / total_requests
        // Error Budget Remaining = Total - Used
        $result = $this->calculator->calculateForService(
            total_requests: 1000,
            total_5xx: 30, // 3% de erros
            slo_target: 98.5,
            sla_target: 95.0 // 5% de budget
        );

        $this->assertEquals(5.0, $result['error_budget']['total']);
        $this->assertEquals(3.0, $result['error_budget']['used']);
        $this->assertEquals(2.0, $result['error_budget']['remaining']); // 5% - 3% = 2%
        $this->assertFalse($result['error_budget']['depleted']);
    }

    public function test_precision_with_small_numbers(): void
    {
        // Testar com números pequenos para garantir precisão
        $result = $this->calculator->calculateForService(
            total_requests: 100,
            total_5xx: 1,
            slo_target: 98.5,
            sla_target: 95.0
        );

        // SLI = 1 - (1/100) = 99%
        $this->assertEquals(99.0, $result['sli']['value']);
        $this->assertEquals(1.0, $result['error_budget']['used']);
    }

    public function test_handles_negative_values_gracefully(): void
    {
        $result = $this->calculator->calculateForService(
            total_requests: -100,
            total_5xx: -10
        );

        // Deve retornar métricas vazias para valores inválidos
        $this->assertEquals(100.0, $result['sli']['value']);
        $this->assertTrue($result['status']['healthy']);
    }
}
