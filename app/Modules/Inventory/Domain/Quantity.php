<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use App\Modules\Inventory\Domain\Exceptions\InventoryException;

/**
 * Cantidad no negativa con 3 decimales. Usa aritmética decimal (bcmath), nunca float.
 */
final readonly class Quantity implements \Stringable
{
    private const SCALE = 3;

    private function __construct(public string $value) {}

    public static function of(string|int $value): self
    {
        $raw = trim((string) $value);

        if (preg_match('/^\d{1,11}(\.\d{1,3})?$/', $raw) !== 1) {
            throw InventoryException::invalid("Cantidad inválida: '{$raw}'.");
        }

        return new self(bcadd($raw, '0', self::SCALE));
    }

    public static function zero(): self
    {
        return new self(bcadd('0', '0', self::SCALE));
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->value, $other->value, self::SCALE));
    }

    public function subtract(self $other): self
    {
        if ($this->lessThan($other)) {
            throw InventoryException::invalid('La resta produciría una cantidad negativa.');
        }

        return new self(bcsub($this->value, $other->value, self::SCALE));
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', self::SCALE) === 1;
    }

    public function lessThan(self $other): bool
    {
        return bccomp($this->value, $other->value, self::SCALE) === -1;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return bccomp($this->value, $other->value, self::SCALE) >= 0;
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
