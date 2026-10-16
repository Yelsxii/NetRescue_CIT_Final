<?php

namespace App\Support;

class Ipv4Address
{
    public static function usable(?string $ip): bool
    {
        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
        $first = (int) explode('.', $ip)[0];

        // An address string alone is not proof of a usable host assignment.
        return $first > 0 && $first !== 127 && $first < 224 && ! str_starts_with($ip, '169.254.');
    }
}
