<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LoginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = '登录设置';
    protected static ?string $title = '登录设置';
    protected static string | UnitEnum | null $navigationGroup = '系统';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.login-settings';

    private const FIELDS = [
        'login_github_enabled',
        'github_client_id',
        'github_client_secret',
        'login_wechat_enabled',
        'wechat_appid',
        'wechat_secret',
        'login_google_enabled',
        'google_client_id',
        'google_client_secret',
    ];

    private const SECRET_FIELDS = ['github_client_secret', 'wechat_secret', 'google_client_secret'];
    private const TOGGLE_FIELDS = ['login_github_enabled', 'login_wechat_enabled', 'login_google_enabled'];

    public ?array $data = [];

    public function mount(): void
    {
        $settings = [];
        foreach (self::FIELDS as $key) {
            if (in_array($key, self::SECRET_FIELDS)) {
                $settings[$key] = '';
                continue;
            }
            $value = SiteSetting::get($key);
            if (in_array($key, self::TOGGLE_FIELDS)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $settings[$key] = $value;
        }
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        $baseUrl = request()->getSchemeAndHttpHost();

        return $schema
            ->schema([
                Section::make('GitHub 登录')
                    ->icon('heroicon-o-code-bracket')
                    ->description('使用 GitHub 账号快速登录')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Toggle::make('login_github_enabled')
                            ->label('启用')
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('github_client_id')
                            ->label('Client ID')
                            ->required(fn (Get $get) => $get('login_github_enabled'))
                            ->visible(fn (Get $get) => $get('login_github_enabled'))
                            ->placeholder('GitHub OAuth App Client ID'),
                        Forms\Components\TextInput::make('github_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get) => $get('login_github_enabled'))
                            ->placeholder('留空则保留原值')
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\Placeholder::make('github_callback')
                            ->label('回调地址')
                            ->visible(fn (Get $get) => $get('login_github_enabled'))
                            ->content($baseUrl . '/api/auth/github/callback')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('微信扫码登录')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->description('使用微信扫码登录（需微信开放平台网站应用）')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Toggle::make('login_wechat_enabled')
                            ->label('启用')
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('wechat_appid')
                            ->label('AppID')
                            ->required(fn (Get $get) => $get('login_wechat_enabled'))
                            ->visible(fn (Get $get) => $get('login_wechat_enabled'))
                            ->placeholder('微信开放平台 AppID'),
                        Forms\Components\TextInput::make('wechat_secret')
                            ->label('AppSecret')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get) => $get('login_wechat_enabled'))
                            ->placeholder('留空则保留原值')
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\Placeholder::make('wechat_callback')
                            ->label('回调地址')
                            ->visible(fn (Get $get) => $get('login_wechat_enabled'))
                            ->content($baseUrl . '/api/auth/wechat/callback')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Google 登录')
                    ->icon('heroicon-o-globe-alt')
                    ->description('使用 Google 账号登录（需 Google Cloud Console OAuth 2.0）')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('login_google_enabled')
                            ->label('启用')
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('google_client_id')
                            ->label('Client ID')
                            ->required(fn (Get $get) => $get('login_google_enabled'))
                            ->visible(fn (Get $get) => $get('login_google_enabled'))
                            ->placeholder('Google OAuth Client ID'),
                        Forms\Components\TextInput::make('google_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get) => $get('login_google_enabled'))
                            ->placeholder('留空则保留原值')
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\Placeholder::make('google_callback')
                            ->label('回调地址')
                            ->visible(fn (Get $get) => $get('login_google_enabled'))
                            ->content($baseUrl . '/api/auth/google/callback')
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::FIELDS as $key) {
            if (in_array($key, self::SECRET_FIELDS) && (!array_key_exists($key, $data) || !filled($data[$key] ?? null))) {
                continue;
            }

            $value = $data[$key] ?? null;

            if (in_array($key, self::TOGGLE_FIELDS)) {
                $value = $value ? '1' : '0';
            }

            SiteSetting::set($key, (string) ($value ?? ''), 'login');
        }

        Notification::make()->title('登录设置已保存')->success()->send();
    }
}
