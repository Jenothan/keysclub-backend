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
     * Get all inquiries with search and filters (Admin endpoint).
     */
    public function index(Request $request)
    {
        $query = Inquiry::query();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subject') && $request->subject !== 'All Subjects') {
            $query->where('subject', $request->subject);
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $inquiries = $query->orderBy('created_at', 'desc')->get();
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
