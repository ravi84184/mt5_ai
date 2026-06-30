<?php

namespace App\Services;

use App\Models\Signal;

class SignalValidatorService
{
    /**
     * @param  array<string, mixed>  $symbolData
     */
    public function getRejectionReason(Signal $signal, array $symbolData): ?string
    {
        if (! in_array($signal->action, ['BUY', 'SELL'], true)) {
            return null;
        }

        $entry = (float) $signal->entry_price;
        $sl = (float) $signal->stop_loss;
        $tp = (float) $signal->take_profit;

        if ($entry <= 0 || $sl <= 0 || $tp <= 0) {
            return 'Missing or invalid entry, stop_loss, or take_profit';
        }

        $bid = (float) ($symbolData['market']['bid'] ?? 0);
        $ask = (float) ($symbolData['market']['ask'] ?? 0);
        $digits = (int) ($symbolData['symbol_info']['digits'] ?? 5);
        $point = (float) ($symbolData['symbol_info']['point'] ?? 0.00001);
        $stopsPoints = (float) ($symbolData['symbol_info']['min_stop_distance_points'] ?? 0);
        $minStopDistance = (float) ($symbolData['symbol_info']['min_stop_distance'] ?? ($stopsPoints * $point));

        if ($signal->action === 'BUY') {
            if ($sl >= $entry) {
                return 'BUY stop_loss must be below entry';
            }
            if ($tp <= $entry) {
                return 'BUY take_profit must be above entry';
            }
        } else {
            if ($sl <= $entry) {
                return 'SELL stop_loss must be above entry';
            }
            if ($tp >= $entry) {
                return 'SELL take_profit must be below entry';
            }
        }

        $slDistance = abs($entry - $sl);
        $tpDistance = abs($tp - $entry);

        if ($slDistance <= 0) {
            return 'Stop loss distance is zero';
        }

        $riskReward = $tpDistance / $slDistance;
        $minRr = (float) config('trading.ai_entry.min_risk_reward', 2.0);
        if ($riskReward < $minRr) {
            return sprintf('Risk:reward %.2f below minimum %.2f', $riskReward, $minRr);
        }

        if ($minStopDistance > 0 && $slDistance < $minStopDistance) {
            return sprintf('Stop distance %.5f below broker minimum %.5f', $slDistance, $minStopDistance);
        }

        $maxEntrySlippage = (int) config('trading.signal_validator.max_entry_slippage_points', 50);
        if ($maxEntrySlippage > 0 && $point > 0 && $bid > 0 && $ask > 0) {
            $reference = $signal->action === 'BUY' ? $ask : $bid;
            $slippagePoints = abs($entry - $reference) / $point;
            if ($slippagePoints > $maxEntrySlippage) {
                return sprintf(
                    'Entry price %s too far from market %s',
                    round($entry, $digits),
                    round($reference, $digits)
                );
            }
        }

        return null;
    }
}
