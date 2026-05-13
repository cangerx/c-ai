@extends('admin.layouts.app')

@section('title', '提现记录')

@section('header')
    <h1 class="page-title">提现记录</h1>
    @if(auth()->user()->isAgent())
        <p class="page-subtitle">当前可提现余额：¥{{ number_format(auth()->user()->commission_balance, 2) }}</p>
    @endif
@endsection

@section('content')
    @if(auth()->user()->isAgent())
    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.withdrawals.create') }}" class="btn btn-primary btn-sm">申请提现</a>
    </div>
    @endif

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @if(auth()->user()->isAdmin())<th>代理商</th>@endif
                            <th>金额</th>
                            <th>方式</th>
                            <th>账号</th>
                            <th>状态</th>
                            <th>申请时间</th>
                            @if(auth()->user()->isAdmin())<th>操作</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr>
                            @if(auth()->user()->isAdmin())<td>{{ $req->user->name ?? '-' }}</td>@endif
                            <td>¥{{ number_format($req->amount, 2) }}</td>
                            <td>{{ ['alipay'=>'支付宝','wechat'=>'微信','bank'=>'银行卡'][$req->payment_method] ?? $req->payment_method }}</td>
                            <td>{{ $req->payment_account }}</td>
                            <td>
                                @if($req->status === 'pending')
                                    <span class="badge badge-warning">待处理</span>
                                @elseif($req->status === 'paid')
                                    <span class="badge badge-success">已打款</span>
                                @elseif($req->status === 'approved')
                                    <span class="badge badge-info">已批准</span>
                                @else
                                    <span class="badge badge-danger">已拒绝</span>
                                @endif
                            </td>
                            <td>{{ $req->created_at->format('Y-m-d H:i') }}</td>
                            @if(auth()->user()->isAdmin())
                            <td>
                                @if($req->status === 'pending')
                                <form method="POST" action="{{ route('admin.withdrawals.approve', $req) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('确认批准？')">批准</button>
                                </form>
                                <form method="POST" action="{{ route('admin.withdrawals.reject', $req) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('确认拒绝？')">拒绝</button>
                                </form>
                                @else
                                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 7 : 5 }}">
                                <div class="empty-state">
                                    <div class="empty-state-icon">💸</div>
                                    <div class="empty-state-text">暂无提现记录</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div style="margin-top: 16px;">{{ $requests->links() }}</div>
@endsection
