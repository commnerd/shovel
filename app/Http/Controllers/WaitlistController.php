<?php

namespace App\Http\Controllers;

use App\Models\WaitlistSubscriber;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:waitlist_subscribers,email'
        ]);

        WaitlistSubscriber::create($data);

        return back()->with('success', 'Thanks! We\'ll be in touch soon.');
    }
}
