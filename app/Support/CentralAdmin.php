<?php

namespace App\Support;

use Illuminate\Http\Request;

class CentralAdmin
{
    /**
     * The admin console belongs to the computer running NetRescue Central.
     * A request is local when its source address is one of this machine's
     * own addresses. Other LAN computers keep their agent access, but they
     * do not become administrators by opening the website.
     */
    public static function allows(Request $request): bool
    {
        $remote = self::normalize((string) $request->server('REMOTE_ADDR', ''));

        if ($remote === '') {
            return false;
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
            foreach (net_get_interfaces() ?: [] as $interface) {
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
