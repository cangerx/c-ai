<?php

namespace App\Filament\Agent\Pages;

use App\Models\AgentLevel;
use App\Models\AgentTransaction;
use App\Models\RedeemCode;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * @property-read Schema $form
 */
class Recharge extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = '充值兑换';
    protected static ?string $title = '充值兑换';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.agent.pages.recharge';

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                Section::make('当前账户')
                    ->description('你的积分与余额信息')
                    ->schema([
                        Forms\Components\Placeholder::make('credits_display')
                            ->label('积分余额')
                            ->content(number_format($user->credits)),
                        Forms\Components\Placeholder::make('balance_display')
                            ->label('现金余额')
                            ->content('¥' . number_format($user->balance, 2)),
                        Forms\Components\Placeholder::make('level_display')
                            ->label('当前等级')
                            ->content($user->agentLevel?->name ?? '无'),
                    ])->columns(3),

                Section::make('兑换充值')
                    ->description('输入从上级获取的兑换码为账户充值')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('兑换码')
                            ->placeholder('请输入兑换码')
                            ->helperText('兑换成功后积分将立即到账')
                            ->required()
                            ->maxLength(32)
                            ->autocomplete(false),
                        Forms\Components\Placeholder::make('purchase_link')
                            ->label('购买兑换码')
                            ->content(function () {
                                $url = SiteSetting::get('recharge_purchase_url');
                                if (!$url) {
                                    return '暂未设置购买地址，请联系管理员';
                                }
                                return new \Illuminate\Support\HtmlString(
                                    '<a href="' . e($url) . '" target="_blank" class="text-primary-600 hover:underline">' . e($url) . ' ↗</a>'
                                );
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    public function redeem(): void
    {
        $this->form->getState();
        $code = $this->data['code'];
        $user = auth()->user();

        $error = DB::transaction(function () use ($code, $user) {
            $redeemCode = RedeemCode::where('code', $code)
                ->where('status', 'unused')
                ->lockForUpdate()
                ->first();

            if (!$redeemCode) {
                return '兑换码无效或已使用';
            }
            if ($redeemCode->isExpired()) {
                return '兑换码已过期';
            }

            $redeemCode->update([
                'status' => 'used',
                'used_by' => $user->id,
                'used_at' => now(),
            ]);

            $user->increment('credits', $redeemCode->credits);
            $user->increment('balance', $redeemCode->balance);
            $user->increment('total_recharged', $redeemCode->credits * 0.1 + $redeemCode->balance);
            $user->refresh();

            AgentTransaction::create([
                'user_id' => $user->id,
                'type' => 'recharge',
                'credits' => $redeemCode->credits,
                'balance' => $redeemCode->balance,
                'credits_after' => $user->credits,
                'balance_after' => $user->balance,
                'description' => "兑换码充值 +{$redeemCode->credits}积分 +¥{$redeemCode->balance}",
            ]);

            $nextLevel = AgentLevel::where('min_recharge', '<=', $user->total_recharged)
                ->orderBy('min_recharge', 'desc')
                ->first();
            if ($nextLevel && $user->agent_level_id !== $nextLevel->id) {
                $user->update(['agent_level_id' => $nextLevel->id]);
            }

            return null;
        });

        if ($error) {
            Notification::make()->title($error)->danger()->send();
            return;
        }

        $this->data = [];
        Notification::make()->title('充值成功！')->success()->send();
    }
}
