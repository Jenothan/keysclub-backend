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
            return response()->json(new \stdClass());
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
