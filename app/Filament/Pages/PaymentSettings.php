<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\Payment\PaymentManager;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = '支付配置';
    protected static ?string $title = '支付渠道·顺行付';

    public function getSubheading(): ?string
    {
        return '填写机构号、商户号与 PKCS8 私钥后保存，点击下方测试下单验证连通。';
    }
    protected static string | UnitEnum | null $navigationGroup = '业务配置';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.payment-settings';

    private const FIELDS = [
        'payment_tianque_enabled',
        'payment_tianque_method_wechat',
        'payment_tianque_method_alipay',
        'payment_tianque_method_unionpay',
        'payment_tianque_sandbox',
        'payment_tianque_host',
        'payment_tianque_host_production',
        'payment_tianque_org_id',
        'payment_tianque_mno',
        'payment_tianque_sub_mech_id',
        'payment_tianque_sign_type',
        'payment_tianque_version',
        'payment_tianque_notify_url',
        'payment_tianque_private_key',
        'payment_tianque_public_key',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $values = [];
        foreach (self::FIELDS as $key) {
            // 私钥永不回显，给空让用户决定是否覆盖
            if (in_array($key, ['payment_tianque_private_key'])) {
                $values[$key] = '';
                continue;
            }
            $values[$key] = SiteSetting::get($key, $this->envFallback($key));
        }
        $values['payment_tianque_sandbox'] = filter_var(
            SiteSetting::get('payment_tianque_sandbox', env('TIANQUE_SANDBOX', true)),
            FILTER_VALIDATE_BOOLEAN
        );
        $values['payment_tianque_enabled'] = filter_var(
            SiteSetting::get('payment_tianque_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
        foreach (['wechat' => true, 'alipay' => true, 'unionpay' => false] as $m => $def) {
            $values['payment_tianque_method_' . $m] = filter_var(
                SiteSetting::get('payment_tianque_method_' . $m, $def),
                FILTER_VALIDATE_BOOLEAN
            );
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('渠道状态')
                ->description('未启用前，用户端点击立即充值会提示支付暂不可用。')
                ->schema([
                    Forms\Components\Toggle::make('payment_tianque_enabled')
                        ->label('启用「顺行付」渠道')
                        ->inline(false)
                        ->default(false),
                    Forms\Components\Toggle::make('payment_tianque_sandbox')
                        ->label('沙箱测试模式')
                        ->helperText('开启=对接测试环境；关闭=对接生产环境')
                        ->inline(false)
                        ->default(true),
                ])->columns(2),

            Section::make('支付方式')
                ->description('独立控制各支付方式是否对用户开放。关闭的方式不会出现在前端选择列表，且后端会拒绝对应订单。')
                ->schema([
                    Forms\Components\Toggle::make('payment_tianque_method_wechat')
                        ->label('微信支付')
                        ->inline(false)
                        ->default(true),
                    Forms\Components\Toggle::make('payment_tianque_method_alipay')
                        ->label('支付宝')
                        ->inline(false)
                        ->default(true),
                    Forms\Components\Toggle::make('payment_tianque_method_unionpay')
                        ->label('银联')
                        ->inline(false)
                        ->default(false),
                ])->columns(3),

            Section::make('接入身份')
                ->description('身份类型决定 version 参数：商户=1.2，服务商=1.0。')
                ->schema([
                    Forms\Components\Select::make('payment_tianque_version')
                        ->label('身份类型 version')
                        ->options(['1.0' => '服务商 (version=1.0)', '1.2' => '商户 (version=1.2)'])
                        ->default('1.0')
                        ->required(),
                    Forms\Components\TextInput::make('payment_tianque_org_id')
                        ->label('机构编号 orgId')
                        ->placeholder('8 或 10 位纯数字，例：26680846')
                        ->helperText('顺行付分配的机构 ID')
                        ->rule('regex:/^\d{8}$|^\d{10}$/')
                        ->validationMessages(['regex' => '机构号应为 8 或 10 位纯数字'])
                        ->required(),
                    Forms\Components\TextInput::make('payment_tianque_mno')
                        ->label('商户号 mno')
                        ->placeholder('399 开头 15 位，例：399190910000387')
                        ->helperText('开发文档参数名 mno（不是 mch_id）')
                        ->rule('regex:/^399\d{12}$/')
                        ->validationMessages(['regex' => '商户号应为 399 开头的 15 位数字'])
                        ->required(),
                    Forms\Components\TextInput::make('payment_tianque_sub_mech_id')
                        ->label('子商户号 subMechId（选填）')
                        ->placeholder('一般留空')
                        ->helperText('仅特约商户/分账场景需要'),
                ])->columns(2),

            Section::make('商户私钥')
                ->description('只需填商户私钥一项。顺行付平台公钥为公开固定值，已内置到代码，按沙箱开关自动选择。')
                ->schema([
                    Forms\Components\Select::make('payment_tianque_sign_type')
                        ->label('签名算法')
                        ->options(['RSA' => 'RSA (SHA1) — 推荐', 'RSA2' => 'RSA2 (SHA256)'])
                        ->default('RSA')
                        ->helperText('顺行付官方使用 SHA1withRSA，保持默认 RSA 即可')
                        ->required(),
                    Forms\Components\Textarea::make('payment_tianque_private_key')
                        ->label('商户私钥 (PKCS8)')
                        ->rows(7)
                        ->placeholder("留空保留原私钥不修改\n粘贴 PKCS8 base64 内容，如：MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSj...")
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('仅保存时写入数据库，永不回显。必须 PKCS8 格式（PEM 头为 BEGIN PRIVATE KEY）。若你已有 PKCS1 格式，转换命令：openssl pkcs8 -topk8 -inform PEM -in pkcs1.key -outform PEM -nocrypt -out pkcs8.key'),
                ]),

            Section::make('高级·自定义平台公钥')
                ->description('系统已内置顺行付测试/生产平台公钥，一般无需修改。仅当顺行付更换公钥时才需在此覆盖。')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('payment_tianque_public_key')
                        ->label('自定义平台公钥（覆盖内置值）')
                        ->rows(4)
                        ->placeholder('留空 = 使用内置公钥（推荐）')
                        ->helperText('顺行付下发的平台公钥，不是你自己的公钥'),
                ]),

            Section::make('回调地址')
                ->description('请在顺行付商户后台的异步通知配置中登记以下地址。地址必须公网可达。')
                ->schema([
                    Forms\Components\TextInput::make('payment_tianque_notify_url')
                        ->label('异步通知 URL')
                        ->placeholder(rtrim(config('app.url'), '/') . '/api/payment/notify/tianque')
                        ->prefixIcon('heroicon-o-link')
                        ->helperText('留空时使用默认值：' . rtrim(config('app.url'), '/') . '/api/payment/notify/tianque'),
                ]),

            Section::make('高级·网关地址')
                ->description('一般无需修改，除非顺行付通知你切换了网关。')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('payment_tianque_host')
                        ->label('沙箱网关')
                        ->default('https://openapi-test.tianquetech.com'),
                    Forms\Components\TextInput::make('payment_tianque_host_production')
                        ->label('生产网关')
                        ->default('https://openapi.tianquetech.com'),
                ])->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::FIELDS as $key) {
            if (!array_key_exists($key, $data)) continue;
            $value = $data[$key];
            // 私钥留空不覆盖
            if ($key === 'payment_tianque_private_key' && !filled($value)) continue;
            if (is_bool($value)) $value = $value ? '1' : '0';
            SiteSetting::set($key, (string) ($value ?? ''), 'payment');
        }

        Notification::make()->title('支付配置已保存')->success()->send();
    }

    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('测试下单（0.01 元）')
            ->icon('heroicon-o-rocket-launch')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('测试顺行付沙箱下单')
            ->modalDescription('请先保存配置。将创建一笔 0.01 元的沙箱测试订单，用于验证签名与接口连通性。')
            ->action(function () {
                try {
                    $manager = app(PaymentManager::class);
                    $provider = $manager->driver();
                    $tmp = new Order([
                        'order_no' => 'TEST' . date('YmdHis'),
                        'amount' => 0.01,
                        'subject' => '顺行付连通性测试',
                    ]);
                    $tmp->id = 0;
                    $result = $provider->createOrder($tmp, 'WECHAT', '127.0.0.1');
                    if (!empty($result['qr_code'])) {
                        Notification::make()
                            ->title('连通性验证成功')
                            ->body('返回 payUrl：' . substr($result['qr_code'], 0, 80) . '...')
                            ->success()->duration(8000)->send();
                    } else {
                        Notification::make()
                            ->title('下单未返回支付链接')
                            ->body('原始响应：' . json_encode($result['raw'] ?? [], JSON_UNESCAPED_UNICODE))
                            ->warning()->duration(8000)->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('连通性验证失败')
                        ->body($e->getMessage())
                        ->danger()->duration(10000)->send();
                }
            });
    }

    private function envFallback(string $key): mixed
    {
        return match ($key) {
            'payment_tianque_sandbox' => env('TIANQUE_SANDBOX', true),
            'payment_tianque_host' => env('TIANQUE_HOST', 'https://openapi-test.tianquetech.com'),
            'payment_tianque_host_production' => env('TIANQUE_HOST_PROD', 'https://openapi.tianquetech.com'),
            'payment_tianque_org_id' => env('TIANQUE_ORG_ID', ''),
            'payment_tianque_mno' => env('TIANQUE_MNO', ''),
            'payment_tianque_sub_mech_id' => env('TIANQUE_SUB_MECH_ID', ''),
            'payment_tianque_sign_type' => env('TIANQUE_SIGN_TYPE', 'RSA'),
            'payment_tianque_version' => env('TIANQUE_VERSION', '1.2'),
            'payment_tianque_notify_url' => env('TIANQUE_NOTIFY_URL', rtrim(config('app.url'), '/') . '/api/payment/notify/tianque'),
            'payment_tianque_public_key' => env('TIANQUE_PUBLIC_KEY', ''),
            default => '',
        };
    }
}
