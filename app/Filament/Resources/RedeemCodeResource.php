<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\RedeemCodeResource\Pages;
use App\Models\Plan;
use App\Models\RedeemCode;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RedeemCodeResource extends Resource
{
    protected static ?string $model = RedeemCode::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = '兑换码';
    protected static ?string $modelLabel = '兑换码';
    protected static string | UnitEnum | null $navigationGroup = '业务配置';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('code')->label('兑换码')->disabled(),
            Forms\Components\Select::make('type')->label('类型')
                ->options(['credits' => '积分', 'balance' => '余额', 'mixed' => '混合']),
            Forms\Components\TextInput::make('credits')->label('积分')->numeric(),
            Forms\Components\TextInput::make('balance')->label('余额')->numeric()->prefix('¥'),
            Forms\Components\Select::make('status')->label('状态')
                ->options(['unused' => '未使用', 'used' => '已使用', 'disabled' => '已作废']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('兑换码')->searchable()
                    ->copyable()->fontFamily('mono')->size('sm')->limit(16),
                Tables\Columns\TextColumn::make('type')->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'credits' => '积分',
                        'balance' => '余额',
                        'mixed' => '混合',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'credits' => 'info',
                        'balance' => 'success',
                        'mixed' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('credits')->label('积分'),
                Tables\Columns\TextColumn::make('balance')->label('余额')->prefix('¥'),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'unused' => '未使用',
                        'used' => '已使用',
                        'disabled' => '已作废',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'unused' => 'success',
                        'used' => 'gray',
                        'disabled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('batch_id')->label('批次')->searchable()
                    ->fontFamily('mono')->size('sm')->color('gray'),
                Tables\Columns\TextColumn::make('user.name')->label('使用者')->placeholder('—')
                    ->url(fn (RedeemCode $record) => $record->user_id ? UserResource::getUrl('edit', ['record' => $record->user_id]) : null),
                Tables\Columns\TextColumn::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options(['unused' => '未使用', 'used' => '已使用', 'disabled' => '已作废']),
                Tables\Filters\SelectFilter::make('batch_id')->label('批次')
                    ->options(fn () => RedeemCode::whereNotNull('batch_id')->distinct()->pluck('batch_id', 'batch_id')->toArray())
                    ->searchable(),
            ])
            ->actions([
                Actions\Action::make('disable')
                    ->label('作废')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (RedeemCode $record) => $record->status === 'unused')
                    ->action(fn (RedeemCode $record) => $record->update(['status' => 'disabled'])),
            ])
            ->headerActions([
                Actions\Action::make('batchGenerate')
                    ->label('批量生成')
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        Section::make()->schema([
                            Forms\Components\TextInput::make('count')->label('数量')->numeric()->required()->minValue(1)->maxValue(500)->default(10),
                            Forms\Components\Select::make('plan_id')->label('关联套餐（可选）')
                                ->options(fn () => Plan::active()->ordered()->pluck('name', 'id')->toArray())
                                ->placeholder('不关联套餐')
                                ->searchable(),
                            Forms\Components\Select::make('type')->label('类型')
                                ->options(['credits' => '积分', 'balance' => '余额', 'mixed' => '混合'])
                                ->required()->default('credits'),
                            Forms\Components\TextInput::make('credits')->label('积分')->numeric()->default(100),
                            Forms\Components\TextInput::make('balance')->label('余额 (¥)')->numeric()->default(0),
                            Forms\Components\TextInput::make('expires_days')->label('有效天数')->numeric()->placeholder('永不过期'),
                        ])->columns(2),
                    ])
                    ->action(function (array $data) {
                        $batchId = 'B' . now()->format('ymdHis') . Str::random(4);
                        $expiresAt = !empty($data['expires_days']) ? now()->addDays($data['expires_days']) : null;

                        for ($i = 0; $i < $data['count']; $i++) {
                            RedeemCode::create([
                                'code' => strtoupper(Str::random(32)),
                                'type' => $data['type'],
                                'credits' => $data['credits'] ?? 0,
                                'balance' => $data['balance'] ?? 0,
                                'status' => 'unused',
                                'created_by' => auth()->id(),
                                'expires_at' => $expiresAt,
                                'batch_id' => $batchId,
                                'plan_id' => $data['plan_id'] ?? null,
                            ]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title("已生成 {$data['count']} 个兑换码")
                            ->body("批次号: {$batchId}")
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('exportCsv')
                    ->label('导出 CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('batch_id')->label('选择批次')
                            ->options(fn () => RedeemCode::whereNotNull('batch_id')
                                ->distinct()
                                ->orderByDesc('created_at')
                                ->pluck('batch_id', 'batch_id')->toArray())
                            ->placeholder('全部批次（导出所有未使用）')
                            ->searchable(),
                        Forms\Components\Select::make('status')->label('状态')
                            ->options(['' => '全部', 'unused' => '未使用', 'used' => '已使用', 'disabled' => '已作废'])
                            ->default('unused'),
                    ])
                    ->action(function (array $data) {
                        $query = RedeemCode::query();
                        if (!empty($data['batch_id'])) {
                            $query->where('batch_id', $data['batch_id']);
                        }
                        if (!empty($data['status'])) {
                            $query->where('status', $data['status']);
                        }
                        $codes = $query->orderBy('id')->get();

                        $filename = 'redeem_codes_' . now()->format('YmdHis') . '.csv';
                        $callback = function () use ($codes) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
                            fputcsv($out, ['兑换码', '类型', '积分', '余额', '状态', '批次', '过期时间', '创建时间']);
                            foreach ($codes as $c) {
                                fputcsv($out, [
                                    $c->code, $c->type, $c->credits, $c->balance, $c->status,
                                    $c->batch_id, optional($c->expires_at)->format('Y-m-d H:i'),
                                    $c->created_at->format('Y-m-d H:i'),
                                ]);
                            }
                            fclose($out);
                        };

                        return response()->streamDownload($callback, $filename, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                        ]);
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedeemCodes::route('/'),
        ];
    }
}
