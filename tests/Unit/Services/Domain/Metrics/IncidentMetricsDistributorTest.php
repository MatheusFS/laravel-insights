<?php

namespace Tests\Unit\Services\Domain\Metrics;

use MatheusFS\Laravel\Insights\Services\Domain\Metrics\IncidentMetricsDistributor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MatheusFS\Laravel\Insights\Services\Domain\Metrics\IncidentMetricsDistributor
 */
class IncidentMetricsDistributorTest extends TestCase
{
    private IncidentMetricsDistributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->distributor = new IncidentMetricsDistributor();
    }

    /** @test */
    public function it_distributes_metrics_by_duration_across_two_days(): void
    {
        // Caso real: INC-2026-001
        $result = $this->distributor->distributeByDuration(
            totals: [
                'errors_5xx' => 1078,
                'total_requests' => 182108,
            ],
            started_at: '2026-02-02T22:25:00',
            restored_at: '2026-02-03T12:50:00',
            timezone: 'America/Sao_Paulo'
        );

        // Verificar estrutura
        $this->assertArrayHasKey('2026-02-02', $result);
        $this->assertArrayHasKey('2026-02-03', $result);

        // Verificar somas exatas
        $total_errors = $result['2026-02-02']['errors_5xx'] + $result['2026-02-03']['errors_5xx'];
        $total_requests = $result['2026-02-02']['total_requests'] + $result['2026-02-03']['total_requests'];

        $this->assertEquals(1078, $total_errors);
        $this->assertEquals(182108, $total_requests);

        // Verificar que proporções estão corretas (~31.7% e ~68.3%)
        $this->assertGreaterThan(300, $result['2026-02-02']['errors_5xx']);
        $this->assertLessThan(400, $result['2026-02-02']['errors_5xx']);
        $this->assertGreaterThan(700, $result['2026-02-03']['errors_5xx']);
        $this->assertLessThan(800, $result['2026-02-03']['errors_5xx']);
    }

    /** @test */
    public function it_distributes_metrics_across_single_day(): void
    {
        $result = $this->distributor->distributeByDuration(
            totals: ['errors_5xx' => 100],
            started_at: '2026-02-02T10:00:00',
            restored_at: '2026-02-02T14:00:00',
            timezone: 'UTC'
        );

        // Deve ter apenas 1 dia
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('2026-02-02', $result);
        $this->assertEquals(100, $result['2026-02-02']['errors_5xx']);
    }

    /** @test */
    public function it_distributes_metrics_across_three_days(): void
    {
        $result = $this->distributor->distributeByDuration(
            totals: ['errors_5xx' => 300],
            started_at: '2026-02-01T22:00:00',
            restored_at: '2026-02-03T14:00:00',
            timezone: 'UTC'
        );

        // Deve ter 3 dias
        $this->assertCount(3, $result);
        $this->assertArrayHasKey('2026-02-01', $result);
        $this->assertArrayHasKey('2026-02-02', $result);
        $this->assertArrayHasKey('2026-02-03', $result);

        // Soma deve ser exata
        $total = $result['2026-02-01']['errors_5xx'] + 
                 $result['2026-02-02']['errors_5xx'] + 
                 $result['2026-02-03']['errors_5xx'];
        $this->assertEquals(300, $total);
    }

    /** @test */
    public function it_throws_exception_if_restored_before_started(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('restored_at must be after started_at');

        $this->distributor->distributeByDuration(
            totals: ['errors_5xx' => 100],
            started_at: '2026-02-02T14:00:00',
            restored_at: '2026-02-02T10:00:00', // ANTES do started_at
            timezone: 'UTC'
        );
    }

    /** @test */
    public function it_distributes_equally_across_periods(): void
    {
        $result = $this->distributor->distributeEqually(
            totals: ['errors_5xx' => 100],
            periods: ['period1', 'period2', 'period3']
        );

        $this->assertArrayHasKey('period1', $result);
        $this->assertArrayHasKey('period2', $result);
        $this->assertArrayHasKey('period3', $result);

        // Soma deve ser exata
        $total = $result['period1']['errors_5xx'] + 
                 $result['period2']['errors_5xx'] + 
                 $result['period3']['errors_5xx'];
        $this->assertEquals(100, $total);

        // Valores devem ser aproximadamente iguais (33, 33, 34)
        $this->assertEqualsWithDelta(33, $result['period1']['errors_5xx'], 1);
        $this->assertEqualsWithDelta(33, $result['period2']['errors_5xx'], 1);
        $this->assertEqualsWithDelta(34, $result['period3']['errors_5xx'], 1);
    }

    /** @test */
    public function it_distributes_with_custom_proportions(): void
    {
        $result = $this->distributor->distributeWithProportions(
            totals: ['errors_5xx' => 1000],
            proportions: [
                'morning' => 0.25,
                'afternoon' => 0.35,
                'evening' => 0.40,
            ]
        );

        // Soma deve ser exata
        $total = $result['morning']['errors_5xx'] + 
                 $result['afternoon']['errors_5xx'] + 
                 $result['evening']['errors_5xx'];
        $this->assertEquals(1000, $total);

        // Proporções aproximadas
        $this->assertEqualsWithDelta(250, $result['morning']['errors_5xx'], 5);
        $this->assertEqualsWithDelta(350, $result['afternoon']['errors_5xx'], 5);
        $this->assertEqualsWithDelta(400, $result['evening']['errors_5xx'], 5);
    }

    /** @test */
    public function it_distributes_by_service_and_duration(): void
    {
        $result = $this->distributor->distributeByServiceAndDuration(
            metrics_by_service: [
                'API' => ['errors_5xx' => 1078, 'total_requests' => 182108],
                'UI' => ['errors_5xx' => 562, 'total_requests' => 107565],
            ],
            started_at: '2026-02-02T22:25:00',
            restored_at: '2026-02-03T12:50:00',
            timezone: 'America/Sao_Paulo'
        );

        // Verificar estrutura [date => [service => [metric => value]]]
        $this->assertArrayHasKey('2026-02-02', $result);
        $this->assertArrayHasKey('2026-02-03', $result);
        $this->assertArrayHasKey('API', $result['2026-02-02']);
        $this->assertArrayHasKey('UI', $result['2026-02-02']);
        $this->assertArrayHasKey('API', $result['2026-02-03']);
        $this->assertArrayHasKey('UI', $result['2026-02-03']);

        // Verificar somas exatas por serviço
        $api_errors_total = $result['2026-02-02']['API']['errors_5xx'] + 
                            $result['2026-02-03']['API']['errors_5xx'];
        $ui_errors_total = $result['2026-02-02']['UI']['errors_5xx'] + 
                           $result['2026-02-03']['UI']['errors_5xx'];

        $this->assertEquals(1078, $api_errors_total);
        $this->assertEquals(562, $ui_errors_total);
    }

    /** @test */
    public function it_handles_multiple_metrics_simultaneously(): void
    {
        $result = $this->distributor->distributeByDuration(
            totals: [
                'errors_5xx' => 1078,
                'errors_4xx' => 500,
                'total_requests' => 182108,
            ],
            started_at: '2026-02-02T22:25:00',
            restored_at: '2026-02-03T12:50:00',
            timezone: 'America/Sao_Paulo'
        );

        // Verificar que todas as métricas foram distribuídas
        $this->assertArrayHasKey('errors_5xx', $result['2026-02-02']);
        $this->assertArrayHasKey('errors_4xx', $result['2026-02-02']);
        $this->assertArrayHasKey('total_requests', $result['2026-02-02']);

        // Verificar somas exatas
        $this->assertEquals(1078, 
            $result['2026-02-02']['errors_5xx'] + $result['2026-02-03']['errors_5xx']
        );
        $this->assertEquals(500, 
            $result['2026-02-02']['errors_4xx'] + $result['2026-02-03']['errors_4xx']
        );
        $this->assertEquals(182108, 
            $result['2026-02-02']['total_requests'] + $result['2026-02-03']['total_requests']
        );
    }

    /** @test */
    public function it_respects_timezone_boundaries(): void
    {
        // Incidente que cruza meia-noite (23:30 até 01:30 = 2 horas)
        // Usando UTC com Z para garantir parsing correto
        $result = $this->distributor->distributeByDuration(
            totals: ['errors_5xx' => 100],
            started_at: '2026-02-02T23:30:00Z',
            restored_at: '2026-02-03T01:30:00Z',
            timezone: 'UTC'
        );

        // Deve ter 2 dias
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('2026-02-02', $result);
        $this->assertArrayHasKey('2026-02-03', $result);

        // Soma deve ser exata
        $total = $result['2026-02-02']['errors_5xx'] + 
                 $result['2026-02-03']['errors_5xx'];
        $this->assertEquals(100, $total);
    }

    /** @test */
    public function it_throws_exception_for_empty_periods(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Periods array cannot be empty');

        $this->distributor->distributeEqually(
            totals: ['errors_5xx' => 100],
            periods: []
        );
    }
}
