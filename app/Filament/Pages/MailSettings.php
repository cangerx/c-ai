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
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = '邮件配置';
    protected static ?string $title = 'SMTP 邮件配置';
    protected static string | UnitEnum | null $navigationGroup = '系统';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.mail-settings';

    private const FIELDS = [
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $settings = [];
        foreach (self::FIELDS as $key) {
            if ($key === 'mail_password') {
                $settings[$key] = '';
                continue;
            }
            $settings[$key] = SiteSetting::get($key);
        }
        $this->form->fill($settings);
    }

    public function applyPreset(string $name): void
    {
        $presets = [
            'qq' => ['mail_host' => 'smtp.qq.com', 'mail_port' => '465', 'mail_encryption' => 'ssl'],
            '163' => ['mail_host' => 'smtp.163.com', 'mail_port' => '465', 'mail_encryption' => 'ssl'],
            'aliyun' => ['mail_host' => 'smtp.mxhichina.com', 'mail_port' => '465', 'mail_encryption' => 'ssl'],
            'outlook' => ['mail_host' => 'smtp.office365.com', 'mail_port' => '587', 'mail_encryption' => 'tls'],
            'gmail' => ['mail_host' => 'smtp.gmail.com', 'mail_port' => '587', 'mail_encryption' => 'tls'],
        ];

        if (isset($presets[$name])) {
            $this->data = array_merge($this->data, $presets[$name]);
            Notification::make()->title('已应用 ' . $name . ' 预设配置')->success()->duration(2000)->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('SMTP 服务器')->schema([
                    Forms\Components\TextInput::make('mail_host')
                        ->label('SMTP 服务器')
                        ->placeholder('smtp.qq.com')
                        ->helperText('邮件服务商提供的 SMTP 地址')
                        ->required(),
                    Forms\Components\TextInput::make('mail_port')
                        ->label('端口')
                        ->numeric()
                        ->placeholder('465')
                        ->helperText('SSL 通常为 465，TLS 通常为 587')
                        ->default('465'),
                    Forms\Components\Select::make('mail_encryption')
                        ->label('加密方式')
                        ->options([
                            'ssl' => 'SSL（推荐，端口 465）',
                            'tls' => 'TLS（端口 587）',
                            '' => '无加密',
                        ])
                        ->default('ssl'),
                ])->columns(3),

                Section::make('认证信息')->schema([
                    Forms\Components\TextInput::make('mail_username')
                        ->label('用户名（邮箱地址）')
                        ->placeholder('noreply@example.com')
                        ->helperText('用于 SMTP 认证的完整邮箱地址'),
                    Forms\Components\TextInput::make('mail_password')
                        ->label('密码 / 授权码')
                        ->password()
                        ->revealable()
                        ->placeholder('留空则不修改')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('QQ/163 邮箱请使用授权码（非登录密码），在邮箱设置中生成'),
                ])->columns(2),

                Section::make('发件人信息')->schema([
                    Forms\Components\TextInput::make('mail_from_address')
                        ->label('发件人地址')
                        ->placeholder('noreply@example.com')
                        ->helperText('通常与用户名一致'),
                    Forms\Components\TextInput::make('mail_from_name')
                        ->label('发件人名称')
                        ->placeholder('CANG-AI')
                        ->helperText('收件人看到的发件人显示名'),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::FIELDS as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($key === 'mail_password' && !filled($value)) {
                continue;
            }
            SiteSetting::set($key, (string) ($value ?? ''), 'mail');
        }

        Notification::make()->title('邮件配置已保存')->success()->send();
    }

    public function testMailAction(): Action
    {
        return Action::make('testMail')
            ->label('发送测试邮件')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('发送测试邮件')
            ->modalDescription(fn () => '将向当前管理员邮箱 ' . (auth()->user()->email ?? '') . ' 发送一封测试邮件，请先保存配置。')
            ->action(function () {
                $to = auth()->user()->email ?? null;
                if (!$to) {
                    Notification::make()->title('当前账号无邮箱')->danger()->send();
                    return;
                }

                $this->applyDbMailConfig();

                try {
                    $siteName = SiteSetting::get('site_name', 'CANG-AI');
                    Mail::send('emails.test', [
                        'subject' => '邮件测试',
                        'siteName' => $siteName,
                        'tagline' => '连通测试',
                    ], function ($msg) use ($to, $siteName) {
                        $msg->to($to)->subject("{$siteName} 邮件测试");
                    });
                    Notification::make()
                        ->title('测试邮件已发送')
                        ->body("已投递至 {$to}")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('发送失败')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function applyDbMailConfig(): void
    {
        $host = SiteSetting::get('mail_host');
        if (!$host) {
            return;
        }
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', SiteSetting::get('mail_port', 465));
        Config::set('mail.mailers.smtp.username', SiteSetting::get('mail_username'));
        Config::set('mail.mailers.smtp.password', SiteSetting::get('mail_password'));
        Config::set('mail.mailers.smtp.encryption', SiteSetting::get('mail_encryption', 'ssl'));
        Config::set('mail.from.address', SiteSetting::get('mail_from_address', SiteSetting::get('mail_username')));
        Config::set('mail.from.name', SiteSetting::get('mail_from_name', 'CANG-AI'));
    }
}
