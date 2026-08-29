<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BlockedDate;

class AdminBlockedDateController extends Controller
{
    public function index()
    {
        return response()->json(BlockedDate::orderBy('date')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'dates' => 'required|array',
            'dates.*' => 'required|date|after_or_equal:today',
            'reason' => 'nullable|string'
        ]);

        $blockedDates = [];
        foreach ($request->dates as $dateStr) {
            $date = \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
            $blockedDates[] = BlockedDate::updateOrCreate(
                ['date' => $date],
                ['reason' => $request->reason]
            );
        }

        return response()->json(['message' => 'Dates blocked successfully', 'blocked_dates' => $blockedDates], 201);
    }

    public function destroy($date)
    {
        $blockedDate = BlockedDate::where('date', $date)->firstOrFail();
        $blockedDate->delete();

        return response()->json(['message' => 'Block removed successfully']);
    }
}
