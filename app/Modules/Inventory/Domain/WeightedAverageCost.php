<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

/**
 * Costo promedio ponderado global por producto:
 *   nuevo = (Q_total x promedio_actual + q x costo) / (Q_total + q)
 * Redondeo half-up a 4 decimales.
 */
final class WeightedAverageCost
{
    public static function next(
        Quantity $currentQty,
        UnitCost $currentAvg,
        Quantity $incomingQty,
        UnitCost $incomingCost,
    ): UnitCost {
        $denominator = bcadd($currentQty->value, $incomingQty->value, 3);

        if (bccomp($denominator, '0', 3) === 0) {
            return $incomingCost;
        }

        $numerator = bcadd(
            bcmul($currentQty->value, $currentAvg->value, 7),
            bcmul($incomingQty->value, $incomingCost->value, 7),
            7,
        );

        $quotient = bcdiv($numerator, $denominator, 10);

        // bcadd trunca: sumar 0.00005 antes de truncar a 4 decimales equivale a half-up (valores >= 0).
        return UnitCost::of(bcadd($quotient, '0.00005', 4));
    }
}
