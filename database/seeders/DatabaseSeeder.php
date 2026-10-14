<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\MonitorSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        MonitorSetting::current();
        Device::localHost();
    }
}
