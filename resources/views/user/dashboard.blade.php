@extends('layouts.app')
@section('title', '个人中心 - CANG-AI')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">个人中心</h1>

    {{-- 账户概览 --}}
    <div class="grid sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-sm text-gray-500 mb-1">余额</p>
            <p class="text-2xl font-bold text-blue-600">¥{{ number_format($user->balance, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-sm text-gray-500 mb-1">积分</p>
            <p class="text-2xl font-bold text-green-600">{{ $user->credits }}</p>
        </div>
        @if($user->isAgent())
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-sm text-gray-500 mb-1">佣金余额</p>
            <p class="text-2xl font-bold text-purple-600">¥{{ number_format($user->commission_balance, 2) }}</p>
        </div>
        @endif
    </div>

    {{-- 快捷操作 --}}
    <div class="flex flex-wrap gap-3 mb-8">
        <a href="{{ route('user.redeem') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">兑换充值</a>
        <a href="{{ route('user.usage') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">使用记录</a>
        <a href="{{ route('user.profile') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">修改资料</a>
    </div>

    {{-- 最近使用 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold">最近使用</h2>
        </div>
        @if($recentLogs->isEmpty())
            <p class="px-5 py-8 text-center text-gray-400 text-sm">暂无使用记录</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-2 text-left">时间</th>
                        <th class="px-5 py-2 text-left">模型</th>
                        <th class="px-5 py-2 text-left">消耗</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentLogs as $log)
                    <tr>
                        <td class="px-5 py-3">{{ $log->created_at->format('m-d H:i') }}</td>
                        <td class="px-5 py-3">{{ $log->model }}</td>
                        <td class="px-5 py-3">
                            @if($log->cost_credits > 0){{ $log->cost_credits }}积分@endif
                            @if($log->cost_balance > 0)¥{{ $log->cost_balance }}@endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
