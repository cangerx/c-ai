<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::orderBy('sort')->orderByDesc('id')->get();
        return view('admin.announcements.index', compact('announcements'));
    }

    public function store(Request $request)
    {
        $request->validate(['content' => 'required|string|max:255']);
        Announcement::create([
            'content' => $request->input('content'),
            'url' => $request->input('url'),
            'enabled' => true,
            'sort' => 0,
        ]);
        return back()->with('success', '添加成功');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate(['content' => 'required|string|max:255']);
        $announcement->update($request->only('content', 'url', 'enabled', 'sort'));
        return back()->with('success', '已更新');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success', '已删除');
    }

    public function toggle(Announcement $announcement)
    {
        $announcement->update(['enabled' => !$announcement->enabled]);
        return back();
    }
}
