<nav class="flex sm:flex-col gap-1 sm:w-44 shrink-0 mb-4 sm:mb-0">
    <a href="{{ route('user.dashboard') }}"
       class="px-4 py-2 rounded-lg text-sm transition {{ request()->routeIs('user.dashboard') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
        个人中心
    </a>
    <a href="{{ route('user.wallet') }}"
       class="px-4 py-2 rounded-lg text-sm transition {{ request()->routeIs('user.wallet') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
        钱包
    </a>
    <a href="{{ route('user.profile') }}"
       class="px-4 py-2 rounded-lg text-sm transition {{ request()->routeIs('user.profile') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
        个人设置
    </a>
</nav>
