<?php

return [
    'version' => '3.0.0-cit',
    'supported_os' => 'Windows 10/11',
    'default_interval_minutes' => (int) env('NETRESCUE_DEFAULT_INTERVAL', 5),
    'latency_warning_ms' => (int) env('NETRESCUE_LATENCY_WARNING_MS', 100),
    'dns_test_domain' => env('NETRESCUE_DNS_TEST_DOMAIN', 'example.com'),
    'internet_targets' => array_values(array_filter(array_map('trim', explode(',', (string) env('NETRESCUE_INTERNET_TARGETS', '1.1.1.1:443,8.8.8.8:53'))))),
    'timeouts' => [
        'powershell_seconds' => 20,
        'ping_seconds' => 5,
        // A completed Windows resolver timeout took about 9s on this laptop.
        // Include PowerShell startup so an unreachable DNS server can return
        // its measured result; an outer process timeout remains unmeasured.
        'dns_seconds' => 20,
        'socket_seconds' => 2.5,
        'recovery_seconds' => 35,
        // Adapter state changes may still be running when the old 35s cap ends.
        'adapter_recovery_seconds' => 60,
    ],
    'verification' => [
        // Re-read after transient reconnect/reader failures; never repeat the command.
        'attempts' => 3,
        'retry_delay_seconds' => 1,
        // Every follow-up uses timeouts.powershell_seconds, including the first.
        'reconnect_window_seconds' => 60,
        'reconnect_poll_seconds' => 2,
    ],
    'health_weights' => [
        'gateway' => 30,
        'internet' => 30,
        'dns' => 20,
        'response' => 20,
    ],
];
