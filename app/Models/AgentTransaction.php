<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTransaction extends Model
{
    protected $fillable = [
        'user_id', 'type', 'credits', 'balance',
        'credits_after', 'balance_after', 'description',
    ];
}
