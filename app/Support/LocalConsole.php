<?php

namespace App\Support;

use Illuminate\Http\Request;

class LocalConsole
{
    /** The console is used on the computer running NetRescue. */
    public static function allows(Request $request): bool
    {
        $remote = self::normalize((string) $request->server('REMOTE_ADDR', ''));

        if ($remote === '') {
            return false;
        }

        // Localhost requests do not need interface enumeration or a hostname
        // lookup, especially while the computer's network is disconnected.
        if (in_array($remote, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
            return true;
        }
        return in_array($remote, self::machineAddresses($request), true);
    }

    /**
     * @return list<string>
     */
    private static function machineAddresses(Request $request): array
    {
        $addresses = ['127.0.0.1', '::1'];

        foreach (['SERVER_ADDR', 'LOCAL_ADDR'] as $key) {
            $addresses[] = self::normalize((string) $request->server($key, ''));
        }

        if (function_exists('net_get_interfaces')) {
            // Enumeration can fail during a network transition or in a restricted
            // host. Keep the explicit local addresses; do not turn denial into 500.
            try {
                $interfaces = @net_get_interfaces() ?: [];
            } catch (\Throwable) {
                $interfaces = [];
            }
            foreach ($interfaces as $interface) {
                foreach ($interface['unicast'] ?? [] as $address) {
                    $addresses[] = self::normalize((string) ($address['address'] ?? ''));
                }
            }
        }

        $hostname = gethostname();
        if (is_string($hostname) && $hostname !== '') {
            $resolved = @gethostbynamel($hostname);
            if (is_array($resolved)) {
                foreach ($resolved as $address) {
                    $addresses[] = self::normalize((string) $address);
                }
            }
        }

        return array_values(array_unique(array_filter(
            $addresses,
            fn (string $address) => $address !== '' && ! in_array($address, ['0.0.0.0', '::'], true)
        )));
    }

    private static function normalize(string $address): string
    {
        $address = strtolower(trim($address));
        $zone = strpos($address, '%');

        if ($zone !== false) {
            $address = substr($address, 0, $zone);
        }

        return $address;
    }
}
