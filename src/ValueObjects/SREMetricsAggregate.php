<?php

namespace MatheusFS\Laravel\Insights\ValueObjects;

final class SREMetricsAggregate
{
    public function __construct(
        public SRERequestTypeStats $api,
        public SRERequestTypeStats $ui,
        public SRERequestTypeStats $bot,
        public SRERequestTypeStats $assets,
        public string $start,
        public string $end,
        public string $timestamp
    ) {}

    public static function fromArray(array $data): self
    {
        $byRequestType = $data['by_request_type'] ?? [];
        $period = $data['period'] ?? [];

        return new self(
            SRERequestTypeStats::fromArray($byRequestType['API'] ?? []),
            SRERequestTypeStats::fromArray($byRequestType['UI'] ?? []),
            SRERequestTypeStats::fromArray($byRequestType['BOT'] ?? []),
            SRERequestTypeStats::fromArray($byRequestType['ASSETS'] ?? []),
            (string)($period['start'] ?? ''),
            (string)($period['end'] ?? ''),
            (string)($data['timestamp'] ?? '')
        );
    }

    public function totalRequests(): int
    {
        return $this->api->total_requests
            + $this->ui->total_requests
            + $this->bot->total_requests
            + $this->assets->total_requests;
    }

    public function toArray(): array
    {
        return [
            'by_request_type' => [
                'API' => $this->api->toArray(),
                'UI' => $this->ui->toArray(),
                'BOT' => $this->bot->toArray(),
                'ASSETS' => $this->assets->toArray(),
            ],
            'period' => [
                'start' => $this->start,
                'end' => $this->end,
            ],
            'timestamp' => $this->timestamp,
        ];
    }
}
