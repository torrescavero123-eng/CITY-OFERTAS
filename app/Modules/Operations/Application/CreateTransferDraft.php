<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\DocumentNumbers;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Modules\Operations\Domain\TransferRepository;
use App\Shared\Domain\Transaction;

final class CreateTransferDraft
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly OperationsLookup $lookup,
        private readonly DocumentNumbers $numbers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{id: int, code: string} */
    public function __invoke(NewTransfer $transfer): array
    {
        return $this->transaction->run(function () use ($transfer): array {
            foreach ($transfer->lines as $line) {
                foreach ([$line->fromLocationId, $line->toLocationId] as $locationId) {
                    if ($this->lookup->locationWarehouseId($locationId) === null) {
                        throw OperationsException::notFound('La ubicación', $locationId);
                    }
                }
            }

            $code = $this->numbers->next('TRF');
            $id = $this->transfers->insertTransfer($transfer, $code);
            $this->audit->record(new AuditEntry('transfer.drafted', 'transfer', $id, $transfer->requestedBy, null, ['code' => $code, 'lines' => count($transfer->lines)]));

            return ['id' => $id, 'code' => $code];
        });
    }
}
