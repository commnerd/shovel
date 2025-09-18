<?php

namespace App\Http\Controllers;

use App\Models\WaitlistSubscriber;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index()
    {
        $waitlistCount = WaitlistSubscriber::count();

        return Inertia::render('Dashboard', [
            'waitlistCount' => $waitlistCount,
        ]);
    }
}
