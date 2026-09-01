<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecurringBlockedSlot;
use App\Models\SlotOverride;
use Carbon\Carbon;

class RecurringBlockedSlotController extends Controller
{
    public function index()
    {
        return response()->json(RecurringBlockedSlot::where('is_active', true)->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'court_id' => 'nullable|exists:courts,id',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'reason' => 'nullable|string',
        ]);

        $courtId = $request->court_id ?? \App\Models\Court::first()?->id ?? 1;

        $slot = RecurringBlockedSlot::create([
            'court_id' => $courtId,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason ?? 'Daily Blocked Slot',
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Daily recurring blocked slot added successfully', 'slot' => $slot], 201);
    }

    public function destroy($id)
    {
        $slot = RecurringBlockedSlot::findOrFail($id);
        $slot->delete();

        return response()->json(['message' => 'Daily recurring blocked slot removed successfully']);
    }

    public function setOverride(Request $request)
    {
        $request->validate([
            'court_id' => 'nullable|exists:courts,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'status' => 'required|in:Available,Blocked',
            'notes' => 'nullable|string',
        ]);

        $courtId = $request->court_id ?? \App\Models\Court::first()?->id ?? 1;
        $date = Carbon::parse($request->date)->format('Y-m-d');

        $override = SlotOverride::updateOrCreate(
            [
                'court_id' => $courtId,
                'date' => $date,
                'start_time' => $request->start_time,
            ],
            [
                'end_time' => $request->end_time,
                'status' => $request->status,
                'notes' => $request->notes ?? 'Override by Admin',
            ]
        );

        return response()->json([
            'message' => "Slot marked as {$request->status} for {$date}",
            'override' => $override
        ]);
    }

    public function removeOverride(Request $request)
    {
        $request->validate([
            'court_id' => 'nullable|exists:courts,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
        ]);

        $courtId = $request->court_id ?? \App\Models\Court::first()?->id ?? 1;
        $date = Carbon::parse($request->date)->format('Y-m-d');

        SlotOverride::where('court_id', $courtId)
            ->where('date', $date)
            ->where('start_time', $request->start_time)
            ->delete();

        return response()->json(['message' => 'Slot override removed successfully']);
    }
}
