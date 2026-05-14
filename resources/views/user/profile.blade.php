@extends('layouts.app')
@section('title', '个人设置 - CANG-AI')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 flex flex-col sm:flex-row gap-6">
    @include('user._sidebar')
    <div class="flex-1 min-w-0 max-w-lg">
        <h1 class="text-2xl font-bold mb-6">个人设置</h1>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
        @endif

        {{-- 账号信息 --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-5">
            <h2 class="text-sm font-semibold text-gray-500 mb-3">账号信息</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">邮箱</span><span>{{ $user->email }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">注册时间</span><span>{{ $user->created_at->format('Y-m-d') }}</span></div>
            </div>
        </div>

        {{-- 修改资料 --}}
        <form method="POST" action="{{ route('user.profile.update') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">昵称</label>
                <input type="text" name="nickname" value="{{ old('nickname', $user->nickname ?? $user->name) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('nickname')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">新密码（留空则不修改）</label>
                <input type="password" name="password"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">确认新密码</label>
                <input type="password" name="password_confirmation"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
                保存
            </button>
        </form>
    </div>
</div>
@endsection
