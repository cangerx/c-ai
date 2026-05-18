<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'nickname',
        'invite_code',
        'parent_id',
        'github_id',
        'avatar',
        'wechat_openid',
        'wechat_unionid',
        'agent_level_id',
        'role',
        'status',
        'credits',
        'balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:2',
            'credits' => 'integer',
            'commission_balance' => 'decimal:2',
            'commission_credits' => 'integer',
            'total_consumed_credits' => 'integer',
            'is_distributor' => 'boolean',
            'total_recharged' => 'decimal:2',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->role === 'admin' && $this->status === 'active';
        }
        if ($panel->getId() === 'agent') {
            return in_array($this->role, ['agent', 'admin']) && $this->status === 'active';
        }
        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function agentLevel(): BelongsTo
    {
        return $this->belongsTo(AgentLevel::class);
    }

    public function agentSite()
    {
        return $this->hasOne(AgentSite::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function ensureInviteCode(): string
    {
        if (!$this->invite_code) {
            $this->invite_code = strtoupper(Str::random(8));
            $this->saveQuietly();
        }
        return $this->invite_code;
    }
}
