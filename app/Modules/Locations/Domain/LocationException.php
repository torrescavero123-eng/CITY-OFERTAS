<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

final class LocationException extends \DomainException
{
    private function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 'INVALID_LOCATION');
    }

    public static function warehouseNotFound(int $id): self
    {
        return new self("El almacén {$id} no existe o está desactivado.", 'WAREHOUSE_NOT_FOUND');
    }

    public static function notFound(int $id): self
    {
        return new self("La ubicación {$id} no existe.", 'LOCATION_NOT_FOUND');
    }

    public static function hasStock(string $code, string $quantity): self
    {
        return new self("La ubicación {$code} aún tiene {$quantity} unidades: muévalas antes de desactivarla.", 'LOCATION_HAS_STOCK');
    }

    public static function isStaging(string $code): self
    {
        return new self("La ubicación {$code} es de paso (recepción) y no se puede desactivar.", 'LOCATION_IS_STAGING');
    }

    public static function isFrozen(string $code): self
    {
        return new self("La ubicación {$code} está congelada por un conteo: descongélela primero.", 'LOCATION_FROZEN');
    }
}
