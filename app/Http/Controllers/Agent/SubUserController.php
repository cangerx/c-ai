<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SubUserController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user();
        $users = $agent->children()->latest()->paginate(20);
        return view('agent.sub-users', compact('users'));
    }

    public function recharge(Request $request, User $user)
    {
        $agent = $request->user();

        if ($user->parent_id !== $agent->id) {
            abort(403, '无权操作');
        }

        $data = $request->validate([
            'credits' => 'nullable|integer|min:0|max:9999',
            'balance' => 'nullable|numeric|min:0|max:99999',
        ]);

        if (($data['credits'] ?? 0) > 0) {
            $user->increment('credits', $data['credits']);
        }
        if (($data['balance'] ?? 0) > 0) {
            $user->increment('balance', $data['balance']);
        }

        return back()->with('success', "已为 {$user->name} 充值");
    }
}
