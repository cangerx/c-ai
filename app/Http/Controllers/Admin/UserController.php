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
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%");
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

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'nickname' => $data['nickname'] ?? null,
            'role' => $data['role'],
            'credits' => $data['credits'] ?? 0,
            'balance' => $data['balance'] ?? 0,
            'status' => 'active',
            'parent_id' => $isAgent ? auth()->id() : null,
        ]);

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
        ]);

        // 代理商不能修改角色
        if ($isAgent) {
            $data['role'] = 'user';
        }

        $user->update([
            'name' => $data['name'],
            'nickname' => $data['nickname'],
            'role' => $data['role'],
            'credits' => $data['credits'],
            'balance' => $data['balance'],
        ]);

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()->route('admin.users.index')->with('success', '用户已更新');
    }

    public function toggleStatus(User $user)
    {
        if (auth()->user()->isAgent() && $user->parent_id !== auth()->id()) {
            abort(403);
        }

        $user->update([
            'status' => $user->status === 'active' ? 'disabled' : 'active',
        ]);

        return back()->with('success', $user->status === 'active' ? '用户已启用' : '用户已禁用');
    }
}
