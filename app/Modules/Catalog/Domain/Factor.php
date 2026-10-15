<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/** Factor de conversión de una presentación a la unidad base (3 decimales, > 0). */
final readonly class Factor
{
    private function __construct(public string $value) {}

    public static function of(string|int $value): self
    {
        $raw = trim((string) $value);

        if (preg_match('/^\d{1,9}(\.\d{1,3})?$/', $raw) !== 1 || bccomp($raw, '0', 3) <= 0) {
            throw CatalogException::invalid("Factor de presentación inválido: '{$raw}'.");
        }

        return new self(bcadd($raw, '0', 3));
    }

    public function isOne(): bool
    {
        return bccomp($this->value, '1', 3) === 0;
    }
}
