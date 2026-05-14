<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['content', 'url', 'enabled', 'sort'];

    protected $casts = ['enabled' => 'boolean'];
}
