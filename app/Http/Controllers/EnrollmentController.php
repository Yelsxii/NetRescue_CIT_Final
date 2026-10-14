<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentCode;
use Illuminate\Http\RedirectResponse;

class EnrollmentController extends Controller
{
    public function rotate(): RedirectResponse
    {
        $code = EnrollmentCode::issue();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'New deployment package ready',
            'message' => 'Older one-click installers are now invalid. Download a fresh installer; its authorization is valid for 24 hours.',
        ]);
    }
}
