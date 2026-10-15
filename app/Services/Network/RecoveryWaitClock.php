<?php

namespace App\Services\Network;

class RecoveryWaitClock
{
    public function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    public function wait(float $seconds): void
    {
        if ($seconds > 0) usleep((int) ($seconds * 1_000_000));
    }
}
