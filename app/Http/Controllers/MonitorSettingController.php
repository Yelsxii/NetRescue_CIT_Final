<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMonitorSettingRequest;
use App\Models\MonitorSetting;
use Illuminate\Http\RedirectResponse;

class MonitorSettingController extends Controller
{
    public function update(UpdateMonitorSettingRequest $request): RedirectResponse
    {
        $settings = MonitorSetting::current();
        $settings->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Monitoring settings updated',
            'message' => $settings->enabled
                ? "Automatic monitoring is enabled every {$settings->interval_minutes} minute(s)."
                : 'Automatic monitoring is currently disabled.',
        ]);
    }
}
