<?php

namespace App\Enums;

enum SignalAction: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';
    case Wait = 'WAIT';

    public static function tryFromMixed(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper(trim($value)));
    }
}
