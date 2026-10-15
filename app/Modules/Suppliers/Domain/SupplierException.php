<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

final class SupplierException extends \DomainException
{
    private function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 'INVALID_SUPPLIER');
    }

    public static function rucTaken(string $ruc): self
    {
        return new self("Ya existe un proveedor con el RUC {$ruc}.", 'RUC_TAKEN');
    }

    public static function notFound(int $id): self
    {
        return new self("El proveedor {$id} no existe.", 'SUPPLIER_NOT_FOUND');
    }

    public static function inactive(string $name): self
    {
        return new self("El proveedor {$name} está desactivado.", 'SUPPLIER_INACTIVE');
    }

    public static function productNotFound(int $id): self
    {
        return new self("El producto {$id} no existe.", 'PRODUCT_NOT_FOUND');
    }
}
