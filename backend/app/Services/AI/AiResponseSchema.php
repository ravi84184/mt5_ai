<?php

namespace App\Services\AI;

class AiResponseSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function entry(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'symbol' => ['type' => 'string'],
                'action' => ['type' => 'string', 'enum' => ['BUY', 'SELL', 'WAIT']],
                'confidence' => ['type' => 'integer'],
                'entry_price' => ['type' => 'number'],
                'stop_loss' => ['type' => 'number'],
                'take_profit' => ['type' => 'number'],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['symbol', 'action', 'confidence', 'entry_price', 'stop_loss', 'take_profit', 'reason'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function position(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['HOLD', 'CLOSE', 'MOVE_SL', 'MOVE_TO_BREAKEVEN', 'PARTIAL_CLOSE']],
                'new_sl' => ['type' => 'number'],
                'close_volume' => ['type' => 'number'],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['action', 'new_sl', 'close_volume', 'reason'],
            'additionalProperties' => false,
        ];
    }
}
