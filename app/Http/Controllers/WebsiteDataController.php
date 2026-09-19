<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WebsiteData;

class WebsiteDataController extends Controller
{
    public function show()
    {
        $data = WebsiteData::first();
        
        if (!$data) {
            $data = WebsiteData::create([
                'primary_phone' => '+94 77 123 4567',
                'support_email' => 'info@keysclub.lk',
                'club_address' => 'Karanavai East, Karaveddy, Jaffna, Sri Lanka.',
                'facebook_url' => 'https://facebook.com',
                'instagram_url' => 'https://instagram.com',
                'court_pricing' => 'LKR 400',
                'membership_pricing' => 'LKR 1,000',
                'registration_fee' => 'LKR 2,000',
                'full_day_pricing' => 'LKR 3,000',
                'peak_start_time' => '15:00:00',
                'peak_end_time' => '20:00:00',
                'peak_off_days' => ['Saturday', 'Sunday'],
            ]);
        }

        return response()->json($data);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'primary_phone' => 'required|string',
            'support_email' => 'required|email',
            'club_address' => 'required|string',
            'facebook_url' => 'nullable|url',
            'instagram_url' => 'nullable|url',
            'court_pricing' => 'nullable|string',
            'membership_pricing' => 'nullable|string',
            'registration_fee' => 'nullable|string',
            'full_day_pricing' => 'nullable|string',
            'peak_start_time' => 'nullable|string',
            'peak_end_time' => 'nullable|string',
            'peak_off_days' => 'nullable|array',
        ]);

        $data = WebsiteData::first();

        if ($data) {
            $data->update($validated);
        } else {
            $data = WebsiteData::create($validated);
        }

        return response()->json([
            'message' => 'Website data updated successfully.',
            'data' => $data
        ]);
    }
}
