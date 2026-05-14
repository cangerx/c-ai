@extends('layouts.app')
@section('title', '钱包 - CANG-AI')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 flex flex-col sm:flex-row gap-6">
    @include('user._sidebar')
    <div class="flex-1 min-w-0" x-data="{ tab: {{ Js::from(session('success') || session('error') ? 'redeem' : request('tab', 'overview')) }} }">
        <h1 class="text-2xl font-bold mb-6">钱包</h1>

        {{-- 余额卡片 --}}
        <div class="grid sm:grid-cols-3 gap-4 mb-6">
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

        {{-- Tabs --}}
        <div class="flex gap-1 border-b border-gray-200 mb-5">
            <button type="button" @click="tab = 'overview'"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition"
                    :class="tab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                使用记录
            </button>
            <button type="button" @click="tab = 'redeem'"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition"
                    :class="tab === 'redeem' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                兑换充值
            </button>
        </div>

        {{-- 使用记录 --}}
        <div x-show="tab === 'overview'">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                @if($logs->isEmpty())
                    <p class="px-5 py-8 text-center text-gray-400 text-sm">暂无使用记录</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-5 py-2 text-left">时间</th>
                                <th class="px-5 py-2 text-left">应用</th>
                                <th class="px-5 py-2 text-left">模型</th>
                                <th class="px-5 py-2 text-left">积分</th>
                                <th class="px-5 py-2 text-left">余额</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($logs as $log)
                            <tr>
                                <td class="px-5 py-3">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3">{{ $log->app_name }}</td>
                                <td class="px-5 py-3">{{ $log->model }}</td>
                                <td class="px-5 py-3">{{ $log->cost_credits }}</td>
                                <td class="px-5 py-3">¥{{ $log->cost_balance }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-5 py-4 border-t border-gray-100">
                        {{ $logs->appends(['tab' => 'overview'])->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- 兑换充值 --}}
        <div x-show="tab === 'redeem'" x-cloak>
            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">{{ session('error') }}</div>
            @endif
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <p class="text-sm text-gray-500 mb-4">输入 32 位兑换码，充值积分或余额到账户。</p>
                <form method="POST" action="{{ route('user.redeem.submit') }}">
                    @csrf
                    <div class="flex gap-3">
                        <input type="text" name="code" placeholder="输入 32 位兑换码" maxlength="32" required
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                        <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">兑换</button>
                    </div>
                    @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
