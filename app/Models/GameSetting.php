<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description', 'group'];

    /**
     * Get a setting value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue($key, $default = null)
    {
        $setting = \Illuminate\Support\Facades\Cache::remember('game_setting_' . $key, now()->addHours(24), function () use ($key) {
            return self::where('key', $key)->first();
        });
        
        if (!$setting) {
            return $default;
        }

        switch ($setting->type) {
            case 'integer':
                return (int) $setting->value;
            case 'float':
                return (float) $setting->value;
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }

    /**
     * Set a setting value.
     */
    public static function setValue($key, $value, $type = 'string', $group = 'general', $description = null)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'description' => $description
            ]
        );
        
        \Illuminate\Support\Facades\Cache::forget('game_setting_' . $key);
        
        return $setting;
    }
}
