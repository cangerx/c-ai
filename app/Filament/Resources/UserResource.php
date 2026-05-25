<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\UserResource\Pages;
use App\Models\AgentSite;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = '用户管理';
    protected static ?string $modelLabel = '用户';
    protected static ?string $pluralModelLabel = '用户';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基本信息')->description('用户的登录和身份信息')->schema([
                Forms\Components\TextInput::make('name')->label('名称')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->label('邮箱')->email()->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')->label('密码')->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? '留空则不修改' : ''),
                Forms\Components\Select::make('role')->label('角色')
                    ->options(['admin' => '管理员', 'agent' => '代理商', 'user' => '用户'])
                    ->required(),
                Forms\Components\Select::make('status')->label('状态')
                    ->options(['active' => '正常', 'disabled' => '封禁'])
                    ->required(),
                Forms\Components\Select::make('agent_level_id')->label('代理等级')
                    ->relationship('agentLevel', 'name')
                    ->placeholder('默认')
                    ->visible(fn ($get) => $get('role') === 'agent'),
            ])->columns(2),

            Section::make('账户余额')->description('积分和余额管理')->schema([
                Forms\Components\TextInput::make('credits')->label('积分')->numeric()->default(0)
                    ->suffix('积分'),
                Forms\Components\TextInput::make('balance')->label('余额')->numeric()->default(0)
                    ->prefix('¥'),
            ])->columns(2),

            Section::make('关联信息')->schema([
                Forms\Components\TextInput::make('invite_code')->label('邀请码')->disabled()->visibleOn('edit'),
                Forms\Components\Placeholder::make('parent_name')->label('上级代理')
                    ->content(fn (User $record) => $record->parent_id ? User::find($record->parent_id)?->name ?? '—' : '无')
                    ->visibleOn('edit'),
            ])->columns(2)->collapsible()->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('email')->label('邮箱')->searchable()->color('gray')->size('sm'),
                Tables\Columns\TextColumn::make('role')->label('角色')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'admin' => '管理员',
                        'agent' => '代理商',
                        default => '用户',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'agent' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('agentLevel.name')->label('代理等级')
                    ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('credits')->label('积分')->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('balance')->label('余额')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? '正常' : '封禁')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->label('注册时间')->dateTime('Y-m-d H:i')->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->label('角色')
                    ->options(['admin' => '管理员', 'agent' => '代理商', 'user' => '用户']),
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options(['active' => '正常', 'disabled' => '封禁']),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('toggleStatus')
                    ->label(fn (User $record) => $record->status === 'active' ? '封禁' : '解封')
                    ->icon('heroicon-o-no-symbol')
                    ->color(fn (User $record) => $record->status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update([
                        'status' => $record->status === 'active' ? 'disabled' : 'active',
                    ])),
                Actions\Action::make('makeAgent')
                    ->label('开通代理')
                    ->icon('heroicon-o-building-storefront')
                    ->color('warning')
                    ->visible(fn (User $record) => $record->role === 'user')
                    ->requiresConfirmation()
                    ->modalHeading('开通代理')
                    ->modalDescription(fn (User $record) => "将用户「{$record->name}」升级为代理商并创建分站")
                    ->form([
                        Forms\Components\TextInput::make('site_name')->label('站点名称')->required()->maxLength(100),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->role = 'agent';
                        $record->save();
                        $record->ensureInviteCode();
                        AgentSite::create([
                            'user_id' => $record->id,
                            'site_name' => $data['site_name'],
                            'slug' => $record->invite_code,
                            'subdomain' => $record->invite_code,
                            'status' => 'approved',
                            'is_active' => true,
                            'approved_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()->title('代理开通成功')->success()->send();
                    }),
                Actions\Action::make('viewSite')
                    ->label('分站')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (User $record) => $record->role === 'agent' && AgentSite::where('user_id', $record->id)->exists())
                    ->url(fn (User $record) => AgentSiteResource::getUrl('edit', ['record' => AgentSite::where('user_id', $record->id)->value('id')])),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
