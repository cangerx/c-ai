@extends('layouts.app')
@section('title', '使用记录 - CANG-AI')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">使用记录</h1>

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
                        <th class="px-5 py-2 text-left">消耗积分</th>
                        <th class="px-5 py-2 text-left">消耗余额</th>
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
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
