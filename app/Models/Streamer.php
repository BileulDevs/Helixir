<?php

namespace App\Models;

use Database\Factories\StreamerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Streamer extends Model
{
    /** @use HasFactory<StreamerFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'login', 'display_name'];
}
