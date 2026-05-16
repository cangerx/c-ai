<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentTransaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentTransaction::where('user_id', auth()->id());

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $transactions = $query->latest()->paginate(50);
        return view('agent.transactions', compact('transactions'));
    }
}
