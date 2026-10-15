<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

/** Validación y normalización compartida por altas y ediciones. */
final class SupplierFields
{
    /** @return array{business_name: ?string, contact_name: ?string, phone: ?string, email: ?string, address: ?string} */
    public static function clean(?string $businessName, ?string $contactName, ?string $phone, ?string $email, ?string $address, ?int $leadTimeDays, bool $nameRequired = true): array
    {
        $name = $businessName === null ? null : trim((string) preg_replace('/\s+/u', ' ', $businessName));
        if ($nameRequired && ($name === null || mb_strlen($name) < 2)) {
            throw SupplierException::invalid('La razón social debe tener al menos 2 caracteres.');
        }
        if ($name !== null && mb_strlen($name) > 160) {
            throw SupplierException::invalid('La razón social no puede superar 160 caracteres.');
        }

        $contact = self::blankToNull($contactName);
        if ($contact !== null && mb_strlen($contact) > 120) {
            throw SupplierException::invalid('El contacto no puede superar 120 caracteres.');
        }

        $phone = self::blankToNull($phone);
        if ($phone !== null && preg_match('/^[0-9+()\s-]{6,30}$/', $phone) !== 1) {
            throw SupplierException::invalid('El teléfono no es válido.');
        }

        $email = self::blankToNull($email);
        $email = $email === null ? null : mb_strtolower($email);
        if ($email !== null && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 160)) {
            throw SupplierException::invalid('El correo no es válido.');
        }

        $address = self::blankToNull($address);
        if ($address !== null && mb_strlen($address) > 255) {
            throw SupplierException::invalid('La dirección no puede superar 255 caracteres.');
        }

        if ($leadTimeDays !== null && ($leadTimeDays < 0 || $leadTimeDays > 365)) {
            throw SupplierException::invalid('El plazo de entrega debe estar entre 0 y 365 días.');
        }

        return ['business_name' => $name, 'contact_name' => $contact, 'phone' => $phone, 'email' => $email, 'address' => $address];
    }

    private static function blankToNull(?string $value): ?string
    {
        $v = $value === null ? null : trim($value);

        return $v === null || $v === '' ? null : $v;
    }
}
