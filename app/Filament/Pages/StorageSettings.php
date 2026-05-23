<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Services\ImageStorageService;
use App\Services\StorageProfileService;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cloud';
    protected static ?string $navigationLabel = '云存储';
    protected static ?string $title = '云存储配置';
    protected static string | UnitEnum | null $navigationGroup = '系统';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.storage-settings';

    private const FIELDS = [
        'storage_driver',
        'storage_access_key',
        'storage_secret_key',
        'storage_bucket',
        'storage_endpoint',
        'storage_region',
        'storage_url',
        'storage_temp_driver',
        'storage_temp_access_key',
        'storage_temp_secret_key',
        'storage_temp_bucket',
        'storage_temp_endpoint',
        'storage_temp_region',
        'storage_temp_url',
        'storage_temp_ttl_days',
        'storage_backup_driver',
        'storage_backup_access_key',
        'storage_backup_secret_key',
        'storage_backup_bucket',
        'storage_backup_endpoint',
        'storage_backup_region',
        'storage_backup_url',
    ];

    public const DRIVERS = [
        'local' => [
            'label'    => '本地存储',
            'desc'     => '存到 storage/app/public，无需任何外部服务，适合开发和小流量站点。',
            'icon'     => 'heroicon-o-server-stack',
            'doc'      => 'https://laravel.com/docs/filesystem',
            'doc_text' => 'Laravel Filesystem 文档',
        ],
        'oss' => [
            'label'    => '阿里云 OSS',
            'desc'     => '阿里云对象存储，适合中国大陆用户，速度快、价格低。',
            'icon'     => 'heroicon-o-cloud',
            'doc'      => 'https://help.aliyun.com/zh/oss/getting-started/',
            'doc_text' => '阿里云 OSS 入门指南',
        ],
        'cos' => [
            'label'    => '腾讯云 COS',
            'desc'     => '腾讯云对象存储，S3 兼容，适合国内用户，与腾讯云 CDN 无缝集成。',
            'icon'     => 'heroicon-o-cloud',
            'doc'      => 'https://cloud.tencent.com/document/product/436/7751',
            'doc_text' => '腾讯云 COS 入门指南',
        ],
        'r2' => [
            'label'    => 'Cloudflare R2',
            'desc'     => '免出口流量费、S3 兼容，适合海外或走 Cloudflare CDN 的场景。',
            'icon'     => 'heroicon-o-globe-alt',
            'doc'      => 'https://developers.cloudflare.com/r2/api/s3/tokens/',
            'doc_text' => 'R2 API Token 文档',
        ],
    ];

    private const SAMPLES = [
        'oss' => [
            'storage_endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
            'storage_region'   => 'oss-cn-hangzhou',
            'storage_bucket'   => 'my-bucket',
            'storage_url'      => 'https://cdn.example.com',
        ],
        'cos' => [
            'storage_endpoint' => 'https://cos.ap-guangzhou.myqcloud.com',
            'storage_region'   => 'ap-guangzhou',
            'storage_bucket'   => 'my-bucket-1250000000',
            'storage_url'      => 'https://cdn.example.com',
        ],
        'r2' => [
            'storage_endpoint' => 'https://<account_id>.r2.cloudflarestorage.com',
            'storage_region'   => 'auto',
            'storage_bucket'   => 'my-bucket',
            'storage_url'      => 'https://cdn.example.com',
        ],
    ];

    public ?array $data = [];
    public array $summary = [];
    public array $diagnostics = [];

    public function mount(): void
    {
        $settings = [];
        foreach (self::FIELDS as $key) {
            if ($key === 'storage_secret_key') {
                $settings[$key] = '';
                continue;
            }
            if (in_array($key, ['storage_temp_secret_key', 'storage_backup_secret_key'], true)) {
                $settings[$key] = '';
                continue;
            }
            $settings[$key] = SiteSetting::get($key);
        }
        if (!$settings['storage_driver']) {
            $settings['storage_driver'] = 'local';
        }
        $this->form->fill($settings);
        $this->refreshSummary();
        $this->refreshDiagnostics();
    }

    private function refreshSummary(): void
    {
        $driver = SiteSetting::get('storage_driver', 'local');
        $meta   = self::DRIVERS[$driver] ?? self::DRIVERS['local'];
        $this->summary = [
            'driver'        => $driver,
            'driver_label'  => $meta['label'],
            'driver_icon'   => $meta['icon'],
            'bucket'        => SiteSetting::get('storage_bucket', '') ?: '—',
            'endpoint'      => SiteSetting::get('storage_endpoint', '') ?: '—',
            'has_secret'    => filled(SiteSetting::get('storage_secret_key', '')),
            'last_test_at'  => SiteSetting::get('storage_last_test_at', ''),
            'last_test_ok'  => SiteSetting::get('storage_last_test_ok', '') === '1',
            'last_test_msg' => SiteSetting::get('storage_last_test_msg', ''),
        ];
    }

    private function refreshDiagnostics(): void
    {
        $this->diagnostics = app(\App\Services\StorageProfileService::class)->diagnostics();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([$this->buildWizard()])->statePath('data');
    }

    private function buildWizard(): Wizard
    {
        return Wizard::make([
            $this->stepDriver(),
            $this->stepCredentials(),
            $this->stepRouting(),
            $this->stepReview(),
        ])
            ->skippable()
            ->persistStepInQueryString('step');
    }

    private function stepDriver(): Step
    {
        return Step::make('选择驱动')
            ->description('挑一个对象存储服务')
            ->icon('heroicon-o-cloud')
            ->schema([
                Forms\Components\Radio::make('storage_driver')
                    ->label('')
                    ->options(collect(self::DRIVERS)->mapWithKeys(fn ($v, $k) => [$k => $v['label']])->all())
                    ->descriptions(collect(self::DRIVERS)->mapWithKeys(fn ($v, $k) => [$k => $v['desc']])->all())
                    ->required()
                    ->live()
                    ->default('local')
                    ->extraAttributes(['class' => 'storage-driver-radio'])
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        if (!isset(self::SAMPLES[$state])) {
                            return;
                        }
                        foreach (self::SAMPLES[$state] as $k => $v) {
                            if (!filled($get($k))) {
                                $set($k, $v);
                            }
                        }
                    }),

                Forms\Components\Placeholder::make('driver_doc')
                    ->label('')
                    ->content(function (Get $get) {
                        $d = $get('storage_driver');
                        if (!isset(self::DRIVERS[$d])) {
                            return '';
                        }
                        $m = self::DRIVERS[$d];
                        $tipMap = [
                            'local' => '适合开发环境和小流量站点，无需任何外部服务',
                            'oss'   => '中国大陆访问最快，按用量付费，需在阿里云创建 RAM 子账号',
                            'cos'   => 'S3 兼容，适合国内用户，需在腾讯云创建子账号或 API 密钥',
                            'r2'    => '免出口流量费，适合海外或走 Cloudflare CDN 的场景',
                        ];
                        $bulb = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;margin-top:.125rem"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>';
                        $book = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:.875rem;height:.875rem;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>';
                        $arrow = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.75rem;height:.75rem;flex-shrink:0;display:inline-block;vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>';
                        $boxStyle = 'border-radius:.5rem;border:1px solid #bfdbfe;background:rgba(239,246,255,.6);padding:.625rem 1rem;font-size:.875rem;color:#1e3a8a;';
                        $rowStyle = 'display:flex;align-items:flex-start;gap:.5rem;';
                        $linkRowStyle = 'margin-top:.375rem;';
                        $linkStyle = 'display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;font-weight:500;color:#2563eb;text-decoration:none;';
                        $html = '<div style="' . $boxStyle . '">'
                              . '<div style="' . $rowStyle . '">' . $bulb . '<span>' . e($tipMap[$d] ?? '') . '</span></div>'
                              . '<div style="' . $linkRowStyle . '"><a href="' . e($m['doc']) . '" target="_blank" rel="noopener" style="' . $linkStyle . '">' . $book . '<span>' . e($m['doc_text']) . '</span>' . $arrow . '</a></div>'
                              . '</div>';
                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ]);
    }

    private function stepCredentials(): Step
    {
        return Step::make('填写凭证')
            ->description('输入 Access Key、Bucket 等')
            ->icon('heroicon-o-key')
            ->schema([
                Forms\Components\Placeholder::make('local_notice')
                    ->label('')
                    ->visible(fn (Get $get) => $get('storage_driver') === 'local')
                    ->content('本地存储无需任何凭证，直接进入下一步即可。'),

                Grid::make(2)
                    ->visible(fn (Get $get) => in_array($get('storage_driver'), ['oss', 'cos', 'r2']))
                    ->schema([
                        Forms\Components\TextInput::make('storage_access_key')
                            ->label('Access Key ID')
                            ->required()
                            ->placeholder('输入 Access Key')
                            ->prefixIcon('heroicon-o-key')
                            ->helperText('OSS 控制台 → AccessKey 管理；R2 → API Tokens'),

                        Forms\Components\TextInput::make('storage_secret_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->placeholder('留空则不修改')
                            ->prefixIcon('heroicon-o-lock-closed')
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('已配置则留空保留原值'),

                        Forms\Components\TextInput::make('storage_bucket')
                            ->label('Bucket 名称')
                            ->required()
                            ->placeholder('my-bucket')
                            ->prefixIcon('heroicon-o-archive-box'),

                        Forms\Components\TextInput::make('storage_region')
                            ->label('Region')
                            ->required()
                            ->placeholder('oss-cn-hangzhou / auto')
                            ->prefixIcon('heroicon-o-map-pin')
                            ->helperText('OSS 写区域 ID；R2 固定写 auto'),

                        Forms\Components\TextInput::make('storage_endpoint')
                            ->label('Endpoint')
                            ->required()
                            ->url()
                            ->placeholder('https://oss-cn-hangzhou.aliyuncs.com')
                            ->prefixIcon('heroicon-o-link')
                            ->helperText('必须以 https:// 开头')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('storage_url')
                            ->label('自定义域名（CDN）')
                            ->placeholder('https://cdn.example.com')
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->url()
                            ->helperText('可选，配置后生成的文件 URL 会用此域名')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private function stepReview(): Step
    {
        return Step::make('确认并测试')
            ->description('检查配置并测试连通性')
            ->icon('heroicon-o-check-badge')
            ->schema([
                Forms\Components\Placeholder::make('review')
                    ->label('')
                    ->content(function (Get $get) {
                        $d = $get('storage_driver') ?: 'local';
                        $m = self::DRIVERS[$d] ?? self::DRIVERS['local'];

                        $iconStyle = 'width:1.25rem;height:1.25rem;flex-shrink:0';
                        $rowIconStyle = 'width:1rem;height:1rem;flex-shrink:0;color:rgb(107 114 128)';

                        $headIcon = svg($m['icon'], '', ['style' => 'width:1.5rem;height:1.5rem;color:rgb(59 130 246)'])->toHtml();
                        $headOuter = 'margin-bottom:1rem;display:flex;align-items:center;gap:.75rem;border-radius:.75rem;border:1px solid #e5e7eb;background:linear-gradient(to right,#eff6ff,#ffffff);padding:1rem;';
                        $headIconBox = 'display:flex;flex-shrink:0;align-items:center;justify-content:center;width:2.75rem;height:2.75rem;border-radius:.5rem;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);border:1px solid #e5e7eb;';
                        $head = '<div style="' . $headOuter . '">'
                              . '<div style="' . $headIconBox . '">' . $headIcon . '</div>'
                              . '<div><div style="font-size:1rem;font-weight:600;color:#111827;">' . e($m['label']) . '</div>'
                              . '<div style="font-size:.75rem;color:#6b7280;">' . e($m['desc']) . '</div></div>'
                              . '</div>';

                        // 字段对比表
                        $rows = [];
                        $warn = svg('heroicon-m-exclamation-triangle', '', ['style' => 'width:.875rem;height:.875rem;color:rgb(245 158 11);display:inline-block;vertical-align:middle;margin-right:.25rem'])->toHtml();
                        $missing = '<span style="color:#d97706;">' . $warn . '未填写</span>';

                        if ($d === 'local') {
                            $rows[] = ['icon' => 'heroicon-o-folder', 'k' => '存储位置', 'v' => 'storage/app/public', 'mono' => true, 'raw' => false];
                            $rows[] = ['icon' => 'heroicon-o-globe-alt', 'k' => '访问路径', 'v' => '/storage/*', 'mono' => true, 'raw' => false];
                        } else {
                            $rows[] = ['icon' => 'heroicon-o-key', 'k' => 'Access Key', 'v' => filled($get('storage_access_key')) ? '已填写' : $missing, 'mono' => false, 'raw' => !filled($get('storage_access_key'))];
                            $rows[] = ['icon' => 'heroicon-o-lock-closed', 'k' => 'Secret', 'v' => filled($get('storage_secret_key')) ? '已新填' : '保留原值', 'mono' => false, 'raw' => false];
                            $rows[] = ['icon' => 'heroicon-o-archive-box', 'k' => 'Bucket', 'v' => $get('storage_bucket') ?: $missing, 'mono' => true, 'raw' => !filled($get('storage_bucket'))];
                            $rows[] = ['icon' => 'heroicon-o-map-pin', 'k' => 'Region', 'v' => $get('storage_region') ?: $missing, 'mono' => true, 'raw' => !filled($get('storage_region'))];
                            $rows[] = ['icon' => 'heroicon-o-link', 'k' => 'Endpoint', 'v' => $get('storage_endpoint') ?: $missing, 'mono' => true, 'raw' => !filled($get('storage_endpoint'))];
                            $rows[] = ['icon' => 'heroicon-o-globe-alt', 'k' => 'CDN 域名', 'v' => $get('storage_url') ?: '（未配置）', 'mono' => true, 'raw' => false];
                        }

                        $tableOuter = 'overflow:hidden;border-radius:.75rem;border:1px solid #e5e7eb;background:#fff;';
                        $table = '<div style="' . $tableOuter . '">';
                        foreach ($rows as $i => $r) {
                            $rowStyle = 'display:flex;align-items:center;padding:.625rem 1rem;'
                                . ($i === 0 ? '' : 'border-top:1px solid #f3f4f6;');
                            $monoCss = $r['mono'] ? 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;' : '';
                            $valStyle = $monoCss . 'font-size:.875rem;color:#111827;margin-left:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;';
                            $rowIcon = svg($r['icon'], '', ['style' => $rowIconStyle])->toHtml();
                            $valHtml = $r['raw'] ? $r['v'] : e($r['v']);
                            $table .= '<div style="' . $rowStyle . '">'
                                   . '<span style="margin-right:.5rem;">' . $rowIcon . '</span>'
                                   . '<span style="font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.025em;color:#6b7280;min-width:6.5rem;">' . e($r['k']) . '</span>'
                                   . '<span style="' . $valStyle . '">' . $valHtml . '</span>'
                                   . '</div>';
                        }
                        $table .= '</div>';

                        // 风险提示
                        $tipIconWarn = svg('heroicon-o-exclamation-triangle', '', ['style' => $iconStyle])->toHtml();
                        $tipIconInfo = svg('heroicon-o-information-circle', '', ['style' => $iconStyle])->toHtml();
                        $tipBaseStyle = 'margin-top:.75rem;display:flex;align-items:flex-start;gap:.5rem;border-radius:.5rem;padding:.5rem .75rem;font-size:.75rem;border-width:1px;border-style:solid;';
                        $tipWarnStyle = $tipBaseStyle . 'border-color:#fde68a;background:#fffbeb;color:#78350f;';
                        $tipInfoStyle = $tipBaseStyle . 'border-color:#bfdbfe;background:#eff6ff;color:#1e3a8a;';
                        $tip = $d === 'local'
                            ? '<div style="' . $tipWarnStyle . '">' . $tipIconWarn . '<span>本地存储不适合多机部署或大流量站点，生产环境建议切换到对象存储</span></div>'
                            : '<div style="' . $tipInfoStyle . '">' . $tipIconInfo . '<span>保存只写配置，请到下方点击 <b>连接测试</b> 验证服务可达，再用 <b>上传测试图片</b> 验证读写权限</span></div>';

                        return new \Illuminate\Support\HtmlString($head . $table . $tip);
                    }),
            ]);
    }

    private function stepRouting(): Step
    {
        return Step::make('用途分流')
            ->description('长期图、上传图、备份图分开存储')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Forms\Components\Placeholder::make('routing_tip')
                    ->label('')
                    ->content('默认存储用于长期生成图片，建议配置 R2。上传/下载临时图建议配置 OSS/COS 并设置生命周期；系统备份可配置 R2/OSS/COS，留空则只保留本地备份。'),

                Grid::make(2)->schema([
                    Forms\Components\Select::make('storage_temp_driver')
                        ->label('上传/下载临时存储')
                        ->options(collect(self::DRIVERS)->mapWithKeys(fn ($v, $k) => [$k => $v['label']])->all())
                        ->default('local')
                        ->helperText('留 local 表示复用默认长期存储')
                        ->live(),

                    Forms\Components\TextInput::make('storage_temp_ttl_days')
                        ->label('临时图保留天数')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(30)
                        ->default(7),
                ]),

                Grid::make(2)
                    ->visible(fn (Get $get) => in_array($get('storage_temp_driver'), ['oss', 'cos', 'r2'], true))
                    ->schema($this->profileFields('storage_temp', '临时')),

                Forms\Components\Select::make('storage_backup_driver')
                    ->label('系统备份远端存储')
                    ->options(collect(self::DRIVERS)->mapWithKeys(fn ($v, $k) => [$k => $v['label']])->all())
                    ->default('local')
                    ->helperText('配置后，系统备份 tar.gz 会在本地生成后同步到远端')
                    ->live(),

                Grid::make(2)
                    ->visible(fn (Get $get) => in_array($get('storage_backup_driver'), ['oss', 'cos', 'r2'], true))
                    ->schema($this->profileFields('storage_backup', '备份')),
            ]);
    }

    private function profileFields(string $prefix, string $labelPrefix): array
    {
        return [
            Forms\Components\TextInput::make("{$prefix}_access_key")
                ->label("{$labelPrefix} Access Key")
                ->prefixIcon('heroicon-o-key'),
            Forms\Components\TextInput::make("{$prefix}_secret_key")
                ->label("{$labelPrefix} Secret")
                ->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('留空则保留原值'),
            Forms\Components\TextInput::make("{$prefix}_bucket")
                ->label("{$labelPrefix} Bucket")
                ->prefixIcon('heroicon-o-archive-box'),
            Forms\Components\TextInput::make("{$prefix}_region")
                ->label("{$labelPrefix} Region")
                ->prefixIcon('heroicon-o-map-pin'),
            Forms\Components\TextInput::make("{$prefix}_endpoint")
                ->label("{$labelPrefix} Endpoint")
                ->url()
                ->prefixIcon('heroicon-o-link')
                ->columnSpanFull(),
            Forms\Components\TextInput::make("{$prefix}_url")
                ->label("{$labelPrefix} CDN 域名")
                ->url()
                ->prefixIcon('heroicon-o-globe-alt')
                ->columnSpanFull(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::FIELDS as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (in_array($key, ['storage_secret_key', 'storage_temp_secret_key', 'storage_backup_secret_key'], true) && !filled($value)) {
                continue;
            }
            SiteSetting::set($key, (string) ($value ?? ''), 'storage');
        }

        $this->refreshSummary();
        $this->refreshDiagnostics();

        Notification::make()
            ->title('配置已保存')
            ->body('请点击"连接测试"验证服务可用性。')
            ->success()
            ->send();
    }

    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('连接测试')
            ->icon('heroicon-o-signal')
            ->color('info')
            ->action(function () {
                $profiles = app(StorageProfileService::class);
                $checks = [
                    '长期生成图片' => StorageProfileService::PURPOSE_GENERATED,
                    '上传/下载临时图' => StorageProfileService::PURPOSE_UPLOAD,
                    '系统备份' => StorageProfileService::PURPOSE_BACKUP,
                ];
                $tested = [];

                foreach ($checks as $label => $purpose) {
                    if (!$profiles->isCloud($purpose)) {
                        continue;
                    }

                    $profile = $profiles->profileForPurpose($purpose);
                    if (isset($tested[$profile])) {
                        continue;
                    }

                    $tested[$profile] = ['label' => $label, 'purpose' => $purpose];
                }

                if (empty($tested)) {
                    Notification::make()
                        ->title('当前配置无需测试')
                        ->body('当前没有启用远端对象存储，本地存储不需要连接测试。')
                        ->warning()
                        ->send();
                    return;
                }
                try {
                    foreach ($tested as $test) {
                        $profiles->testWrite($test['purpose']);
                    }

                    $labels = array_column($tested, 'label');

                    SiteSetting::set('storage_last_test_at', now()->toDateTimeString(), 'storage');
                    SiteSetting::set('storage_last_test_ok', '1', 'storage');
                    SiteSetting::set('storage_last_test_msg', '连接正常：' . implode('、', $labels), 'storage');
                    $this->refreshSummary();
                    $this->refreshDiagnostics();
                    Notification::make()->title('连接测试成功')->body('已验证：' . implode('、', $labels))->success()->send();
                } catch (\Throwable $e) {
                    SiteSetting::set('storage_last_test_at', now()->toDateTimeString(), 'storage');
                    SiteSetting::set('storage_last_test_ok', '0', 'storage');
                    SiteSetting::set('storage_last_test_msg', $e->getMessage(), 'storage');
                    $this->refreshSummary();
                    $this->refreshDiagnostics();
                    Notification::make()->title('连接测试失败')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public function uploadProbeAction(): Action
    {
        return Action::make('uploadProbe')
            ->label('上传测试图片')
            ->icon('heroicon-o-photo')
            ->color('gray')
            ->action(function () {
                try {
                    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
                    $storage = app(ImageStorageService::class);
                    $purpose = StorageProfileService::PURPOSE_GENERATED;
                    $key = $storage->store($png, 'image/png', $purpose);
                    $url = $storage->url($key, $purpose);

                    Notification::make()
                        ->title('测试图片已上传')
                        ->body($url)
                        ->success()
                        ->persistent()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('上传失败')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
