@extends('agent.layout')

@section('title', '兑换码管理')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 class="page-title">兑换码管理</h1>
            <p class="page-subtitle" style="margin:0;">管理已生成的兑换码</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('agent.redeem-codes.export') }}" class="btn btn-ghost">导出CSV</a>
            <a href="{{ route('agent.redeem-codes.generate') }}" class="btn btn-primary">生成兑换码</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr>
                        <th>兑换码</th>
                        <th>类型</th>
                        <th>积分</th>
                        <th>余额</th>
                        <th>状态</th>
                        <th>使用者</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($codes as $code)
                    <tr>
                        <td><code style="font-size:11px;">{{ $code->code }}</code></td>
                        <td>
                            @if($code->type === 'credits') <span class="badge badge-info">积分</span>
                            @elseif($code->type === 'balance') <span class="badge badge-warning">余额</span>
                            @else <span class="badge">混合</span>
                            @endif
                        </td>
                        <td>{{ $code->credits }}</td>
                        <td>¥{{ number_format($code->balance, 2) }}</td>
                        <td>
                            @if($code->status === 'unused') <span class="badge badge-success">未使用</span>
                            @elseif($code->status === 'used') <span class="badge badge-info">已使用</span>
                            @else <span class="badge badge-danger">已禁用</span>
                            @endif
                        </td>
                        <td>{{ $code->user->name ?? '—' }}</td>
                        <td>{{ $code->created_at->format('m-d H:i') }}</td>
                        <td>
                            @if($code->status === 'unused')
                            <form method="POST" action="{{ route('agent.redeem-codes.disable', $code) }}" style="display:inline;"
                                  x-data @submit.prevent="$dispatch('confirm', { title:'禁用兑换码', message:'确定禁用此兑换码？', form: $el })">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-danger">禁用</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-text">暂无兑换码</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $codes->links() }}
@endsection
