<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CANG-AI 绘图')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    {{-- 导航栏 --}}
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50" x-data="{ open: false }">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex justify-between h-16 items-center">
                {{-- Logo + 链接 --}}
                <div class="flex items-center gap-6">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-blue-600">CANG-AI</a>
                    <a href="{{ url('/') }}" class="text-sm text-gray-600 hover:text-gray-900">主页</a>
                    <a href="{{ route('explore') }}" class="text-sm text-gray-600 hover:text-gray-900">探索画廊</a>
                </div>

                {{-- 用户菜单 --}}
                <div class="flex items-center gap-3">
                    @guest
                        <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">登录</a>
                        <a href="{{ route('register') }}" class="text-sm px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">注册</a>
                    @endguest
                    @auth
                        {{-- 通知铃铛 --}}
                        <div class="relative" x-data="{ showNotif: false, unread: 0 }" x-init="
                            fetch('/api/notifications/unread-count', {headers:{'Authorization':'Bearer '+document.cookie.match(/token=([^;]+)/)?.[1]||''}})
                                .then(r=>r.json()).then(d=>unread=d.count||0).catch(()=>{});
                            setInterval(()=>{
                                fetch('/api/notifications/unread-count', {headers:{'Authorization':'Bearer '+document.cookie.match(/token=([^;]+)/)?.[1]||''}})
                                    .then(r=>r.json()).then(d=>unread=d.count||0).catch(()=>{});
                            }, 30000)
                        ">
                            <button @click="showNotif = !showNotif" class="relative text-gray-600 hover:text-gray-900">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span x-show="unread>0" x-text="unread" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center"></span>
                            </button>
                        </div>
                        <div class="relative" x-data="{ dropdown: false }">
                            <button @click="dropdown = !dropdown" class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900">
                                <span>{{ Auth::user()->nickname ?? Auth::user()->name }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="dropdown" @click.away="dropdown = false" x-transition
                                 class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg py-1">
                                <a href="/?profile=1" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">个人中心</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">退出</button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    {{-- 主内容 --}}
    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
