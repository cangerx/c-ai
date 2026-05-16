<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $rate = (float) SiteSetting::get('distributor_commission_rate', 0.10);

        $distributors = User::where('is_distributor', true)
            ->withCount('children')
            ->orderByDesc('commission_credits')
            ->get();

        $query = CommissionLog::with('user:id,nickname,name,email', 'fromUser:id,nickname,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.commissions.index', compact('distributors', 'logs', 'rate'));
    }
}
