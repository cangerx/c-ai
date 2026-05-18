<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Models\WithdrawalRequest;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = '提现监控';
    protected static ?string $modelLabel = '提现';
    protected static string | UnitEnum | null $navigationGroup = '代理商';
    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = WithdrawalRequest::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
                Tables\Columns\TextColumn::make('agent.name')->label('归属代理')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')->label('申请用户')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('amount')->label('积分')->sortable()
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
                Tables\Columns\TextColumn::make('payment_account')->label('账户')->limit(20)->color('gray'),
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
                Tables\Columns\TextColumn::make('agent_note')->label('代理备注')->limit(20)->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('申请时间')->dateTime('m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('agent_processed_at')->label('处理时间')->dateTime('m-d H:i')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options(['pending' => '待处理', 'paid' => '已打款', 'rejected' => '已拒绝']),
                Tables\Filters\SelectFilter::make('agent_id')->label('代理')
                    ->relationship('agent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawalRequests::route('/'),
        ];
    }
}
