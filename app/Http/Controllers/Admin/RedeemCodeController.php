<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedeemCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RedeemCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = RedeemCode::with('creator', 'user');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($batch = $request->get('batch_id')) {
            $query->where('batch_id', $batch);
        }

        $codes = $query->latest()->paginate(20)->withQueryString();
        return view('admin.redeem-codes.index', compact('codes'));
    }

    public function showGenerate()
    {
        return view('admin.redeem-codes.generate');
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'count' => 'required|integer|min:1|max:500',
            'type' => 'required|in:credits,balance,mixed',
            'credits' => 'required_if:type,credits,mixed|integer|min:0',
            'balance' => 'required_if:type,balance,mixed|numeric|min:0',
            'expires_days' => 'nullable|integer|min:1',
        ]);

        $batchId = 'B' . now()->format('ymdHis') . Str::random(4);
        $expiresAt = !empty($data['expires_days']) ? now()->addDays($data['expires_days']) : null;
        $codes = [];

        for ($i = 0; $i < $data['count']; $i++) {
            $codes[] = RedeemCode::create([
                'code' => strtoupper(Str::random(32)),
                'type' => $data['type'],
                'credits' => $data['credits'] ?? 0,
                'balance' => $data['balance'] ?? 0,
                'status' => 'unused',
                'created_by' => auth()->id(),
                'expires_at' => $expiresAt,
                'batch_id' => $batchId,
            ]);
        }

        return redirect()->route('admin.redeem-codes.index', ['batch_id' => $batchId])
            ->with('success', "已生成 {$data['count']} 个兑换码，批次号: {$batchId}");
    }

    public function disable(RedeemCode $redeemCode)
    {
        if ($redeemCode->status === 'unused') {
            $redeemCode->update(['status' => 'disabled']);
            return back()->with('success', '兑换码已作废');
        }
        return back()->with('error', '只能作废未使用的兑换码');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = RedeemCode::query();
        if ($batch = $request->get('batch_id')) {
            $query->where('batch_id', $batch);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $filename = 'redeem-codes-' . now()->format('YmdHis') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['兑换码', '类型', '次数', '额度', '状态', '批次号', '过期时间', '创建时间']);
            $query->orderBy('id')->chunk(200, function ($codes) use ($out) {
                foreach ($codes as $code) {
                    fputcsv($out, [
                        $code->code,
                        $code->type,
                        $code->credits,
                        $code->balance,
                        $code->status,
                        $code->batch_id,
                        $code->expires_at?->toDateTimeString() ?? '',
                        $code->created_at->toDateTimeString(),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
