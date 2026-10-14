<?php

namespace App\Support;

final class NetworkDiagnosis
{
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly string $severity,
        public readonly string $summary,
        public readonly array $details = [],
        public readonly ?string $recommendedAction = null,
        public readonly ?string $recoveryActionCode = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'severity' => $this->severity,
            'summary' => $this->summary,
            'details' => $this->details,
            'recommended_action' => $this->recommendedAction,
            'recovery_action_code' => $this->recoveryActionCode,
        ];
    }
}
