<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $transfers = DB::table('transactions')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return view('dashboard.index', compact('transfers'));
    }
    
    public function stats()
    {
        $stats = [
            'total' => DB::table('transactions')->count() * 5000,
            'pending' => DB::table('transactions')->where('status', 'pending')->count(),
            'completed' => DB::table('transactions')->where('status', 'completed')->count(),
        ];
        
        return response()->json($stats);
    }
}
