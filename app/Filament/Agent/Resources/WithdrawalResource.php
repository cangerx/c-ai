<?php

namespace App\Filament\Agent\Resources;

use BackedEnum;
use App\Filament\Agent\Resources\WithdrawalResource\Pages;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WithdrawalResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = '提现审核';
    protected static ?string $modelLabel = '提现申请';
    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        try {
            $agentId = auth()->id();
            $count = WithdrawalRequest::where('agent_id', $agentId)->where('status', 'pending')->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('agent_id', auth()->id());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('用户')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('amount')->label('提现积分')->sortable()
                    ->weight('bold')->color('danger')->suffix(' 积分'),
                Tables\Columns\TextColumn::make('payment_method')->label('方式')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'alipay' => '支付宝',
                        'wechat' => '微信',
                        'bank' => '银行卡',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'alipay' => 'info',
                        'wechat' => 'success',
                        'bank' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_account')->label('收款账户')->limit(20),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => '待处理',
                        'paid' => '已打款',
                        'rejected' => '已拒绝',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('agent_note')->label('备注')->limit(20)->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('申请时间')->dateTime('m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('agent_processed_at')->label('处理时间')->dateTime('m-d H:i')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options(['pending' => '待处理', 'paid' => '已打款', 'rejected' => '已拒绝']),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->label('确认打款')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('确认已打款')
                    ->modalDescription(fn (WithdrawalRequest $record) => "确认已向 {$record->user->name} 打款 {$record->amount} 积分？")
                    ->visible(fn (WithdrawalRequest $record) => $record->status === 'pending')
                    ->action(fn (WithdrawalRequest $record) => $record->update([
                        'status' => 'paid',
                        'agent_processed_at' => now(),
                    ])),
                Actions\Action::make('reject')
                    ->label('拒绝')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('agent_note')->label('拒绝原因')->required()->rows(2),
                    ])
                    ->action(function (WithdrawalRequest $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'status' => 'rejected',
                                'agent_note' => $data['agent_note'],
                                'agent_processed_at' => now(),
                            ]);
                            User::where('id', $record->user_id)
                                ->increment('commission_credits', (int) $record->amount);
                        });
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
        ];
    }
}
