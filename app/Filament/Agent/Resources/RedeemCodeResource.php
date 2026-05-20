<?php

namespace App\Filament\Agent\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Agent\Resources\RedeemCodeResource\Pages;
use App\Models\AgentPlan;
use App\Models\AgentTransaction;
use App\Models\RedeemCode;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedeemCodeResource extends Resource
{
    protected static ?string $model = RedeemCode::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = '兑换码';
    protected static ?string $modelLabel = '兑换码';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('created_by', auth()->id());
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
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('编号')->numeric()->sortable()->size('sm'),
                Tables\Columns\TextColumn::make('code')->label('兑换码')
                    ->copyable()
                    ->copyMessage('兑换码已复制')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('sm')
                    ->tooltip(fn (RedeemCode $record) => $record->code),
                Tables\Columns\TextColumn::make('credits')->label('积分')->suffix(' 积分'),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'unused' => 'success',
                        'used' => 'gray',
                        'disabled' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'unused' => '未使用',
                        'used' => '已使用',
                        'disabled' => '已禁用',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('batch_id')->label('批次')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->label('过期')->date('Y-m-d')->placeholder('永久'),
                Tables\Columns\TextColumn::make('created_at')->label('创建')->date('Y-m-d'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options(['unused' => '未使用', 'used' => '已使用', 'disabled' => '已禁用']),
            ])
            ->headerActions([
                Actions\Action::make('generate')
                    ->label('批量生成')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('plan_id')->label('套餐')
                            ->options(fn () => AgentPlan::where('agent_id', auth()->id())->active()->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('count')->label('数量')->numeric()->required()->minValue(1)->maxValue(100)->default(10),
                        Forms\Components\DatePicker::make('expires_at')->label('过期日期'),
                    ])
                    ->action(function (array $data) {
                        $agent = auth()->user();
                        $plan = AgentPlan::where('agent_id', $agent->id)->findOrFail($data['plan_id']);
                        $count = (int) $data['count'];
                        $totalCost = $plan->credits * $count;

                        if ($agent->credits < $totalCost) {
                            \Filament\Notifications\Notification::make()->title("积分不足，需要 {$totalCost}")->danger()->send();
                            return;
                        }

                        try {
                            DB::transaction(function () use ($agent, $plan, $count, $data, $totalCost) {
                                $locked = User::lockForUpdate()->find($agent->id);
                                if ($locked->credits < $totalCost) {
                                    throw new \RuntimeException('积分不足');
                                }
                                $locked->decrement('credits', $totalCost);
                                $batchId = now()->format('YmdHis') . '-' . Str::random(4);

                                for ($i = 0; $i < $count; $i++) {
                                    RedeemCode::create([
                                        'agent_plan_id' => $plan->id,
                                        'code' => strtoupper(Str::random(12)),
                                        'type' => 'credits',
                                        'credits' => $plan->credits,
                                        'balance' => 0,
                                        'status' => 'unused',
                                        'created_by' => $agent->id,
                                        'batch_id' => $batchId,
                                        'expires_at' => $data['expires_at'] ?? null,
                                    ]);
                                }

                                AgentTransaction::create([
                                    'user_id' => $agent->id,
                                    'type' => 'generate',
                                    'credits' => -$totalCost,
                                    'balance' => 0,
                                    'credits_after' => $locked->fresh()->credits,
                                    'balance_after' => $locked->balance,
                                    'description' => "生成 {$count} 个兑换码 (套餐: {$plan->name})",
                                ]);
                            });
                        } catch (\RuntimeException $e) {
                            \Filament\Notifications\Notification::make()->title($e->getMessage())->danger()->send();
                            return;
                        }

                        \Filament\Notifications\Notification::make()->title("成功生成 {$count} 个兑换码")->success()->send();
                    }),
            ])
            ->actions([
                Actions\Action::make('disable')
                    ->label('禁用')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (RedeemCode $record) => $record->status === 'unused')
                    ->action(function (RedeemCode $record) {
                        DB::transaction(function () use ($record) {
                            User::where('id', $record->created_by)->increment('credits', $record->credits);
                            $record->update(['status' => 'disabled']);
                        });
                        \Filament\Notifications\Notification::make()->title('已禁用并退回积分')->success()->send();
                    }),
                Actions\Action::make('delete')
                    ->label('删除')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('确认删除')
                    ->modalDescription('删除未使用的兑换码将退回积分，确认？')
                    ->visible(fn (RedeemCode $record) => in_array($record->status, ['unused', 'disabled']))
                    ->action(function (RedeemCode $record) {
                        DB::transaction(function () use ($record) {
                            if ($record->status === 'unused') {
                                User::where('id', $record->created_by)->increment('credits', $record->credits);
                            }
                            $record->delete();
                        });
                        \Filament\Notifications\Notification::make()->title('已删除')->success()->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('disable')
                        ->label('批量禁用')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            DB::transaction(function () use ($records) {
                                foreach ($records as $record) {
                                    if ($record->status === 'unused') {
                                        User::where('id', $record->created_by)->increment('credits', $record->credits);
                                        $record->update(['status' => 'disabled']);
                                    }
                                }
                            });
                            \Filament\Notifications\Notification::make()
                                ->title("已禁用 {$records->count()} 个兑换码并退回积分")
                                ->success()
                                ->send();
                        }),
                    Actions\BulkAction::make('delete')
                        ->label('批量删除')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('确认批量删除')
                        ->modalDescription('删除未使用的兑换码将退回积分，确认？')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            DB::transaction(function () use ($records) {
                                foreach ($records as $record) {
                                    if ($record->status === 'unused') {
                                        User::where('id', $record->created_by)->increment('credits', $record->credits);
                                    }
                                    $record->delete();
                                }
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('已批量删除')
                                ->success()
                                ->send();
                        }),
                ]),
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
