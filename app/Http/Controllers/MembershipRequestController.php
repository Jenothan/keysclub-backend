<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MembershipRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

use App\Services\CloudinaryService;
use App\Services\SmsService;

class MembershipRequestController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Get current user's membership status & latest request.
     */
    public function myStatus(Request $request)
    {
        $user = $request->user();
        $latestRequest = MembershipRequest::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'is_member' => (bool) $user->is_member,
            'latest_request' => $latestRequest,
        ]);
    }

    /**
     * User submits a membership request.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->is_member) {
            return response()->json(['message' => 'You are already a registered Court Member!'], 400);
        }

        // Check if there is already a pending request
        $existingPending = MembershipRequest::where('user_id', $user->id)
            ->where('status', 'Pending')
            ->first();

        if ($existingPending) {
            return response()->json(['message' => 'You already have a pending membership request under review.'], 400);
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $this->cloudinaryService->upload($request->file('payment_proof'), 'membership_proofs');
        }

        $membershipRequest = MembershipRequest::create([
            'user_id' => $user->id,
            'payment_proof_path' => $proofPath,
            'notes' => $request->notes,
            'status' => 'Pending',
        ]);

        // Send SMS to user acknowledging receipt of request
        if ($user->phone) {
            $smsMsg = "Hello {$user->name}, we received your KEYS Club court membership request. We will review it shortly and update you. Thank you!";
            SmsService::sendSms($user->phone, $smsMsg);
        }

        return response()->json([
            'message' => 'Membership request submitted successfully! Admin will review your application.',
            'data' => $membershipRequest,
        ], 201);
    }

    /**
     * Admin: List all membership requests.
     */
    public function index(Request $request)
    {
        $query = MembershipRequest::with(['user', 'reviewer']);

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json($requests);
    }

    /**
     * Admin: Approve a membership request.
     */
    public function approve(Request $request, $id)
    {
        $membershipRequest = MembershipRequest::with('user')->findOrFail($id);

        if ($membershipRequest->status === 'Approved') {
            return response()->json(['message' => 'This request has already been approved.'], 400);
        }

        $membershipRequest->update([
            'status' => 'Approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Promote user to Member
        if ($membershipRequest->user) {
            $membershipRequest->user->update([
                'is_member' => true,
            ]);

            // Send SMS notification on approval
            if ($membershipRequest->user->phone) {
                $userName = $membershipRequest->user->name;
                $smsMsg = "Congratulations {$userName}! Your KEYS Club court membership request has been approved. You can now book Peak Hour slots!";
                SmsService::sendSms($membershipRequest->user->phone, $smsMsg);
            }
        }

        return response()->json([
            'message' => "Membership approved successfully for {$membershipRequest->user->name}!",
            'data' => $membershipRequest,
        ]);
    }

    /**
     * Admin: Reject a membership request.
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $membershipRequest = MembershipRequest::with('user')->findOrFail($id);

        $reason = $request->rejection_reason ?? 'Request did not meet requirements.';

        $membershipRequest->update([
            'status' => 'Rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Send SMS notification on rejection
        if ($membershipRequest->user && $membershipRequest->user->phone) {
            $userName = $membershipRequest->user->name;
            $smsMsg = "Hello {$userName}, your KEYS Club court membership request was not approved. Reason: {$reason}";
            SmsService::sendSms($membershipRequest->user->phone, $smsMsg);
        }

        return response()->json([
            'message' => 'Membership request rejected.',
            'data' => $membershipRequest,
        ]);
    }

    /**
     * Serve or stream payment proof file (PDF or Image).
     */
    public function serveProof($id)
    {
        $membershipRequest = MembershipRequest::findOrFail($id);

        if (!$membershipRequest->payment_proof_path) {
            return response()->json(['message' => 'No payment proof attached to this request.'], 404);
        }

        // If stored as Cloudinary HTTPS URL, redirect directly
        if (str_starts_with($membershipRequest->payment_proof_path, 'http')) {
            return redirect()->away($membershipRequest->payment_proof_path);
        }

        if (!Storage::disk('public')->exists($membershipRequest->payment_proof_path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        $path = Storage::disk('public')->path($membershipRequest->payment_proof_path);
        $mimeType = mime_content_type($path);

        return Response::file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
