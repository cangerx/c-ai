<?php

namespace App\Filament\Agent\Pages;

use App\Models\AgentSite;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = '分站设置';
    protected static ?string $title = '分站设置';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.agent.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $site = AgentSite::firstOrNew(['user_id' => auth()->id()]);
        $data = $site->toArray();
        if (empty($data['subdomain'])) {
            $data['subdomain'] = auth()->user()->invite_code ?? '';
        }
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return $schema
            ->components([
                Section::make('基本信息')
                    ->description('设置分站的基本标识信息')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('站点名称')
                            ->placeholder('例：AI 创作平台')
                            ->required()
                            ->maxLength(100),
                    ])->columns(2),

                Section::make('域名设置')
                    ->description('配置独立访问地址（可选）')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('subdomain')
                            ->label('子域名前缀')
                            ->placeholder('mysite')
                            ->helperText('与右侧域名组合为完整子域名访问地址')
                            ->maxLength(32)
                            ->alphaDash()
                            ->unique(
                                table: 'agent_sites',
                                column: 'subdomain',
                                ignorable: fn () => AgentSite::where('user_id', auth()->id())->first(),
                                modifyRuleUsing: fn ($rule) => $rule->where('subdomain_domain', $this->data['subdomain_domain'] ?? null),
                            ),
                        Forms\Components\Select::make('subdomain_domain')
                            ->label('选择域名')
                            ->options(function () {
                                $domains = json_decode(SiteSetting::get('wildcard_domains', '[]'), true) ?: [];
                                return array_combine($domains, $domains);
                            })
                            ->helperText('管理员配置的泛解析域名'),
                        Forms\Components\TextInput::make('custom_domain')
                            ->label('自定义域名')
                            ->placeholder('ai.example.com')
                            ->helperText('需将域名 CNAME 解析到主站服务器')
                            ->maxLength(255),
                    ])->columns(3),

                Section::make('外观')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('logo_url')
                            ->label('Logo 地址')
                            ->placeholder('https://...')
                            ->url()
                            ->maxLength(500),
                        Forms\Components\ColorPicker::make('theme_color')
                            ->label('主题色')
                            ->default('#2d5bf0'),
                        Forms\Components\Textarea::make('announcement')
                            ->label('站内公告')
                            ->placeholder('在此输入公告内容，用户登录后可见')
                            ->maxLength(1000)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('首页展示')
                    ->description('自定义首页标题、副标题和背景')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('hero_title')
                            ->label('首页大标题')
                            ->placeholder('不填则使用站点名称')
                            ->maxLength(200),
                        Forms\Components\TextInput::make('hero_subtitle')
                            ->label('副标题 / 标语')
                            ->placeholder('一句话描述你的平台')
                            ->maxLength(500),
                        Forms\Components\TextInput::make('hero_bg_url')
                            ->label('背景图片地址')
                            ->placeholder('https://... 留空则使用背景色')
                            ->url()
                            ->maxLength(500),
                        Forms\Components\ColorPicker::make('hero_bg_color')
                            ->label('背景色')
                            ->helperText('无背景图时生效'),
                    ])->columns(2),

                Section::make('SEO 搜索优化')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('seo_title')
                            ->label('页面标题')
                            ->placeholder('不填则使用站点名称')
                            ->maxLength(200),
                        Forms\Components\TextInput::make('seo_description')
                            ->label('描述')
                            ->placeholder('搜索引擎展示的简介')
                            ->maxLength(500),
                        Forms\Components\TextInput::make('seo_keywords')
                            ->label('关键词')
                            ->placeholder('用逗号分隔，如：AI,写作,绘画')
                            ->maxLength(300),
                    ]),

                Section::make('页脚设置')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('footer_text')
                            ->label('页脚文本')
                            ->placeholder('例：© 2025 我的AI平台')
                            ->maxLength(300),
                        Forms\Components\TextInput::make('footer_icp')
                            ->label('备案号')
                            ->placeholder('例：京ICP备XXXXXXXX号')
                            ->maxLength(100),
                        Forms\Components\Repeater::make('footer_links')
                            ->label('页脚链接')
                            ->schema([
                                Forms\Components\TextInput::make('text')
                                    ->label('文字')
                                    ->required()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('url')
                                    ->label('链接')
                                    ->required()
                                    ->url()
                                    ->maxLength(300),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->maxItems(5)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('计费与佣金')
                    ->description('设置用户使用扣费及你的分成比例')
                    ->schema([
                        Forms\Components\TextInput::make('cost_per_generation')
                            ->label('单次生成扣费')
                            ->suffix('积分')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('用户每次调用 AI 扣除的积分数'),
                        Forms\Components\TextInput::make('commission_rate')
                            ->label('佣金比例')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('分销员下级用户消费时，从你的积分池扣除的分成比例'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
        } catch (\Throwable $e) {
            Notification::make()->title('表单验证失败: ' . $e->getMessage())->danger()->send();
            return;
        }

        $userId = auth()->id();
        $site = AgentSite::firstOrNew(['user_id' => $userId]);

        $data['user_id'] = $userId;
        $data['theme_color'] = $data['theme_color'] ?: '#2d5bf0';

        // 只保留模型允许的字段
        $fillable = (new AgentSite())->getFillable();
        $data = array_intersect_key($data, array_flip($fillable));
        $data['user_id'] = $userId;

        try {
            if ($site->exists) {
                Cache::forget("agent_site:domain:{$site->custom_domain}");
                Cache::forget("agent_site:sub:{$site->subdomain}@{$site->subdomain_domain}");
                $site->update($data);
                Cache::forget("agent_site:domain:{$site->custom_domain}");
                Cache::forget("agent_site:sub:{$site->subdomain}@{$site->subdomain_domain}");
                Notification::make()->title('分站设置已保存')->success()->send();
            } else {
                $data['slug'] = $data['subdomain'] ?? str()->random(8);
                $data['status'] = 'pending';
                $data['is_active'] = false;
                AgentSite::create($data);
                Notification::make()->title('分站申请已提交，等待审核')->success()->send();
            }
        } catch (\Throwable $e) {
            \Log::error('分站设置保存失败', ['error' => $e->getMessage(), 'user_id' => $userId]);
            Notification::make()->title('保存失败: ' . mb_substr($e->getMessage(), 0, 100))->danger()->send();
        }
    }
}
