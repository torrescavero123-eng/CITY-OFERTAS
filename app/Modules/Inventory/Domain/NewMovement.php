<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use App\Modules\Inventory\Domain\Exceptions\InventoryException;

/**
 * Movimiento ya validado (forma, motivo, costo). Si existe, es estructuralmente correcto;
 * las reglas que dependen del estado (stock, ubicación congelada) las aplica el caso de uso.
 */
final readonly class NewMovement
{
    public string $uuid;

    public function __construct(
        string $uuid,
        public string $idempotencyKey,
        public MovementType $type,
        public int $productId,
        public ?int $fromLocationId,
        public ?int $toLocationId,
        public Quantity $quantity,
        public int $userId,
        public ?UnitCost $unitCost = null,
        public ?int $reasonId = null,
        public ?int $presentationId = null,
        public ?Quantity $presentationQty = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $documentRef = null,
        public ?string $notes = null,
        public ?int $approvedBy = null,
        public ?\DateTimeImmutable $clientCreatedAt = null,
    ) {
        $this->uuid = strtolower($uuid);
        $this->assertValid();
    }

    /** Los ajustes derivados de un conteo sí pueden operar sobre ubicaciones congeladas. */
    public function allowsFrozenLocations(): bool
    {
        return $this->sourceType === 'COUNT';
    }

    /** @return list<int> */
    public function locationIds(): array
    {
        return array_values(array_filter(
            [$this->fromLocationId, $this->toLocationId],
            static fn (?int $id): bool => $id !== null,
        ));
    }

    private function assertValid(): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $this->uuid) !== 1) {
            throw InventoryException::invalid('El uuid del movimiento no es válido.');
        }

        if (preg_match('/^[\w:.\-]{8,80}$/', $this->idempotencyKey) !== 1) {
            throw InventoryException::invalid('La clave de idempotencia debe tener entre 8 y 80 caracteres.');
        }

        if (! $this->quantity->isPositive()) {
            throw InventoryException::invalid('La cantidad debe ser mayor que cero.');
        }

        $this->assertShape();

        if ($this->type->requiresReason() && $this->reasonId === null) {
            throw InventoryException::invalid('Este tipo de movimiento exige un motivo.');
        }

        if ($this->type->requiresCost() && $this->unitCost === null) {
            throw InventoryException::invalid('Este tipo de movimiento exige el costo unitario.');
        }

        if (! $this->type->requiresCost() && $this->unitCost !== null) {
            throw InventoryException::invalid('Este tipo de movimiento no admite costo: se valoriza al promedio vigente.');
        }

        if (($this->presentationId === null) !== ($this->presentationQty === null)) {
            throw InventoryException::invalid('La presentación y su cantidad deben informarse juntas.');
        }

        if ($this->notes !== null && mb_strlen($this->notes) > 500) {
            throw InventoryException::invalid('Las notas no pueden superar 500 caracteres.');
        }

        if ($this->documentRef !== null && mb_strlen($this->documentRef) > 60) {
            throw InventoryException::invalid('La referencia del documento no puede superar 60 caracteres.');
        }
    }

    private function assertShape(): void
    {
        if ($this->type->isEntry() && ($this->toLocationId === null || $this->fromLocationId !== null)) {
            throw InventoryException::invalid('Una entrada requiere solo ubicación de destino.');
        }

        if ($this->type->isExit() && ($this->fromLocationId === null || $this->toLocationId !== null)) {
            throw InventoryException::invalid('Una salida requiere solo ubicación de origen.');
        }

        if ($this->type->isTransfer()) {
            if ($this->fromLocationId === null || $this->toLocationId === null) {
                throw InventoryException::invalid('Una transferencia requiere origen y destino.');
            }

            if ($this->fromLocationId === $this->toLocationId) {
                throw InventoryException::invalid('El origen y el destino deben ser distintos.');
            }
        }
    }
}
