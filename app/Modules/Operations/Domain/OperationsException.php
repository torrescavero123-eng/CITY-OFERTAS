<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final class OperationsException extends \DomainException
{
    private function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 'INVALID_OPERATION');
    }

    public static function notFound(string $what, int $id): self
    {
        return new self("{$what} {$id} no existe.", 'NOT_FOUND');
    }

    public static function wrongState(string $message): self
    {
        return new self($message, 'WRONG_STATE');
    }

    public static function duplicateDocument(string $type, string $number): self
    {
        return new self("Ya se registró {$type} {$number} de ese proveedor.", 'DUPLICATE_DOCUMENT');
    }

    public static function supplierInactive(): self
    {
        return new self('El proveedor no existe o está desactivado.', 'SUPPLIER_INACTIVE');
    }

    public static function warehouseNotFound(): self
    {
        return new self('El almacén no existe o está desactivado.', 'WAREHOUSE_NOT_FOUND');
    }

    public static function locationNotInWarehouse(int $locationId): self
    {
        return new self("La ubicación {$locationId} no existe o no pertenece al almacén de la recepción.", 'LOCATION_NOT_IN_WAREHOUSE');
    }

    public static function separationOfDuties(): self
    {
        return new self('Quien solicita un ajuste no puede aprobarlo: debe hacerlo otra persona.', 'SEPARATION_OF_DUTIES');
    }

    public static function exceedsDirectLimit(string $value, string $limit): self
    {
        return new self("El ajuste vale S/ {$value} y supera el límite de registro directo (S/ {$limit}): debe solicitarse para aprobación.", 'EXCEEDS_DIRECT_LIMIT');
    }

    /** El almacén rechazó el movimiento (stock insuficiente, ubicación congelada, etc.). */
    public static function stockRejected(string $message, string $inventoryCode): self
    {
        return new self($message, $inventoryCode);
    }
}
