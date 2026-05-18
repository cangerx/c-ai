<?php

namespace App\Filament\Agent\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Agent\Resources\SubUserResource\Pages;
use App\Models\AgentTransaction;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SubUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = '下级用户';
    protected static ?string $modelLabel = '用户';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('parent_id', auth()->id());
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
                Tables\Columns\TextColumn::make('name')->label('昵称')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('邮箱')->searchable(),
                Tables\Columns\TextColumn::make('credits')->label('积分')->sortable(),
                Tables\Columns\TextColumn::make('balance')->label('余额')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->label('注册时间')->date('Y-m-d')->sortable(),
            ])
            ->actions([
                Actions\Action::make('rechargeCredits')
                    ->label('充积分')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('credits')->label('积分数量')->numeric()->required()->minValue(1),
                    ])
                    ->action(function (User $record, array $data) {
                        $agent = auth()->user();
                        $amount = (int) $data['credits'];
                        if ($agent->credits < $amount) {
                            \Filament\Notifications\Notification::make()->title('积分不足')->danger()->send();
                            return;
                        }
                        DB::transaction(function () use ($agent, $record, $amount) {
                            User::where('id', $agent->id)->decrement('credits', $amount);
                            User::where('id', $record->id)->increment('credits', $amount);
                            AgentTransaction::create([
                                'user_id' => $agent->id,
                                'type' => 'recharge',
                                'credits' => -$amount,
                                'balance' => 0,
                                'credits_after' => $agent->fresh()->credits,
                                'balance_after' => $agent->balance,
                                'description' => "划转积分给 {$record->name}",
                            ]);
                        });
                        \Filament\Notifications\Notification::make()->title("已充值 {$amount} 积分")->success()->send();
                    }),
                Actions\Action::make('rechargeBalance')
                    ->label('充余额')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('balance')->label('余额 (¥)')->numeric()->required()->minValue(0.01)->step(0.01),
                    ])
                    ->action(function (User $record, array $data) {
                        $agent = auth()->user();
                        $amount = (float) $data['balance'];
                        if ($agent->balance < $amount) {
                            \Filament\Notifications\Notification::make()->title('余额不足')->danger()->send();
                            return;
                        }
                        DB::transaction(function () use ($agent, $record, $amount) {
                            User::where('id', $agent->id)->decrement('balance', $amount);
                            User::where('id', $record->id)->increment('balance', $amount);
                            AgentTransaction::create([
                                'user_id' => $agent->id,
                                'type' => 'recharge',
                                'credits' => 0,
                                'balance' => -$amount,
                                'credits_after' => $agent->credits,
                                'balance_after' => $agent->fresh()->balance,
                                'description' => "划转余额给 {$record->name}",
                            ]);
                        });
                        \Filament\Notifications\Notification::make()->title("已充值 ¥{$amount}")->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubUsers::route('/'),
        ];
    }
}
