<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MonitorSetting extends Model
{
    protected $fillable = [
        'enabled', 'interval_minutes', 'latency_warning_ms', 'dns_test_domain', 'internet_targets',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'interval_minutes' => 'integer',
            'latency_warning_ms' => 'integer',
            'internet_targets' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => true,
            'interval_minutes' => config('netrescue.default_interval_minutes', 5),
            'latency_warning_ms' => config('netrescue.latency_warning_ms', 100),
            'dns_test_domain' => config('netrescue.dns_test_domain', 'example.com'),
            'internet_targets' => config('netrescue.internet_targets', ['1.1.1.1:443', '8.8.8.8:53']),
        ]);
    }

    public function dueAfterSeconds(): int
    {
        return max(20, ($this->interval_minutes * 60) - 25);
    }

    public function isDue(?Carbon $lastAutomatic, ?Carbon $now = null): bool
    {
        if (! $lastAutomatic) {
            return true;
        }

        $now ??= now();

        return $lastAutomatic->diffInSeconds($now) >= $this->dueAfterSeconds();
    }

    public function nextAutomaticAt(?Carbon $lastAutomatic, ?Carbon $now = null): Carbon
    {
        $now = ($now ?? now())->copy();

        if (! $lastAutomatic || $this->isDue($lastAutomatic, $now)) {
            return $now->second < 8
                ? $now->copy()->startOfMinute()
                : $now->copy()->addMinute()->startOfMinute();
        }

        $dueAt = $lastAutomatic->copy()->addSeconds($this->dueAfterSeconds());
        $tick = $dueAt->copy()->startOfMinute();

        if ($tick->lt($dueAt)) {
            $tick->addMinute();
        }

        if ($tick->lte($now)) {
            $tick = $now->copy()->addMinute()->startOfMinute();
        }

        return $tick;
    }
}
