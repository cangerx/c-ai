@extends('admin.layouts.app')

@section('title', '广告横幅')

@section('header')
    <h1 class="page-title">广告横幅</h1>
    <p class="page-subtitle">管理首页轮播广告，支持多条滚动展示</p>
@endsection

@section('content')
    {{-- 添加新广告 --}}
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header"><span class="card-title">添加广告</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.announcements.store') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                @csrf
                <div style="flex:2; min-width:200px;">
                    <label class="form-label">广告文案</label>
                    <input class="form-input" name="content" placeholder="例：新功能上线，体验 AI 高清绘画" required>
                </div>
                <div style="flex:1; min-width:160px;">
                    <label class="form-label">链接地址（可选）</label>
                    <input class="form-input" name="url" placeholder="https://...">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">添加</button>
            </form>
        </div>
    </div>

    {{-- 广告列表 --}}
    <div class="card">
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;">排序</th>
                            <th>广告内容</th>
                            <th>链接</th>
                            <th style="width:70px;">状态</th>
                            <th style="width:140px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($announcements as $ann)
                        <tr style="{{ !$ann->enabled ? 'opacity:0.5;' : '' }}">
                            <td>
                                <form method="POST" action="{{ route('admin.announcements.update', $ann) }}" style="display:inline;">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="content" value="{{ $ann->content }}">
                                    <input type="hidden" name="url" value="{{ $ann->url }}">
                                    <input type="hidden" name="enabled" value="{{ $ann->enabled }}">
                                    <input class="form-input" name="sort" value="{{ $ann->sort }}" style="width:50px; padding:4px 8px; font-size:12px; text-align:center;" onchange="this.form.submit()">
                                </form>
                            </td>
                            <td style="font-size:13px;">{{ $ann->content }}</td>
                            <td style="font-size:12px; color:var(--text-secondary);">
                                @if($ann->url)
                                    <a href="{{ $ann->url }}" target="_blank" style="color:var(--accent);">{{ Str::limit($ann->url, 30) }}</a>
                                @else
                                    <span style="color:#ccc;">—</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.announcements.toggle', $ann) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" data-no-loading style="font-size:12px;">
                                        @if($ann->enabled)
                                            <span class="badge badge-success">启用</span>
                                        @else
                                            <span class="badge badge-danger">禁用</span>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $ann) }}" style="display:inline;"
                                          x-data @submit.prevent="$dispatch('confirm', { title: '删除广告', message: '确定删除此广告？', form: $el })">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" data-no-loading style="color:var(--danger);">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-secondary);">暂无广告，添加后将在首页轮播展示</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
