<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // 代理商只能看自己的子用户
        if (auth()->user()->isAgent()) {
            $query->where('parent_id', auth()->id());
        }

        if ($search = $request->get('q')) {
            $s = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($s) {
                $q->where('email', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%")
                  ->orWhere('nickname', 'like', "%{$s}%");
            });
        }
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $users = $query->latest()->paginate(20)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $isAgent = auth()->user()->isAgent();

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'nickname' => 'nullable|string|max:50',
            'role' => 'required|in:admin,agent,user',
            'credits' => 'nullable|integer|min:0',
            'balance' => 'nullable|numeric|min:0',
        ]);

        // 代理商只能创建普通用户，且自动绑定 parent_id
        if ($isAgent) {
            $data['role'] = 'user';
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'nickname' => $data['nickname'] ?? null,
            'parent_id' => $isAgent ? auth()->id() : null,
        ]);
        $user->role = $data['role'];
        $user->credits = $data['credits'] ?? 0;
        $user->balance = $data['balance'] ?? 0;
        $user->status = 'active';
        $user->save();

        return redirect()->route('admin.users.index')->with('success', '用户已创建');
    }

    public function edit(User $user)
    {
        // 代理商只能编辑自己的子用户
        if (auth()->user()->isAgent() && $user->parent_id !== auth()->id()) {
            abort(403);
        }

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $isAgent = auth()->user()->isAgent();

        // 代理商只能编辑自己的子用户
        if ($isAgent && $user->parent_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'nickname' => 'nullable|string|max:50',
            'role' => 'required|in:admin,agent,user',
            'credits' => 'required|integer|min:0',
            'balance' => 'required|numeric|min:0',
            'password' => 'nullable|string|min:6',
            'is_distributor' => 'nullable',
        ]);

        // 代理商不能修改角色
        if ($isAgent) {
            $data['role'] = 'user';
        }

        $user->name = $data['name'];
        $user->nickname = $data['nickname'];
        $user->role = $data['role'];
        $user->credits = $data['credits'];
        $user->balance = $data['balance'];
        $user->save();

        if (!$isAgent) {
            $becomingDistributor = (bool) ($data['is_distributor'] ?? false);
            if ($becomingDistributor && !$user->is_distributor) {
                $user->is_distributor = true;
                $user->ensureInviteCode();
                $user->save();
            } elseif (!$becomingDistributor && $user->is_distributor) {
                $user->is_distributor = false;
                $user->save();
            }
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->save();
        }

        return redirect()->route('admin.users.index')->with('success', '用户已更新');
    }

    public function toggleStatus(User $user)
    {
        if (auth()->user()->isAgent() && $user->parent_id !== auth()->id()) {
            abort(403);
        }

        $user->status = $user->status === 'active' ? 'disabled' : 'active';
        $user->save();

        return back()->with('success', $user->status === 'active' ? '用户已启用' : '用户已禁用');
    }
}
