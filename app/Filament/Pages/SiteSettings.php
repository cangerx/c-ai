<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = '站点设置';
    protected static ?string $title = '站点设置';
    protected static string | UnitEnum | null $navigationGroup = '系统';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.site-settings';

    /**
     * 字段 → group 映射，决定 SiteSetting::set 时存到哪个 group
     */
    /**
     * 字段 → group 映射。所有 key 必须有业务代码实际读取，不放死字段。
     */
    private const FIELD_GROUPS = [
        // 基础（SEO 标签 / 默认占位用）
        'site_name' => 'general',
        'site_description' => 'general',
        'site_keywords' => 'general',
        // 首页可视化
        'hero_title' => 'general',
        'hero_subtitle' => 'general',
        'hero_bg_url' => 'general',
        'hero_bg_color' => 'general',
        'footer_text' => 'general',
        'footer_icp' => 'general',
        // 模型
        'prompt_tool_model' => 'model',
        'reverse_prompt_model' => 'model',
        'reverse_prompt_base_url' => 'model',
        'reverse_prompt_api_key' => 'model',
        // 计费
        'billing_per_generation' => 'billing',
        // 注册赠送
        'register_gift_credits' => 'billing',
        'register_gift_balance' => 'billing',
        // 分销
        'distributor_threshold' => 'distributor',
        'distributor_commission_rate' => 'distributor',
        // 代理
        'recharge_purchase_url' => 'agent',
        'wildcard_domains' => 'agent',
        // 前端主题
        'active_theme' => 'general',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $settings = [];
        foreach (array_keys(self::FIELD_GROUPS) as $key) {
            $settings[$key] = SiteSetting::get($key);
        }
        // 类型转换
        if (isset($settings['wildcard_domains'])) {
            $settings['wildcard_domains'] = json_decode($settings['wildcard_domains'] ?? '[]', true) ?: [];
        }

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('SEO / 基础信息')->schema([
                    Forms\Components\TextInput::make('site_name')->label('站点名称')->placeholder('CANG-AI'),
                    Forms\Components\TextInput::make('site_description')->label('站点描述（SEO Description）'),
                    Forms\Components\TextInput::make('site_keywords')->label('SEO 关键词')->placeholder('AI,图像生成,绘画'),
                ])->columns(2),

                Section::make('首页展示')->description('自定义主站首页标题、背景和页脚')->collapsible()->schema([
                    Forms\Components\TextInput::make('hero_title')
                        ->label('首页大标题')
                        ->placeholder('不填则使用站点名称')
                        ->maxLength(200),
                    Forms\Components\TextInput::make('hero_subtitle')
                        ->label('副标题 / 标语')
                        ->placeholder('一句话描述平台')
                        ->maxLength(500),
                    Forms\Components\TextInput::make('hero_bg_url')
                        ->label('背景图片地址')
                        ->placeholder('https://... 留空则使用背景色')
                        ->url()
                        ->maxLength(500),
                    Forms\Components\ColorPicker::make('hero_bg_color')
                        ->label('背景色')
                        ->helperText('无背景图时生效'),
                    Forms\Components\TextInput::make('footer_text')
                        ->label('页脚文本')
                        ->placeholder('例：© 2025 CANG-AI')
                        ->maxLength(300),
                    Forms\Components\TextInput::make('footer_icp')
                        ->label('备案号')
                        ->placeholder('例：京ICP备XXXXXXXX号')
                        ->maxLength(100),
                ])->columns(2),

                Section::make('模型设置')->schema([
                    Forms\Components\TextInput::make('prompt_tool_model')
                        ->label('提示词工具模型（优化/翻译）')
                        ->placeholder('gpt-5.4-mini')
                        ->helperText('用于提示词优化、翻译等辅助任务的模型 ID'),
                    Forms\Components\TextInput::make('reverse_prompt_model')
                        ->label('反推模型')
                        ->placeholder('gpt-5.4-mini')
                        ->helperText('视觉模型 ID，如 gpt-5.4-mini、gpt-5.4'),
                    Forms\Components\TextInput::make('reverse_prompt_base_url')
                        ->label('反推 API 地址')
                        ->placeholder('https://api.openai.com')
                        ->helperText('支持 /v1/chat/completions 的 API 地址（优先于渠道）'),
                    Forms\Components\TextInput::make('reverse_prompt_api_key')
                        ->label('反推 API Key')
                        ->password()
                        ->helperText('对应 API 地址的密钥'),
                ])->columns(2),

                Section::make('计费设置')->schema([
                    Forms\Components\TextInput::make('billing_per_generation')
                        ->label('每次生成扣积分数')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('1')
                        ->helperText('不区分模型、尺寸、质量，统一扣固定积分'),
                ]),

                Section::make('注册与分销')->schema([
                    Forms\Components\TextInput::make('register_gift_credits')
                        ->label('注册赠送积分')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('5'),
                    Forms\Components\TextInput::make('register_gift_balance')
                        ->label('注册赠送余额（元）')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->placeholder('0'),
                    Forms\Components\TextInput::make('distributor_threshold')
                        ->label('分销申请门槛（累计消费积分数）')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('100'),
                    Forms\Components\TextInput::make('distributor_commission_rate')
                        ->label('分销返利比例')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.01)
                        ->placeholder('0.10')
                        ->helperText('0~1，如 0.1 = 10%'),
                ])->columns(2),

                Section::make('代理充值')->schema([
                    Forms\Components\TextInput::make('recharge_purchase_url')
                        ->label('充值购买地址')
                        ->placeholder('https://...')
                        ->helperText('代理面板「充值兑换」页面会展示此链接，引导代理前往购买')
                        ->url()
                        ->maxLength(500),
                ]),

                Section::make('泛解析域名')->description('代理可选择的子域名后缀')->schema([
                    Forms\Components\TagsInput::make('wildcard_domains')
                        ->label('域名列表')
                        ->placeholder('输入域名后回车，如 ai.com')
                        ->helperText('需在 DNS 对每个域名做 *.domain 泛解析指向本服务器'),
                ]),

                Section::make('前端主题')
                    ->description('选择前端站点默认渲染哪套模板。用户无 cookie 时使用此默认值。')
                    ->schema([
                        Forms\Components\TextInput::make('active_theme')
                            ->label('默认模板 key')
                            ->placeholder('default')
                            ->helperText(
                                '前端 src/themes/<key>/ 下注册的模板 key，例如：default、chatgpt-like。' .
                                '改动后立即对未设置 cookie 的访客生效；已设置 cookie 的用户不受影响。'
                            )
                            ->maxLength(64),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::FIELD_GROUPS as $key => $group) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];

            if ($key === 'wildcard_domains') {
                $value = json_encode($value ?: []);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif ($value === null) {
                $value = '';
            }

            SiteSetting::set($key, (string) $value, $group);
        }

        Cache::forget('wildcard_domains_list');
        Notification::make()->title('设置已保存')->success()->send();
    }
}
