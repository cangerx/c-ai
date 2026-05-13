@extends('agent.layout')

@section('title', '子用户管理')

@section('content')
    <h1 class="page-title">子用户管理</h1>
    <p class="page-subtitle">查看和管理通过你的邀请码注册的用户</p>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table>
                <thead>
                    <tr>
                        <th>用户</th>
                        <th>邮箱</th>
                        <th>剩余次数</th>
                        <th>剩余余额</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th>充值</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->credits }}</td>
                        <td>¥{{ number_format($u->balance, 2) }}</td>
                        <td>
                            @if($u->status === 'active')
                                <span class="badge badge-success">正常</span>
                            @else
                                <span class="badge badge-danger">禁用</span>
                            @endif
                        </td>
                        <td>{{ $u->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('agent.sub-users.recharge', $u) }}" style="display:flex; gap:6px; align-items:center;">
                                @csrf
                                <input class="form-input" type="number" name="credits" placeholder="次数" min="0" style="width:70px;">
                                <input class="form-input" type="number" name="balance" placeholder="余额" min="0" step="0.01" style="width:80px;">
                                <button type="submit" class="btn btn-primary btn-sm">充值</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">暂无子用户，分享你的邀请码吸引用户注册</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $users->links() }}
@endsection
