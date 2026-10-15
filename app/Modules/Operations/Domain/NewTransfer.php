<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class NewTransfer
{
    public const MAX_LINES = 500;

    public ?string $notes;

    /** @param list<TransferLine> $lines */
    public function __construct(public int $requestedBy, public array $lines, ?string $notes = null)
    {
        if ($lines === [] || count($lines) > self::MAX_LINES) {
            throw OperationsException::invalid('La transferencia debe tener entre 1 y '.self::MAX_LINES.' líneas.');
        }
        $this->notes = $notes === null || trim($notes) === '' ? null : trim($notes);
        if ($this->notes !== null && mb_strlen($this->notes) > 500) {
            throw OperationsException::invalid('Las notas no pueden superar 500 caracteres.');
        }
    }
}
