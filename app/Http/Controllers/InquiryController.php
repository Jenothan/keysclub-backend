<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inquiry;

class InquiryController extends Controller
{
    /**
     * Store a new inquiry (Public endpoint).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $inquiry = Inquiry::create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'Inquiry submitted successfully.',
            'inquiry' => $inquiry
        ], 201);
    }

    /**
     * Get all inquiries (Admin endpoint).
     */
    public function index()
    {
        $inquiries = Inquiry::orderBy('created_at', 'desc')->get();
        return response()->json($inquiries);
    }

    /**
     * Mark an inquiry as resolved (Admin endpoint).
     */
    public function resolve($id)
    {
        $inquiry = Inquiry::findOrFail($id);
        $inquiry->status = 'Resolved';
        $inquiry->save();

        return response()->json([
            'message' => 'Inquiry marked as resolved.',
            'inquiry' => $inquiry
        ]);
    }
}
