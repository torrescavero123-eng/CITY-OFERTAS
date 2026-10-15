<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Modules\Operations\Domain\OperationsException;

/** Lectura segura de los datos que llegan de los formularios. */
final class FormInput
{
    public static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function nullableString(mixed $value): ?string
    {
        $s = self::string($value);

        return $s === '' ? null : $s;
    }

    public static function requiredInt(mixed $value, string $label): int
    {
        $s = self::string($value);

        return $s === '' ? throw OperationsException::invalid("Falta seleccionar {$label}.") : (int) $s;
    }

    public static function requiredDecimal(mixed $value, string $label): string
    {
        $s = self::string($value);

        return $s === '' ? throw OperationsException::invalid("Falta indicar {$label}.") : $s;
    }

    /** @return list<array<string, mixed>> */
    public static function rows(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
