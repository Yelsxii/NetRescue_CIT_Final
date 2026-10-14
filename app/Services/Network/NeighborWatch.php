<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Models\Incident;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class NeighborWatch
{
    public function observe(): void
    {
        if (app()->runningUnitTests() || PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $local = Device::localHost();
        $prefix = $this->prefix((string) $local->last_ip);
        if ($prefix === null) {
            return;
        }

        foreach ($this->neighbors() as $ip) {
            if (! str_starts_with($ip, $prefix.'.') || $ip === $local->last_ip || $ip === $local->gateway_ip) {
                continue;
            }

            $device = Device::query()->where('last_ip', $ip)->where('is_local', false)->first();
            if ($device) {
                $device->update(['last_seen_at' => now()]);
                continue;
            }

            Device::query()->create([
                'device_uuid' => (string) Str::uuid(),
                'display_name' => $ip,
                'hostname' => $ip,
                'connection_type' => 'seen',
                'is_local' => false,
                'recognized' => false,
                'last_ip' => $ip,
                'status' => 'unknown',
                'last_seen_at' => now(),
            ]);
        }
    }

    public function checkRecognized(): void
    {
        if (app()->runningUnitTests() || PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $devices = Device::query()->where('recognized', true)->where('is_local', false)->get();

        foreach ($devices as $device) {
            if (! filter_var($device->last_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }

            $answered = $this->answers($device->last_ip);
            $device->update([
                'status' => $answered ? 'healthy' : 'offline',
                'last_seen_at' => $answered ? now() : $device->last_seen_at,
                'last_health_check_at' => now(),
            ]);

            $open = Incident::query()
                ->where('device_id', $device->id)
                ->where('status', 'active')
                ->where('code', 'device_unreachable')
                ->first();

            if ($answered && $open) {
                $open->update(['status' => 'resolved', 'resolved_at' => now(), 'last_detected_at' => now()]);
            }

            if (! $answered && ! $open) {
                Incident::query()->create([
                    'device_id' => $device->id,
                    'code' => 'device_unreachable',
                    'title' => 'Computer not responding',
                    'severity' => 'high',
                    'status' => 'active',
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                    'occurrence_count' => 1,
                    'summary' => 'This recognized computer did not answer.',
                    'recommended_action' => 'Check power and the cable or Wi-Fi on that computer. A repair command cannot run there from this console.',
                ]);
            }
        }
    }

    private function prefix(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $parts = explode('.', $ip);

        return $parts[0].'.'.$parts[1].'.'.$parts[2];
    }

    private function neighbors(): array
    {
        $process = new Process([
            'powershell.exe', '-NoProfile', '-Command',
            "Get-NetNeighbor -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { \$_.State -in 'Reachable','Stale' } | Select-Object -ExpandProperty IPAddress",
        ]);
        $process->setTimeout(12);

        try {
            $process->run();
        } catch (\Throwable) {
            return [];
        }

        if (! $process->isSuccessful()) {
            return [];
        }

        $ips = [];
        foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $line) {
            $ip = trim($line);
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            if (str_starts_with($ip, '169.254.') || str_starts_with($ip, '224.') || str_starts_with($ip, '127.')) {
                continue;
            }
            $last = (int) explode('.', $ip)[3];
            if ($last === 0 || $last === 255) {
                continue;
            }
            $ips[$ip] = $ip;
            if (count($ips) >= 24) {
                break;
            }
        }

        return array_values($ips);
    }

    private function answers(string $ip): bool
    {
        $process = new Process(['powershell.exe', '-NoProfile', '-Command', "Test-Connection -ComputerName '".str_replace("'", '', $ip)."' -Count 1 -TimeoutSeconds 1 -Quiet"]);
        $process->setTimeout(8);

        try {
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        return trim($process->getOutput()) === 'True';
    }
}
