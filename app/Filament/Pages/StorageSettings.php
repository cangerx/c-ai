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
use Filament\Schemas\Components\Section;
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
        'storage_mode',
        'storage_driver',
        'storage_access_key',
        'storage_secret_key',
        'storage_bucket',
        'storage_endpoint',
        'storage_region',
        'storage_url',
        'storage_temp_driver',
        'storage_temp_reuse_default',
        'storage_temp_access_key',
        'storage_temp_secret_key',
        'storage_temp_bucket',
        'storage_temp_endpoint',
        'storage_temp_region',
        'storage_temp_url',
        'storage_temp_ttl_days',
        'storage_backup_driver',
        'storage_backup_reuse_default',
        'storage_backup_access_key',
        'storage_backup_secret_key',
        'storage_backup_bucket',
        'storage_backup_endpoint',
        'storage_backup_region',
        'storage_backup_url',
        
        // 虚拟用途分配字段，仅做 UI 交互，统一流转保存
        'storage_assign_generated',
        'storage_assign_temp',
        'storage_assign_backup',
    ];
 
    private const MODES = [
        'local' => [
            'label' => '本地存储',
            'desc' => '不需要云账号，文件保存在服务器本机，适合测试、小站点或单机部署。',
            'icon' => 'heroicon-o-server-stack',
        ],
        'cloud' => [
            'label' => '智能云存储',
            'desc' => '只配置一套云存储。生成图和临时图自动复用，系统备份保留本地。',
            'icon' => 'heroicon-o-sparkles',
        ],
        'advanced' => [
            'label' => '高级分流',
            'desc' => '分别配置生成图、临时图、系统备份，适合多桶、生命周期和独立权限场景。',
            'icon' => 'heroicon-o-adjustments-horizontal',
        ],
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
            if (in_array($key, ['storage_assign_generated', 'storage_assign_temp', 'storage_assign_backup'], true)) {
                $settings[$key] = '';
                continue;
            }
            
            $val = SiteSetting::get($key);
            if (in_array($key, ['storage_temp_reuse_default', 'storage_backup_reuse_default'], true)) {
                $val = ($val === null || $val === '') ? true : filter_var($val, FILTER_VALIDATE_BOOLEAN); // 默认开启密钥复用
            }
            $settings[$key] = $val;
        }
        
        if (!$settings['storage_driver']) {
            $settings['storage_driver'] = 'local';
        }
        if (!$settings['storage_temp_driver']) {
            $settings['storage_temp_driver'] = 'default';
        }
        if (!$settings['storage_backup_driver']) {
            $settings['storage_backup_driver'] = 'local';
        }
        if (!$settings['storage_mode']) {
            $settings['storage_mode'] = $this->inferMode($settings);
        }

        // 绑定解析特定驱动的主媒介参数
        $driver = $settings['storage_driver'];
        if (in_array($driver, ['oss', 'cos', 'r2'], true)) {
            foreach (['access_key', 'bucket', 'region', 'endpoint', 'url'] as $field) {
                $specVal = SiteSetting::get("storage_{$driver}_{$field}");
                if ($specVal !== null && $specVal !== '') {
                    $settings["storage_{$field}"] = $specVal;
                }
            }
        }

        // 绑定解析特定驱动的临时存储参数
        $tempDriver = $settings['storage_temp_driver'];
        if ($tempDriver === 'default') {
            $tempDriver = $driver;
        }
        if (in_array($tempDriver, ['oss', 'cos', 'r2'], true)) {
            foreach (['access_key', 'bucket', 'region', 'endpoint', 'url'] as $field) {
                $specVal = SiteSetting::get("storage_temp_{$tempDriver}_{$field}");
                if ($specVal !== null && $specVal !== '') {
                    $settings["storage_temp_{$field}"] = $specVal;
                }
            }
        }

        // 绑定解析特定驱动的备份存储参数
        $backupDriver = $settings['storage_backup_driver'];
        if ($backupDriver === 'default') {
            $backupDriver = $driver;
        }
        if (in_array($backupDriver, ['oss', 'cos', 'r2'], true)) {
            foreach (['access_key', 'bucket', 'region', 'endpoint', 'url'] as $field) {
                $specVal = SiteSetting::get("storage_backup_{$backupDriver}_{$field}");
                if ($specVal !== null && $specVal !== '') {
                    $settings["storage_backup_{$field}"] = $specVal;
                }
            }
        }

        // 核心解耦点：初始化用途分配虚拟字段
        $settings['storage_assign_generated'] = $settings['storage_driver'] === 'local' ? 'local' : 'cloud';
        $settings['storage_assign_temp'] = $settings['storage_temp_driver'] === 'default' ? 'default' : 'independent';
        $settings['storage_assign_backup'] = in_array($settings['storage_backup_driver'], ['default', 'local'], true)
            ? $settings['storage_backup_driver']
            : 'independent';
 
        $this->form->fill($settings);
        $this->refreshSummary();
        $this->refreshDiagnostics();
    }
 
    private function refreshSummary(): void
    {
        $driver = SiteSetting::get('storage_driver', 'local');
        $mode = SiteSetting::get('storage_mode', '');
        if (!$mode) {
            $mode = $this->inferMode([
                'storage_driver' => $driver,
                'storage_temp_driver' => SiteSetting::get('storage_temp_driver', 'default'),
                'storage_backup_driver' => SiteSetting::get('storage_backup_driver', 'local'),
            ]);
        }
        $meta   = self::DRIVERS[$driver] ?? self::DRIVERS['local'];
        $this->summary = [
            'mode'          => $mode,
            'mode_label'    => self::MODES[$mode]['label'] ?? self::MODES['cloud']['label'],
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
        return $schema
            ->schema([
                // 1. 业务用途解耦分配面板 (SaaS Decoupled Purpose Assignment)
                Section::make('存储用途绑定中心')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->description('解耦设计：在此单独指定不同的业务用途对应流向哪些存储媒介。')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\Select::make('storage_assign_generated')
                                ->label('长期生成图存储去向')
                                ->options([
                                    'local' => '本地服务器磁盘',
                                    'cloud' => '远端云存储 [主媒介 A]',
                                ])
                                ->required()
                                ->live()
                                ->native(false),
 
                            Forms\Components\Select::make('storage_assign_temp')
                                ->label('临时参考图存储去向')
                                ->options([
                                    'default'     => '复用主云存储 [主媒介 A]',
                                    'independent' => '独立临时存储 [临时媒介 B]',
                                ])
                                ->required()
                                ->live()
                                ->native(false)
                                ->visible(fn (Get $get) => $get('storage_assign_generated') === 'cloud'),
 
                            Forms\Components\Select::make('storage_assign_backup')
                                ->label('系统自动备份存储去向')
                                ->options([
                                    'local'       => '保留本地服务器备份',
                                    'default'     => '复用主云存储 [主媒介 A]',
                                    'independent' => '独立备份存储 [备份媒介 C]',
                                ])
                                ->required()
                                ->live()
                                ->native(false)
                                ->visible(fn (Get $get) => $get('storage_assign_generated') === 'cloud'),
                        ]),
 
                        Forms\Components\TextInput::make('storage_temp_ttl_days')
                            ->label('临时参考图保留天数')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(7)
                            ->visible(fn (Get $get) => $get('storage_assign_generated') === 'cloud')
                            ->helperText('上传的垫图和下载缓存会在指定天数后在后台自动被生命周期模块清理'),
                    ]),
 
                // 2. 存储媒介 A 配置 (Default Long-Term Pool)
                Section::make('[主媒介 A] 长期存储配置')
                    ->icon('heroicon-o-key')
                    ->description('对应“长期生成图”用途的云端存储参数')
                    ->visible(fn (Get $get) => $get('storage_assign_generated') === 'cloud')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Radio::make('storage_driver')
                            ->label('主媒介云服务商')
                            ->options($this->cloudDriverOptions())
                            ->descriptions($this->cloudDriverDescriptions())
                            ->required(fn (Get $get) => $get('storage_assign_generated') === 'cloud')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (in_array($state, ['oss', 'cos', 'r2'], true)) {
                                    $set('storage_access_key', SiteSetting::get("storage_{$state}_access_key") ?: SiteSetting::get('storage_access_key', ''));
                                    $set('storage_secret_key', '');
                                    $set('storage_bucket', SiteSetting::get("storage_{$state}_bucket") ?: SiteSetting::get('storage_bucket', ''));
                                    $set('storage_region', SiteSetting::get("storage_{$state}_region") ?: SiteSetting::get('storage_region', ''));
                                    $set('storage_endpoint', SiteSetting::get("storage_{$state}_endpoint") ?: SiteSetting::get('storage_endpoint', ''));
                                    $set('storage_url', SiteSetting::get("storage_{$state}_url") ?: SiteSetting::get('storage_url', ''));
                                }
                            })
                            ->extraAttributes(['class' => 'storage-driver-radio']),
 
                        Forms\Components\Placeholder::make('driver_doc')
                            ->label('')
                            ->content(function (Get $get) {
                                $d = $get('storage_driver');
                                if (!isset(self::DRIVERS[$d]) || $d === 'local') {
                                    return '';
                                }
                                $m = self::DRIVERS[$d];
                                $tipMap = [
                                    'oss' => '阿里云提供中国大陆极速直传，建议创建 RAM 子账号授权 OSS 写入权限。',
                                    'cos' => '腾讯云 COS 支持完整的 S3 API，适合搭配腾讯云 CDN 回源。',
                                    'r2'  => 'Cloudflare R2 完全免除外网下行流量费，适合出海或全球多点 CDN 业务。',
                                ];
                                $bulb = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;margin-top:.125rem"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>';
                                $book = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:.875rem;height:.875rem;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>';
                                $arrow = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.75rem;height:.75rem;flex-shrink:0;display:inline-block;vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>';
                                $boxStyle = 'border-radius:.5rem;border:1px solid #bfdbfe;background:rgba(239,246,255,.6);padding:.625rem 1rem;font-size:.875rem;color:#1e3a8a;';
                                $rowStyle = 'display:flex;align-items:flex-start;gap:.5rem;';
                                $linkRowStyle = 'margin-top:.375rem;';
                                $linkStyle = 'display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;font-weight:500;color:#2563eb;text-decoration:none;';
                                return new \Illuminate\Support\HtmlString(
                                    '<div style="' . $boxStyle . '">'
                                    . '<div style="' . $rowStyle . '">' . $bulb . '<span>' . e($tipMap[$d] ?? '') . '</span></div>'
                                    . '<div style="' . $linkRowStyle . '"><a href="' . e($m['doc']) . '" target="_blank" rel="noopener" style="' . $linkStyle . '">' . $book . '<span>' . e($m['doc_text']) . '</span>' . $arrow . '</a></div>'
                                    . '</div>'
                                );
                            }),
 
                        Forms\Components\TextInput::make('storage_access_key')
                            ->label('Access Key ID')
                            ->required(fn (Get $get) => $get('storage_assign_generated') === 'cloud' && $get('storage_driver') !== 'local')
                            ->placeholder('主云账户秘钥 ID')
                            ->prefixIcon('heroicon-o-key'),
 
                        Forms\Components\TextInput::make('storage_secret_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->placeholder('留空则不修改')
                            ->prefixIcon('heroicon-o-lock-closed')
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (Get $get) => $get('storage_assign_generated') === 'cloud'
                                && in_array($get('storage_driver'), ['oss', 'cos', 'r2'], true)
                                && !filled(SiteSetting::get('storage_secret_key', ''))),
 
                        Forms\Components\TextInput::make('storage_bucket')
                            ->label('Bucket 名称')
                            ->required(fn (Get $get) => $get('storage_assign_generated') === 'cloud' && $get('storage_driver') !== 'local')
                            ->prefixIcon('heroicon-o-archive-box'),
 
                        Forms\Components\TextInput::make('storage_region')
                            ->label('Region')
                            ->required(fn (Get $get) => $get('storage_assign_generated') === 'cloud' && $get('storage_driver') !== 'local')
                            ->placeholder('oss-cn-hangzhou / auto')
                            ->prefixIcon('heroicon-o-map-pin'),
 
                        Forms\Components\TextInput::make('storage_endpoint')
                            ->label('Endpoint')
                            ->required(fn (Get $get) => $get('storage_assign_generated') === 'cloud' && $get('storage_driver') !== 'local')
                            ->url()
                            ->prefixIcon('heroicon-o-link')
                            ->columnSpanFull(),
 
                        Forms\Components\TextInput::make('storage_url')
                            ->label('自定义 CDN 加速域名')
                            ->url()
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->columnSpanFull(),
                    ])->columns(2),
 
                // 3. 存储媒介 B 配置 (Independent Temp Cloud Pool)
                Section::make('[临时媒介 B] 独立临时云存储')
                    ->icon('heroicon-o-key')
                    ->description('对应“上传/下载临时图”用途的云端存储参数')
                    ->visible(fn (Get $get) => $get('storage_assign_generated') === 'cloud' && $get('storage_assign_temp') === 'independent')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Radio::make('storage_temp_driver')
                            ->label('临时媒介服务商')
                            ->options($this->cloudDriverOptions())
                            ->required(fn (Get $get) => $get('storage_assign_temp') === 'independent')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (in_array($state, ['oss', 'cos', 'r2'], true)) {
                                    $set('storage_temp_access_key', SiteSetting::get("storage_temp_{$state}_access_key") ?: SiteSetting::get('storage_temp_access_key', ''));
                                    $set('storage_temp_secret_key', '');
                                    $set('storage_temp_bucket', SiteSetting::get("storage_temp_{$state}_bucket") ?: SiteSetting::get('storage_temp_bucket', ''));
                                    $set('storage_temp_region', SiteSetting::get("storage_temp_{$state}_region") ?: SiteSetting::get('storage_temp_region', ''));
                                    $set('storage_temp_endpoint', SiteSetting::get("storage_temp_{$state}_endpoint") ?: SiteSetting::get('storage_temp_endpoint', ''));
                                    $set('storage_temp_url', SiteSetting::get("storage_temp_{$state}_url") ?: SiteSetting::get('storage_temp_url', ''));
                                }
                            })
                            ->extraAttributes(['class' => 'storage-driver-radio']),
 
                        Forms\Components\Toggle::make('storage_temp_reuse_default')
                            ->label('一键复用 [主媒介 A] 的账户密钥凭证')
                            ->live()
                            ->default(true)
                            ->helperText('勾选后自动隐去密钥输入，系统保存时自动克隆主存储 Access Key 与 Secret，省除重复输入。')
                            ->columnSpanFull(),
 
                        Forms\Components\TextInput::make('storage_temp_access_key')
                            ->label('临时存储 Access Key')
                            ->prefixIcon('heroicon-o-key')
                            ->required(fn (Get $get) => !$get('storage_temp_reuse_default'))
                            ->visible(fn (Get $get) => !$get('storage_temp_reuse_default')),
 
                        Forms\Components\TextInput::make('storage_temp_secret_key')
                            ->label('临时存储 Secret')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder('留空则保留历史密钥')
                            ->required(fn (Get $get) => !$get('storage_temp_reuse_default') && !$this->hasStoredSecret('storage_temp'))
                            ->visible(fn (Get $get) => !$get('storage_temp_reuse_default')),
 
                        Forms\Components\TextInput::make('storage_temp_bucket')
                            ->label('临时 Bucket 名称')
                            ->required()
                            ->prefixIcon('heroicon-o-archive-box'),
 
                        Forms\Components\TextInput::make('storage_temp_region')
                            ->label('临时 Region')
                            ->required()
                            ->prefixIcon('heroicon-o-map-pin'),
 
                        Forms\Components\TextInput::make('storage_temp_endpoint')
                            ->label('临时 Endpoint')
                            ->url()
                            ->required()
                            ->prefixIcon('heroicon-o-link')
                            ->columnSpanFull(),
 
                        Forms\Components\TextInput::make('storage_temp_url')
                            ->label('临时 CDN 加速域名')
                            ->url()
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->columnSpanFull(),
                    ])->columns(2),
 
                // 4. 存储媒介 C 配置 (Independent Backup Cloud Pool)
                Section::make('[备份媒介 C] 独立远端备份存储')
                    ->icon('heroicon-o-key')
                    ->description('对应“系统自动备份”用途的云端存储参数')
                    ->visible(fn (Get $get) => $get('storage_assign_generated') === 'cloud' && $get('storage_assign_backup') === 'independent')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Radio::make('storage_backup_driver')
                            ->label('备份媒介服务商')
                            ->options($this->cloudDriverOptions())
                            ->required(fn (Get $get) => $get('storage_assign_backup') === 'independent')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (in_array($state, ['oss', 'cos', 'r2'], true)) {
                                    $set('storage_backup_access_key', SiteSetting::get("storage_backup_{$state}_access_key") ?: SiteSetting::get('storage_backup_access_key', ''));
                                    $set('storage_backup_secret_key', '');
                                    $set('storage_backup_bucket', SiteSetting::get("storage_backup_{$state}_bucket") ?: SiteSetting::get('storage_backup_bucket', ''));
                                    $set('storage_backup_region', SiteSetting::get("storage_backup_{$state}_region") ?: SiteSetting::get('storage_backup_region', ''));
                                    $set('storage_backup_endpoint', SiteSetting::get("storage_backup_{$state}_endpoint") ?: SiteSetting::get('storage_backup_endpoint', ''));
                                    $set('storage_backup_url', SiteSetting::get("storage_backup_{$state}_url") ?: SiteSetting::get('storage_backup_url', ''));
                                }
                            })
                            ->extraAttributes(['class' => 'storage-driver-radio']),
 
                        Forms\Components\Toggle::make('storage_backup_reuse_default')
                            ->label('一键复用 [主媒介 A] 的账户密钥凭证')
                            ->live()
                            ->default(true)
                            ->helperText('勾选后自动隐去密钥输入，直接克隆主存储密钥。')
                            ->columnSpanFull(),
 
                        Forms\Components\TextInput::make('storage_backup_access_key')
                            ->label('备份存储 Access Key')
                            ->prefixIcon('heroicon-o-key')
                            ->required(fn (Get $get) => !$get('storage_backup_reuse_default'))
                            ->visible(fn (Get $get) => !$get('storage_backup_reuse_default')),
 
                        Forms\Components\TextInput::make('storage_backup_secret_key')
                            ->label('备份存储 Secret')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder('留空则保留历史密钥')
                            ->required(fn (Get $get) => !$get('storage_backup_reuse_default') && !$this->hasStoredSecret('storage_backup'))
                            ->visible(fn (Get $get) => !$get('storage_backup_reuse_default')),
 
                        Forms\Components\TextInput::make('storage_backup_bucket')
                            ->label('备份 Bucket 名称')
                            ->required()
                            ->prefixIcon('heroicon-o-archive-box'),
 
                        Forms\Components\TextInput::make('storage_backup_region')
                            ->label('备份 Region')
                            ->required()
                            ->prefixIcon('heroicon-o-map-pin'),
 
                        Forms\Components\TextInput::make('storage_backup_endpoint')
                            ->label('备份 Endpoint')
                            ->url()
                            ->required()
                            ->prefixIcon('heroicon-o-link')
                            ->columnSpanFull(),
 
                        Forms\Components\TextInput::make('storage_backup_url')
                            ->label('备份 CDN 加速域名')
                            ->url()
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }
 
    private function profileFields(string $prefix, string $labelPrefix): array
    {
        return [];
    }
 
    private function routingDriverOptions(string $purpose): array
    {
        return [];
    }
 
    private function cloudDriverOptions(): array
    {
        return collect(self::DRIVERS)
            ->except('local')
            ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
            ->all();
    }
 
    private function cloudDriverDescriptions(): array
    {
        return collect(self::DRIVERS)
            ->except('local')
            ->mapWithKeys(fn ($v, $k) => [$k => $v['desc']])
            ->all();
    }
 
    private function applyPurposePreset(Set $set, Get $get, string $targetPrefix, string $driver): void
    {
        if (in_array($driver, ['default', 'local'], true) || !isset(self::DRIVERS[$driver])) {
            return;
        }
 
        $defaultDriver = (string) ($get('storage_driver') ?: 'local');
        $defaultFields = [
            'access_key' => (string) $get('storage_access_key'),
            'bucket' => (string) $get('storage_bucket'),
            'region' => (string) $get('storage_region'),
            'endpoint' => (string) $get('storage_endpoint'),
            'url' => (string) $get('storage_url'),
        ];
        $sampleFields = [
            'bucket' => self::SAMPLES[$driver]['storage_bucket'] ?? '',
            'region' => self::SAMPLES[$driver]['storage_region'] ?? '',
            'endpoint' => self::SAMPLES[$driver]['storage_endpoint'] ?? '',
        ];
 
        foreach (['access_key', 'secret_key', 'bucket', 'region', 'endpoint', 'url'] as $field) {
            if ($field === 'secret_key') {
                continue;
            }
 
            $targetKey = "{$targetPrefix}_{$field}";
            if (filled($get($targetKey))) {
                continue;
            }
 
            $value = '';
            if ($driver === $defaultDriver) {
                $value = $defaultFields[$field] ?? '';
            } elseif (isset($sampleFields[$field])) {
                $value = $sampleFields[$field];
            }
 
            if ($value !== '') {
                $set($targetKey, $value);
            }
        }
    }
 
    private function hasStoredSecret(string $prefix): bool
    {
        return filled(SiteSetting::get("{$prefix}_secret_key", ''));
    }
 
    private function inferMode(array $settings): string
    {
        $driver = (string) ($settings['storage_driver'] ?? 'local');
        $tempDriver = (string) ($settings['storage_temp_driver'] ?? 'default');
        $backupDriver = (string) ($settings['storage_backup_driver'] ?? 'local');
 
        if ($driver === 'local') {
            return 'local';
        }
 
        if ($tempDriver === 'default' && $backupDriver === 'local') {
            return 'cloud';
        }
 
        return 'advanced';
    }
 
    private function applyModeDefaults(array &$data): void
    {
        $mode = (string) ($data['storage_mode'] ?? 'cloud');
 
        if ($mode === 'local') {
            $data['storage_driver'] = 'local';
            $data['storage_temp_driver'] = 'default';
            $data['storage_backup_driver'] = 'local';
            return;
        }
 
        if ($mode === 'cloud') {
            $data['storage_temp_driver'] = 'default';
            $data['storage_backup_driver'] = 'local';
        }
    }
 
    public function save(): void
    {
        $data = $this->form->getState();
 
        // 核心解耦转换：将虚拟用途绑定字段翻译还原为系统底层驱动值
        if ($data['storage_assign_generated'] === 'local') {
            $data['storage_driver'] = 'local';
            $data['storage_mode'] = 'local';
            $data['storage_temp_driver'] = 'default';
            $data['storage_backup_driver'] = 'local';
        } else {
            // 云模式保护
            if (($data['storage_driver'] ?? 'local') === 'local') {
                $data['storage_driver'] = 'oss';
            }
 
            // 临时参考图分流转换
            if ($data['storage_assign_temp'] === 'default') {
                $data['storage_temp_driver'] = 'default';
            } else {
                if (($data['storage_temp_driver'] ?? 'default') === 'default') {
                    $data['storage_temp_driver'] = 'oss';
                }
            }
 
            // 备份远端分流转换
            if ($data['storage_assign_backup'] === 'local') {
                $data['storage_backup_driver'] = 'local';
            } elseif ($data['storage_assign_backup'] === 'default') {
                $data['storage_backup_driver'] = 'default';
            } else {
                if (in_array($data['storage_backup_driver'] ?? 'local', ['local', 'default'], true)) {
                    $data['storage_backup_driver'] = 'oss';
                }
            }
 
            // 推导系统的最终 storage_mode 状态列
            if ($data['storage_temp_driver'] === 'default' && $data['storage_backup_driver'] === 'local') {
                $data['storage_mode'] = 'cloud';
            } else {
                $data['storage_mode'] = 'advanced';
            }
        }
 
        // 智能凭证同步克隆逻辑 (Smart credentials syncing)
        if (($data['storage_mode'] ?? 'cloud') === 'advanced') {
            // 临时图密钥同步
            if (!empty($data['storage_temp_reuse_default'])) {
                $data['storage_temp_access_key'] = $data['storage_access_key'] ?? '';
                $secret = ($data['storage_secret_key'] ?? '') ?: SiteSetting::get('storage_secret_key', '');
                if (filled($secret)) {
                    $data['storage_temp_secret_key'] = $secret;
                }
            }
            // 备份远端密钥同步
            if (!empty($data['storage_backup_reuse_default'])) {
                $data['storage_backup_access_key'] = $data['storage_access_key'] ?? '';
                $secret = ($data['storage_secret_key'] ?? '') ?: SiteSetting::get('storage_secret_key', '');
                if (filled($secret)) {
                    $data['storage_backup_secret_key'] = $secret;
                }
            }
        }
 
        // 保存常规与同步后的数据字段
        foreach (self::FIELDS as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            // 虚拟用途字段不用入库
            if (in_array($key, ['storage_assign_generated', 'storage_assign_temp', 'storage_assign_backup'], true)) {
                continue;
            }
 
            $value = $data[$key];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (in_array($key, ['storage_secret_key', 'storage_temp_secret_key', 'storage_backup_secret_key'], true) && !filled($value)) {
                continue;
            }

            // A: 保存主驱动的特定值
            $driver = $data['storage_driver'];
            if (in_array($driver, ['oss', 'cos', 'r2'], true) && str_starts_with($key, 'storage_') && !str_starts_with($key, 'storage_temp_') && !str_starts_with($key, 'storage_backup_')) {
                $field = substr($key, strlen('storage_'));
                if (in_array($field, ['access_key', 'secret_key', 'bucket', 'region', 'endpoint', 'url'], true)) {
                    SiteSetting::set("storage_{$driver}_{$field}", (string) ($value ?? ''), 'storage');
                }
            }

            // B: 保存临时图驱动的特定值
            $tempDriver = $data['storage_temp_driver'];
            if ($tempDriver === 'default') {
                $tempDriver = $driver;
            }
            if (in_array($tempDriver, ['oss', 'cos', 'r2'], true) && str_starts_with($key, 'storage_temp_') && $key !== 'storage_temp_driver' && $key !== 'storage_temp_reuse_default' && $key !== 'storage_temp_ttl_days') {
                $field = substr($key, strlen('storage_temp_'));
                if (in_array($field, ['access_key', 'secret_key', 'bucket', 'region', 'endpoint', 'url'], true)) {
                    SiteSetting::set("storage_temp_{$tempDriver}_{$field}", (string) ($value ?? ''), 'storage');
                }
            }

            // C: 保存备份驱动的特定值
            $backupDriver = $data['storage_backup_driver'];
            if ($backupDriver === 'default') {
                $backupDriver = $driver;
            }
            if (in_array($backupDriver, ['oss', 'cos', 'r2'], true) && str_starts_with($key, 'storage_backup_') && $key !== 'storage_backup_driver' && $key !== 'storage_backup_reuse_default') {
                $field = substr($key, strlen('storage_backup_'));
                if (in_array($field, ['access_key', 'secret_key', 'bucket', 'region', 'endpoint', 'url'], true)) {
                    SiteSetting::set("storage_backup_{$backupDriver}_{$field}", (string) ($value ?? ''), 'storage');
                }
            }
 
            SiteSetting::set($key, (string) ($value ?? ''), 'storage');
        }
 
        // 补充将推导的 storage_mode 入库
        SiteSetting::set('storage_mode', $data['storage_mode'], 'storage');
 
        $this->refreshSummary();
        $this->refreshDiagnostics();
 
        Notification::make()
            ->title('配置保存成功')
            ->body('存储用途绑定与凭证分流已写库，请在右侧操作中心执行“可用性连接测试”。')
            ->success()
            ->send();
    }
 
    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('连接可用性测试')
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
            ->label('完整上传读写测试')
            ->icon('heroicon-o-photo')
            ->color('gray')
            ->action(function () {
                try {
                    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
                    $storage = app(ImageStorageService::class);
                    $purposes = [
                        '长期生成图' => StorageProfileService::PURPOSE_GENERATED,
                        '上传/下载临时图' => StorageProfileService::PURPOSE_UPLOAD,
                    ];
                    $urls = [];
 
                    foreach ($purposes as $label => $purpose) {
                        $key = $storage->store($png, 'image/png', $purpose);
                        $urls[] = $label . ': ' . $storage->url($key, $purpose);
                    }
 
                    Notification::make()
                        ->title('上传测试通过')
                        ->body(implode("\n", $urls))
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
