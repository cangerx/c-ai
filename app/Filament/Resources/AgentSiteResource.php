<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\AgentSiteResource\Pages;
use App\Models\AgentSite;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class AgentSiteResource extends Resource
{
    protected static ?string $model = AgentSite::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = '分站管理';
    protected static ?string $modelLabel = '分站';
    protected static string | UnitEnum | null $navigationGroup = '代理商';
    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $count = AgentSite::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基本信息')->schema([
                Forms\Components\Select::make('user_id')->label('用户')
                    ->relationship('agent', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visibleOn('create'),
                Forms\Components\TextInput::make('site_name')->label('站点名称')->required(),
                Forms\Components\TextInput::make('subdomain')->label('子域名前缀')
                    ->required()->maxLength(32)->alphaDash()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('subdomain_domain')->label('泛解析域名')
                    ->options(function () {
                        $domains = json_decode(\App\Models\SiteSetting::get('wildcard_domains', '[]'), true) ?: [];
                        return array_combine($domains, $domains);
                    }),
                Forms\Components\TextInput::make('custom_domain')->label('自定义域名'),
                Forms\Components\TextInput::make('logo_url')->label('Logo URL'),
                Forms\Components\ColorPicker::make('theme_color')->label('主题色')->default('#2d5bf0'),
            ])->columns(2),
            Section::make('SEO')->schema([
                Forms\Components\TextInput::make('seo_title')->label('SEO 标题'),
                Forms\Components\TextInput::make('seo_description')->label('SEO 描述'),
                Forms\Components\TextInput::make('seo_keywords')->label('SEO 关键词'),
                Forms\Components\Textarea::make('announcement')->label('站内公告')->rows(2),
            ])->columns(2)->collapsible(),
            Section::make('运营参数')->schema([
                Forms\Components\TextInput::make('cost_per_generation')->label('单次扣费 (积分)')->numeric(),
                Forms\Components\TextInput::make('commission_rate')->label('佣金比例 (%)')->numeric()->minValue(0)->maxValue(100),
                Forms\Components\Toggle::make('is_active')->label('启用')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('site_name')->label('站点')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('agent.name')->label('代理商')->searchable()
                    ->url(fn (AgentSite $record) => UserResource::getUrl('edit', ['record' => $record->user_id])),
                Tables\Columns\TextColumn::make('subdomain')->label('子域名')
                    ->formatStateUsing(fn (AgentSite $record) => $record->subdomain ? ($record->subdomain . '.' . ($record->subdomain_domain ?? config('app.domain'))) : '—')
                    ->color('gray')->size('sm'),
                Tables\Columns\TextColumn::make('custom_domain')->label('自定义域名')->placeholder('—'),
                Tables\Columns\TextColumn::make('agent.agentLevel.name')->label('等级')->placeholder('默认')
                    ->badge()->color('info'),
                Tables\Columns\TextColumn::make('sub_users')->label('下级用户')
                    ->state(fn (AgentSite $record) => User::where('parent_id', $record->user_id)->count()),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => '待审核',
                        'approved' => '已通过',
                        'rejected' => '已拒绝',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('创建时间')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options([
                        'pending' => '待审核',
                        'approved' => '已通过',
                        'rejected' => '已拒绝',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('启用状态'),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->label('通过')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AgentSite $record) => $record->status === 'pending')
                    ->action(function (AgentSite $record) {
                        $record->update(['status' => 'approved', 'is_active' => true, 'approved_at' => now()]);
                        User::where('id', $record->user_id)->where('role', '!=', 'admin')->update(['role' => 'agent']);
                    }),
                Actions\Action::make('reject')
                    ->label('拒绝')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AgentSite $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reject_reason')->label('拒绝原因')->required(),
                    ])
                    ->action(fn (AgentSite $record, array $data) => $record->update([
                        'status' => 'rejected',
                        'reject_reason' => $data['reject_reason'],
                    ])),
                Actions\Action::make('toggleActive')
                    ->label(fn (AgentSite $record) => $record->is_active ? '禁用' : '启用')
                    ->icon('heroicon-o-power')
                    ->requiresConfirmation()
                    ->visible(fn (AgentSite $record) => $record->status === 'approved')
                    ->action(fn (AgentSite $record) => $record->update(['is_active' => !$record->is_active])),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve')
                        ->label('批量通过')
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(function ($r) {
                            if ($r->status === 'pending') {
                                $r->update(['status' => 'approved', 'is_active' => true, 'approved_at' => now()]);
                                User::where('id', $r->user_id)->where('role', '!=', 'admin')->update(['role' => 'agent']);
                            }
                        })),
                    Actions\BulkAction::make('enable')
                        ->label('批量启用')
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    Actions\BulkAction::make('disable')
                        ->label('批量禁用')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentSites::route('/'),
            'create' => Pages\CreateAgentSite::route('/create'),
            'edit' => Pages\EditAgentSite::route('/{record}/edit'),
        ];
    }
}
