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
        'dns_seconds' => 6,
        'socket_seconds' => 2.5,
        'recovery_seconds' => 35,
    ],
    'verification' => [
        // Re-read after transient reconnect/reader failures; never repeat the command.
        'attempts' => 3,
        'retry_delay_seconds' => 1,
        // Try a fresh snapshot promptly after link changes. Later readings keep
        // the normal configuration allowance; the repair command is never repeated.
        'initial_configuration_seconds' => 5,
    ],
    'health_weights' => [
        'gateway' => 30,
        'internet' => 30,
        'dns' => 20,
        'response' => 20,
    ],
];
