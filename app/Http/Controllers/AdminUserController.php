<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminUserController extends Controller
{
    /**
     * Display a listing of regular users.
     */
    public function index()
    {
        $users = User::where('role', 'User')
            ->orWhereNull('role')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($users);
    }
}
