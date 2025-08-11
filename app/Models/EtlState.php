<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtlState extends Model
{
    public $timestamps = true;
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public static function getValue(string $key, $default = null)
    {
        return optional(static::find($key))->value ?? $default;
    }

    public static function putValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string)$value]);
    }
}
