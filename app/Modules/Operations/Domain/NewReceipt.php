<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class NewReceipt
{
    public const MAX_LINES = 500;

    public string $docNumber;

    public ?string $notes;

    /** @param list<ReceiptLine> $lines */
    public function __construct(
        public int $supplierId,
        public int $warehouseId,
        public DocumentType $docType,
        string $docNumber,
        public string $docDate,
        public int $receivedBy,
        public array $lines,
        ?string $notes = null,
    ) {
        $this->docNumber = strtoupper(trim($docNumber));
        if (preg_match('/^[A-Z0-9][A-Z0-9\-\/]{0,29}$/', $this->docNumber) !== 1) {
            throw OperationsException::invalid('El número de documento no es válido (letras, números, guion o barra; máximo 30).');
        }

        $parts = explode('-', $docDate);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate) !== 1 || ! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            throw OperationsException::invalid('La fecha del documento no es válida (use AAAA-MM-DD).');
        }

        if ($lines === [] || count($lines) > self::MAX_LINES) {
            throw OperationsException::invalid('La recepción debe tener entre 1 y '.self::MAX_LINES.' líneas.');
        }

        $this->notes = $notes === null || trim($notes) === '' ? null : trim($notes);
        if ($this->notes !== null && mb_strlen($this->notes) > 500) {
            throw OperationsException::invalid('Las notas no pueden superar 500 caracteres.');
        }
    }
}
