<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTransaction extends Model
{
    protected $fillable = [
        'user_id', 'type', 'credits', 'balance',
        'credits_after', 'balance_after', 'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
