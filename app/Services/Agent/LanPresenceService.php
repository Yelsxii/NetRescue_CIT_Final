<?php

namespace App\Services\Agent;

use App\Services\Network\NetworkProbeService;

class LanPresenceService
{
    public function __construct(
        private readonly NetworkProbeService $networkProbe,
    ) {
    }

    /**
     * The router this Central laptop is using right now, plus the key for that router.
     * The key stays the same while the gateway stays the same, and a new router issues a new key.
     *
     * @return array{fingerprint: string, local_ip: string, gateway_ip: string, prefix: string, hostname: string, pairing_key: string}
     */
    public function snapshot(): array
    {
        if (app()->runningUnitTests()) {
            return [
                'fingerprint' => '203.0.113.1',
                'local_ip' => '203.0.113.50',
                'gateway_ip' => '203.0.113.1',
                'prefix' => '203.0.113',
                'hostname' => 'CENTRAL-TEST',
                'pairing_key' => 'T3ST-K3Y1',
            ];
        }

        $stored = $this->read();
        $checkedAt = isset($stored['checked_at']) ? strtotime((string) $stored['checked_at']) : false;
        if ($stored && $checkedAt && (time() - $checkedAt) < 30 && ($stored['local_ip'] ?? '') !== '') {
            return $stored;
        }

        $live = $this->probeLive();

        if ($live['local_ip'] === '' && $live['gateway_ip'] === '') {
            return $stored ?: $this->blank();
        }

        $next = $this->reconcile($stored, $live['local_ip'], $live['gateway_ip']);
        $next['checked_at'] = now()->toIso8601String();
        $this->write($next);

        return $next;
    }

    public function rotateKey(): array
    {
        $current = $this->snapshot();
        $current['pairing_key'] = $this->makeKey();
        $current['fingerprint'] = $current['gateway_ip'] !== '' ? $current['gateway_ip'] : $current['local_ip'];

        if (! app()->runningUnitTests()) {
            $this->write($current);
        }

        return $current;
    }

    public function keyMatches(string $key): bool
    {
        $key = strtoupper((string) preg_replace('/\s+/', '', trim($key)));
        $current = strtoupper((string) ($this->snapshot()['pairing_key'] ?? ''));

        if ($current === '' || ! preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key)) {
            return false;
        }

        return hash_equals($current, $key);
    }

    public function sameLan(string $remoteIp): bool
    {
        $remoteIp = trim($remoteIp);
        $prefix = (string) ($this->snapshot()['prefix'] ?? '');

        return $prefix !== ''
            && filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && str_starts_with($remoteIp, $prefix.'.');
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{fingerprint: string, local_ip: string, gateway_ip: string, prefix: string, hostname: string, pairing_key: string}
     */
    public function reconcile(?array $stored, string $localIp, string $gatewayIp): array
    {
        $fingerprint = $gatewayIp !== '' ? $gatewayIp : $localIp;
        $pairingKey = strtoupper((string) ($stored['pairing_key'] ?? ''));
        $sameRouter = $stored && ($stored['fingerprint'] ?? '') === $fingerprint && $pairingKey !== '';

        return [
            'fingerprint' => $fingerprint,
            'local_ip' => $localIp,
            'gateway_ip' => $gatewayIp,
            'prefix' => $this->prefixOf($localIp),
            'hostname' => (string) (gethostname() ?: 'central'),
            'pairing_key' => $sameRouter ? $pairingKey : $this->makeKey(),
        ];
    }

    /**
     * @return array{local_ip: string, gateway_ip: string}
     */
    private function probeLive(): array
    {
        try {
            $configuration = $this->networkProbe->collectConfiguration();
        } catch (\Throwable) {
            return ['local_ip' => '', 'gateway_ip' => ''];
        }

        return [
            'local_ip' => trim((string) ($configuration['local_ip'] ?? '')),
            'gateway_ip' => trim((string) ($configuration['gateway_ip'] ?? '')),
        ];
    }

    /**
     * @return array{fingerprint: string, local_ip: string, gateway_ip: string, prefix: string, hostname: string, pairing_key: string}|null
     */
    private function read(): ?array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function write(array $snapshot): void
    {
        $directory = dirname($this->path());
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->path(), json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function path(): string
    {
        return storage_path('app/lan-presence.json');
    }

    private function prefixOf(string $ip): string
    {
        $parts = explode('.', $ip);

        return count($parts) === 4 ? $parts[0].'.'.$parts[1].'.'.$parts[2] : '';
    }

    private function makeKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($alphabet) - 1;
        $chunk = '';

        $build = function () use ($alphabet, $length, &$chunk): string {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= $alphabet[random_int(0, $length)];
            }

            return $chunk;
        };

        return $build().'-'.$build();
    }

    /**
     * @return array{fingerprint: string, local_ip: string, gateway_ip: string, prefix: string, hostname: string, pairing_key: string}
     */
    private function blank(): array
    {
        return [
            'fingerprint' => '',
            'local_ip' => '',
            'gateway_ip' => '',
            'prefix' => '',
            'hostname' => (string) (gethostname() ?: 'central'),
            'pairing_key' => '',
        ];
    }
}
