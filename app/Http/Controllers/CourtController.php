<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Court;

class CourtController extends Controller
{
    public function index()
    {
        return response()->json(Court::where('status', true)->get());
    }
}
