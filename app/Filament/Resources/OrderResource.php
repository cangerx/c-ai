<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use App\Services\Payment\PaymentManager;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = '充值订单';
    protected static ?string $modelLabel = '订单';
    protected static string | UnitEnum | null $navigationGroup = '业务配置';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('订单信息')->schema([
                Forms\Components\TextInput::make('order_no')->label('订单号')->disabled(),
                Forms\Components\TextInput::make('user.name')->label('用户')->disabled(),
                Forms\Components\TextInput::make('amount')->label('金额')->prefix('¥')->disabled(),
                Forms\Components\TextInput::make('credits')->label('积分')->disabled(),
                Forms\Components\TextInput::make('subject')->label('描述')->disabled(),
                Forms\Components\TextInput::make('status')->label('状态')->disabled(),
                Forms\Components\TextInput::make('pay_method')->label('支付方式')->disabled(),
                Forms\Components\TextInput::make('provider_order_no')->label('天雀订单号 uuid')->disabled(),
                Forms\Components\TextInput::make('provider_trade_no')->label('天雀流水号')->disabled(),
                Forms\Components\Textarea::make('qr_code')->label('二维码内容')->rows(3)->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_no')->label('订单号')
                    ->fontFamily('mono')->size('sm')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('用户')
                    ->searchable()
                    ->description(fn (Order $r) => $r->user?->email),
                Tables\Columns\TextColumn::make('amount')->label('金额')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('credits')->label('积分')->numeric()->suffix(' 积分'),
                Tables\Columns\TextColumn::make('pay_method')->label('支付')
                    ->formatStateUsing(fn ($s) => match ($s) {
                        'WECHAT' => '💚 微信',
                        'ALIPAY' => '💙 支付宝',
                        'UNIONPAY' => '🏦 银联',
                        default => $s ?: '—',
                    })->badge(),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge()
                    ->formatStateUsing(fn ($s) => [
                        'pending' => '待支付',
                        'paid' => '已支付',
                        'failed' => '失败',
                        'cancelled' => '已取消',
                        'refunded' => '已退款',
                    ][$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('credits_granted')->label('已发')->boolean(),
                Tables\Columns\TextColumn::make('paid_at')->label('支付时间')->dateTime('Y-m-d H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('创建')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')->options([
                    'pending' => '待支付',
                    'paid' => '已支付',
                    'failed' => '失败',
                    'cancelled' => '已取消',
                    'refunded' => '已退款',
                ]),
                Tables\Filters\SelectFilter::make('pay_method')->label('支付方式')->options([
                    'WECHAT' => '微信',
                    'ALIPAY' => '支付宝',
                    'UNIONPAY' => '银联',
                ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('markPaid')
                    ->label('手动入账')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $r) => $r->status !== 'paid')
                    ->requiresConfirmation()
                    ->modalDescription('强制将订单标记为已支付并发放积分（用于补单）。请谨慎使用。')
                    ->action(function (Order $record) {
                        DB::transaction(function () use ($record) {
                            $r = Order::lockForUpdate()->find($record->id);
                            if (!$r || $r->credits_granted) return;
                            $r->update([
                                'status' => 'paid',
                                'paid_at' => now(),
                            ]);
                            $u = User::lockForUpdate()->find($r->user_id);
                            if ($u) {
                                $u->credits = (int) $u->credits + (int) $r->credits;
                                if ((float) $r->bonus_balance > 0) {
                                    $u->balance = (float) $u->balance + (float) $r->bonus_balance;
                                }
                                $u->total_recharged = (float) $u->total_recharged + (float) $r->amount;
                                $u->save();
                            }
                            $r->update(['credits_granted' => true]);
                            PaymentTransaction::create([
                                'order_id' => $r->id,
                                'type' => 'manual',
                                'provider' => $r->pay_provider,
                                'result' => 'success',
                                'request' => ['operator' => auth()->user()->email ?? null],
                            ]);
                        });
                        Notification::make()->title('订单已手动入账')->success()->send();
                    }),
                Actions\Action::make('refund')
                    ->label('退款')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Order $r) => $r->status === 'paid')
                    ->requiresConfirmation()
                    ->modalHeading('确认退款')
                    ->modalDescription(fn (Order $r) => "将向天阙发起退款 ¥{$r->amount}，并扣回已发放的积分和余额。此操作不可撤销。")
                    ->action(function (Order $record) {
                        try {
                            $refundNo = 'RF' . date('YmdHis') . strtoupper(Str::random(6));
                            $provider = app(PaymentManager::class)->driver();
                            $result = $provider->refundOrder($record, $refundNo);

                            DB::transaction(function () use ($record, $refundNo, $result) {
                                $r = Order::lockForUpdate()->find($record->id);
                                if (!$r || $r->status === 'refunded') return;

                                $r->update(['status' => 'refunded']);

                                if ($r->credits_granted) {
                                    $u = User::lockForUpdate()->find($r->user_id);
                                    if ($u) {
                                        $u->credits = max(0, (int) $u->credits - (int) $r->credits);
                                        if ((float) $r->bonus_balance > 0) {
                                            $u->balance = max(0, (float) $u->balance - (float) $r->bonus_balance);
                                        }
                                        $u->total_recharged = max(0, (float) $u->total_recharged - (float) $r->amount);
                                        $u->save();
                                    }
                                }

                                PaymentTransaction::create([
                                    'order_id' => $r->id,
                                    'type' => 'refund',
                                    'provider' => $r->pay_provider,
                                    'result' => 'success',
                                    'provider_trade_no' => $result['provider_trade_no'] ?? null,
                                    'response' => $result['raw'] ?? null,
                                    'request' => ['refund_no' => $refundNo, 'operator' => auth()->user()->email ?? null],
                                ]);
                            });

                            Notification::make()->title('退款成功')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('退款失败')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
