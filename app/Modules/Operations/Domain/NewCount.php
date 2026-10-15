<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class NewCount
{
    public ?string $scopeValue;

    public function __construct(public int $warehouseId, public CountScopeType $scopeType, ?string $scopeValue, public int $openedBy)
    {
        $value = $scopeValue === null ? null : trim($scopeValue);
        $this->scopeValue = $value === '' ? null : $value;

        if ($scopeType->needsValue() && $this->scopeValue === null) {
            throw OperationsException::invalid('Indique la zona, ubicación o categoría que se va a contar.');
        }
        if (! $scopeType->needsValue() && $this->scopeValue !== null) {
            throw OperationsException::invalid('Este tipo de conteo no lleva un valor de alcance.');
        }
        if ($this->scopeValue !== null && mb_strlen($this->scopeValue) > 40) {
            throw OperationsException::invalid('El valor del alcance es demasiado largo.');
        }
    }
}
