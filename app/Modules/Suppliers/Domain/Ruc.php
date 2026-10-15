<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

/** RUC peruano de 11 dígitos con dígito verificador (módulo 11 de SUNAT). */
final readonly class Ruc implements \Stringable
{
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private const PREFIXES = ['10', '15', '16', '17', '20'];

    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        $ruc = trim($value);

        if (preg_match('/^\d{11}$/', $ruc) !== 1) {
            throw SupplierException::invalid('El RUC debe tener 11 dígitos.');
        }
        if (! in_array(substr($ruc, 0, 2), self::PREFIXES, true)) {
            throw SupplierException::invalid('El RUC debe empezar con 10, 15, 16, 17 o 20.');
        }

        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += (int) $ruc[$i] * $weight;
        }
        $check = 11 - ($sum % 11);
        $check = $check === 10 ? 0 : ($check === 11 ? 1 : $check);

        if ($check !== (int) $ruc[10]) {
            throw SupplierException::invalid('El RUC no es válido (dígito verificador incorrecto).');
        }

        return new self($ruc);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
