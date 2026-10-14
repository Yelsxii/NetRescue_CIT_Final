<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\EnrollmentCode;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Optional screenshot data. Do not call this from DatabaseSeeder.
 * Run it only when you explicitly want sample computers:
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        MonitorSetting::current();
        EnrollmentCode::current();

        $central = Device::localHost();

        $devices = [
            [
                'uuid' => '10000000-0000-0000-0000-000000000001',
                'display_name' => 'LAB-PC-01',
                'hostname' => 'LAB-PC-01',
                'ip' => '192.168.1.21',
                'status' => 'healthy',
                'health' => 100,
                'gateway' => 4.1,
                'internet' => 21.4,
                'dns' => 18.8,
                'summary' => 'All connectivity checks are stable.',
            ],
            [
                'uuid' => '10000000-0000-0000-0000-000000000002',
                'display_name' => 'LAB-PC-02',
                'hostname' => 'LAB-PC-02',
                'ip' => '192.168.1.22',
                'status' => 'healthy',
                'health' => 96,
                'gateway' => 5.7,
                'internet' => 26.1,
                'dns' => 23.0,
                'summary' => 'Connectivity is healthy with normal latency.',
            ],
            [
                'uuid' => '10000000-0000-0000-0000-000000000003',
                'display_name' => 'LAB-PC-07',
                'hostname' => 'LAB-PC-07',
                'ip' => '192.168.1.27',
                'status' => 'degraded',
                'health' => 74,
                'gateway' => 63.8,
                'internet' => 112.6,
                'dns' => 95.4,
                'summary' => 'Connectivity works but response time is above the warning threshold.',
            ],
            [
                'uuid' => '10000000-0000-0000-0000-000000000004',
                'display_name' => 'FACULTY-PC-01',
                'hostname' => 'FACULTY-PC-01',
                'ip' => '192.168.1.41',
                'status' => 'critical',
                'health' => 52,
                'gateway' => 7.8,
                'internet' => null,
                'dns' => null,
                'summary' => 'The local network responds but outbound Internet and DNS checks are failing.',
            ],
            [
                'uuid' => '10000000-0000-0000-0000-000000000005',
                'display_name' => 'LAB-PC-12',
                'hostname' => 'LAB-PC-12',
                'ip' => '192.168.1.32',
                'status' => 'offline',
                'health' => 18,
                'gateway' => null,
                'internet' => null,
                'dns' => null,
                'summary' => 'The device has not responded during the latest monitoring window.',
            ],
        ];

        foreach ($devices as $index => $seed) {
            $device = Device::query()->updateOrCreate(
                ['device_uuid' => $seed['uuid']],
                [
                    'display_name' => $seed['display_name'],
                    'hostname' => $seed['hostname'],
                    'connection_type' => 'discovered',
                    'is_local' => false,
                    'operating_system' => 'Windows 11 Pro',
                    'os_version' => '23H2',
                    'last_ip' => $seed['ip'],
                    'adapter_name' => 'Ethernet',
                    'gateway_ip' => '192.168.1.1',
                    'dns_servers' => ['1.1.1.1', '8.8.8.8'],
                    'status' => $seed['status'],
                    'health_score' => $seed['health'],
                    'registered_at' => now()->subDays(10 - $index),
                    'last_seen_at' => $seed['status'] === 'offline' ? now()->subMinutes(28) : now()->subMinutes(2 + $index),
                    'last_health_check_at' => $seed['status'] === 'offline' ? now()->subMinutes(28) : now()->subMinutes(5 + $index),
                ]
            );

            for ($hour = 11; $hour >= 0; $hour--) {
                $timestamp = Carbon::now()->subHours($hour)->minute(0)->second(0);
                $isRecentState = $hour <= 1;
                $health = $seed['health'];
                $status = $seed['status'];
                $gatewayReachable = true;
                $internetReachable = true;
                $dnsOperational = true;
                $severity = 'none';
                $diagnosisCode = 'healthy';
                $diagnosisTitle = 'Network Operational';
                $summary = 'All configured NetRescue checks passed.';
                $gateway = $seed['gateway'];
                $internet = $seed['internet'];
                $dns = $seed['dns'];

                if ($status === 'degraded' && $isRecentState) {
                    $severity = 'moderate';
                    $diagnosisCode = 'slow_network';
                    $diagnosisTitle = 'Slow Network Response';
                    $summary = $seed['summary'];
                } elseif ($status === 'critical' && $isRecentState) {
                    $severity = 'high';
                    $diagnosisCode = 'internet_unreachable';
                    $diagnosisTitle = 'Internet Connectivity Failure';
                    $summary = $seed['summary'];
                    $internetReachable = false;
                    $dnsOperational = false;
                } elseif ($status === 'offline' && $isRecentState) {
                    $severity = 'critical';
                    $diagnosisCode = 'device_unreachable';
                    $diagnosisTitle = 'Device Unreachable';
                    $summary = $seed['summary'];
                    $gatewayReachable = false;
                    $internetReachable = false;
                    $dnsOperational = false;
                } else {
                    $health = min(100, $health + rand(-4, 4));
                    if ($status !== 'offline') {
                        $gateway = $gateway !== null ? max(2, $gateway + rand(-20, 20) / 10) : null;
                        $internet = $internet !== null ? max(10, $internet + rand(-30, 30) / 10) : null;
                        $dns = $dns !== null ? max(8, $dns + rand(-25, 25) / 10) : null;
                    }
                }

                HealthCheck::create([
                    'device_id' => $device->id,
                    'checked_at' => $timestamp,
                    'source' => $device->is_local ? 'automatic' : 'observed',
                    'platform_supported' => true,
                    'interface_name' => 'Ethernet',
                    'local_ip' => $seed['ip'],
                    'gateway_ip' => '192.168.1.1',
                    'dns_servers' => ['1.1.1.1', '8.8.8.8'],
                    'gateway_reachable' => $gatewayReachable,
                    'gateway_latency_ms' => $gateway,
                    'internet_reachable' => $internetReachable,
                    'internet_latency_ms' => $internet,
                    'dns_operational' => $dnsOperational,
                    'dns_latency_ms' => $dns,
                    'dns_test_domain' => 'example.com',
                    'health_score' => max(0, min(100, (int) round($health))),
                    'health_status' => match ($status) {
                        'healthy' => 'healthy',
                        'degraded' => $isRecentState ? 'degraded' : 'healthy',
                        'critical' => $isRecentState ? 'critical' : 'healthy',
                        'offline' => $isRecentState ? 'critical' : 'healthy',
                        default => 'unknown',
                    },
                    'diagnosis_code' => $diagnosisCode,
                    'diagnosis_title' => $diagnosisTitle,
                    'severity' => $severity,
                    'diagnosis_summary' => $summary,
                    'diagnosis_details' => [],
                    'probe_meta' => ['seeded' => true],
                ]);
            }
        }

        // Central device history
        for ($hour = 11; $hour >= 0; $hour--) {
            $timestamp = Carbon::now()->subHours($hour)->minute(30)->second(0);
            HealthCheck::create([
                'device_id' => $central->id,
                'checked_at' => $timestamp,
                'source' => 'automatic',
                'platform_supported' => true,
                'interface_name' => 'Ethernet',
                'local_ip' => '192.168.1.2',
                'gateway_ip' => '192.168.1.1',
                'dns_servers' => ['1.1.1.1', '8.8.8.8'],
                'gateway_reachable' => true,
                'gateway_latency_ms' => max(2, 4.2 + rand(-10, 10) / 10),
                'internet_reachable' => true,
                'internet_latency_ms' => max(10, 20.1 + rand(-20, 20) / 10),
                'dns_operational' => true,
                'dns_latency_ms' => max(8, 17.8 + rand(-18, 18) / 10),
                'dns_test_domain' => 'example.com',
                'health_score' => 96,
                'health_status' => 'healthy',
                'diagnosis_code' => 'healthy',
                'diagnosis_title' => 'Network Operational',
                'severity' => 'none',
                'diagnosis_summary' => 'All configured NetRescue checks passed.',
                'diagnosis_details' => [],
                'probe_meta' => ['seeded' => true],
            ]);
        }

        $deviceLab07 = Device::query()->where('display_name', 'LAB-PC-07')->first();
        $deviceFaculty = Device::query()->where('display_name', 'FACULTY-PC-01')->first();
        $deviceLab12 = Device::query()->where('display_name', 'LAB-PC-12')->first();

        foreach ([
            [
                'device' => $deviceLab07,
                'code' => 'slow_network',
                'title' => 'Slow Network Response',
                'severity' => 'moderate',
                'summary' => 'Observed response time is above the configured warning threshold.',
                'recommended_action' => 'Inspect switch congestion, cable quality, or background traffic affecting this workstation.',
            ],
            [
                'device' => $deviceFaculty,
                'code' => 'internet_unreachable',
                'title' => 'Internet Connectivity Failure',
                'severity' => 'high',
                'summary' => 'The local network is reachable but Internet and DNS checks are currently failing.',
                'recommended_action' => 'Check router uplink, ISP condition, and outbound filtering between this device and the Internet.',
            ],
            [
                'device' => $deviceLab12,
                'code' => 'device_unreachable',
                'title' => 'Device Unreachable',
                'severity' => 'critical',
                'summary' => 'This computer has stopped responding within the recent monitoring window.',
                'recommended_action' => 'Inspect power, cabling, and switch port availability for this workstation.',
            ],
        ] as $incidentSeed) {
            if (! $incidentSeed['device']) {
                continue;
            }

            Incident::create([
                'device_id' => $incidentSeed['device']->id,
                'code' => $incidentSeed['code'],
                'title' => $incidentSeed['title'],
                'severity' => $incidentSeed['severity'],
                'status' => 'active',
                'first_detected_at' => now()->subHours(2),
                'last_detected_at' => now()->subMinutes(12),
                'resolved_at' => null,
                'occurrence_count' => 3,
                'latest_health_check_id' => HealthCheck::query()->where('device_id', $incidentSeed['device']->id)->latest('checked_at')->value('id'),
                'summary' => $incidentSeed['summary'],
                'recommended_action' => $incidentSeed['recommended_action'],
                'recovery_action_code' => null,
            ]);
        }
    }
}
