<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

  /**
   * @var list<string>
   */
    private const ENCRYPTED_KEYS = [
        'trading.ai.openai.api_key',
        'trading.ai.anthropic.api_key',
        'trading.ai.gemini.api_key',
        'trading.telegram.bot_token',
    ];

    public static function isEncrypted(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::query()->find($key);
        if ($record === null || $record->value === null || $record->value === '') {
            return $default;
        }

        if (self::isEncrypted($key)) {
            try {
                return Crypt::decryptString($record->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $record->value;
    }

    public static function setValue(string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            static::query()->where('key', $key)->delete();

            return;
        }

        $stored = self::isEncrypted($key)
            ? Crypt::encryptString((string) $value)
            : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored]
        );
    }

    public static function hasValue(string $key): bool
    {
        $value = self::getValue($key);

        return $value !== null && $value !== '';
    }
}
