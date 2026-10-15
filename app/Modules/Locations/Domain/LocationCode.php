<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

/** Código legible de una ubicación: BOD-Z1-P03-A02-N2 = bodega, zona 1, pasillo 3, anaquel 2, nivel 2. */
final readonly class LocationCode implements \Stringable
{
    private function __construct(public string $value) {}

    public static function build(string $warehouseCode, int $zone, int $aisle, int $rack, int $level): self
    {
        $warehouse = strtoupper(trim($warehouseCode));

        if (preg_match('/^[A-Z]{3}$/', $warehouse) !== 1) {
            throw LocationException::invalid('El código del almacén debe tener 3 letras.');
        }
        foreach (['zona' => [$zone, 99], 'pasillo' => [$aisle, 99], 'anaquel' => [$rack, 99], 'nivel' => [$level, 9]] as $label => [$value, $max]) {
            if ($value < 1 || $value > $max) {
                throw LocationException::invalid("El {$label} debe estar entre 1 y {$max}.");
            }
        }

        return new self(sprintf('%s-Z%d-P%02d-A%02d-N%d', $warehouse, $zone, $aisle, $rack, $level));
    }

    /** Normaliza lo escaneado o digitado (mayúsculas, sin espacios). */
    public static function normalize(string $scanned): string
    {
        return strtoupper(preg_replace('/\s+/', '', $scanned) ?? '');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
