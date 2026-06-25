<?php

namespace App\Enums;

enum TradeManagementAction: string
{
    case Hold = 'HOLD';
    case Close = 'CLOSE';
    case MoveSl = 'MOVE_SL';
    case MoveToBreakeven = 'MOVE_TO_BREAKEVEN';
    case PartialClose = 'PARTIAL_CLOSE';

    public static function tryFromMixed(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper(trim($value)));
    }
}
