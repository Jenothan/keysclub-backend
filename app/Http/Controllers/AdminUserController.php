<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminUserController extends Controller
{
    /**
     * Display a listing of regular users.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Always filter for regular users (excluding Admin & Super Admin)
        $query->where(function ($q) {
            $q->whereNotIn('role', ['Admin', 'Super Admin'])
              ->orWhereNull('role');
        });

        // Search by name, email, or phone
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status (Active / Inactive)
        if ($request->filled('status') && $request->status !== 'All Statuses') {
            if ($request->status === 'Active') {
                $query->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                });
            } elseif ($request->status === 'Inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json($users);
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        if (in_array($user->role, ['Admin', 'Super Admin'])) {
            return response()->json(['message' => 'Cannot modify status of Admin users'], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => "User status updated to " . ($user->is_active ? 'Active' : 'Inactive'),
            'user' => $user
        ]);
    }
}
