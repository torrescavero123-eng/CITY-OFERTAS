<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers;

use App\Filament\Support\FormInput;
use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\Ruc;
use App\Modules\Suppliers\Domain\SupplierChanges;
use App\Modules\Suppliers\Domain\SupplierException;

final class SupplierFormMapper
{
    /** @param array<string, mixed> $data */
    public static function toNewSupplier(array $data, int $actorId): NewSupplier
    {
        return new NewSupplier(
            Ruc::of(FormInput::string($data['ruc'] ?? null)),
            FormInput::string($data['business_name'] ?? null),
            FormInput::nullableString($data['contact_name'] ?? null),
            FormInput::nullableString($data['phone'] ?? null),
            FormInput::nullableString($data['email'] ?? null),
            FormInput::nullableString($data['address'] ?? null),
            self::leadTime($data['lead_time_days'] ?? null),
            $actorId,
        );
    }

    /** @param array<string, mixed> $data */
    public static function toChanges(array $data, int $actorId): SupplierChanges
    {
        return new SupplierChanges(
            FormInput::nullableString($data['business_name'] ?? null),
            FormInput::nullableString($data['contact_name'] ?? null),
            FormInput::nullableString($data['phone'] ?? null),
            FormInput::nullableString($data['email'] ?? null),
            FormInput::nullableString($data['address'] ?? null),
            self::leadTime($data['lead_time_days'] ?? null),
            $actorId,
        );
    }

    private static function leadTime(mixed $value): ?int
    {
        $s = FormInput::string($value);

        if ($s === '') {
            return null;
        }
        if (! ctype_digit($s)) {
            throw SupplierException::invalid('El plazo de entrega debe ser un número entero de días.');
        }

        return (int) $s;
    }
}
