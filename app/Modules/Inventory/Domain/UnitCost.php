<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use App\Modules\Inventory\Domain\Exceptions\InventoryException;

/**
 * Costo unitario en PEN, sin IGV, con 4 decimales.
 */
final readonly class UnitCost implements \Stringable
{
    private const SCALE = 4;

    private function __construct(public string $value) {}

    public static function of(string|int $value): self
    {
        $raw = trim((string) $value);

        if (preg_match('/^\d{1,10}(\.\d{1,4})?$/', $raw) !== 1) {
            throw InventoryException::invalid("Costo unitario inválido: '{$raw}'.");
        }

        return new self(bcadd($raw, '0', self::SCALE));
    }

    public static function zero(): self
    {
        return new self(bcadd('0', '0', self::SCALE));
    }

    public function equals(self $other): bool
    {
        return bccomp($this->value, $other->value, self::SCALE) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
