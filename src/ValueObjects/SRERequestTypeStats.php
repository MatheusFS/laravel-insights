<?php

namespace MatheusFS\Laravel\Insights\ValueObjects;

final class SRERequestTypeStats
{
    public function __construct(
        public int $total_requests,
        public int $errors_5xx
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (int)($data['total_requests'] ?? 0),
            (int)($data['errors_5xx'] ?? 0)
        );
    }

    public function toArray(): array
    {
        return [
            'total_requests' => $this->total_requests,
            'errors_5xx' => $this->errors_5xx,
        ];
    }
}
