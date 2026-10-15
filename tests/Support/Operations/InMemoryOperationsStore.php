<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Modules\Operations\Domain\AdjustmentRecord;
use App\Modules\Operations\Domain\AdjustmentRepository;
use App\Modules\Operations\Domain\AdjustmentStatus;
use App\Modules\Operations\Domain\DocumentNumbers;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Modules\Operations\Domain\ReasonInfo;
use App\Modules\Operations\Domain\ReceiptItemRecord;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\ReceiptRecord;
use App\Modules\Operations\Domain\ReceiptRepository;
use App\Modules\Operations\Domain\ReceiptStatus;
use App\Modules\Operations\Domain\TransferLine;
use App\Modules\Operations\Domain\TransferLineRecord;
use App\Modules\Operations\Domain\TransferRecord;
use App\Modules\Operations\Domain\TransferRepository;
use App\Modules\Operations\Domain\TransferStatus;

final class InMemoryOperationsStore implements AdjustmentRepository, DocumentNumbers, OperationsLookup, ReceiptRepository, TransferRepository
{
    // --- datos maestros de la prueba ---
    /** @var array<int, bool> */
    public array $suppliers = [1 => true, 2 => false];

    /** @var array<int, bool> */
    public array $warehouses = [1 => true, 2 => true];

    /** @var array<int, int> ubicación => almacén */
    public array $locationWarehouse = [10 => 1, 11 => 1, 20 => 2];

    /** @var array<int, ReasonInfo> */
    public array $reasons = [];

    /** @var array<int, string> producto => costo promedio */
    public array $costs = [5 => '2.0000', 6 => '10.0000'];

    public string $directLimit = '0.00';

    // --- documentos ---
    /** @var array<int, array{code: string, status: ReceiptStatus, supplierId: int, warehouseId: int, docType: DocumentType, docNumber: string, items: array<int, array{line: ReceiptLine, movementId: ?int}>}> */
    public array $receipts = [];

    /** @var array<int, array{code: string, status: TransferStatus, requestedBy: int, lines: array<int, array{line: TransferLine, movementId: ?int}>}> */
    public array $transfers = [];

    /** @var array<int, array{code: string, status: AdjustmentStatus, request: NewAdjustment, movementId: ?int, reviewer: ?int, notes: ?string}> */
    public array $adjustments = [];

    /** @var array<string, int> */
    public array $counters = [];

    private int $nextItemId = 1;

    // ---- OperationsLookup ----
    public function supplierIsActive(int $supplierId): bool
    {
        return $this->suppliers[$supplierId] ?? false;
    }

    public function warehouseIsActive(int $warehouseId): bool
    {
        return $this->warehouses[$warehouseId] ?? false;
    }

    public function locationWarehouseId(int $locationId): ?int
    {
        return $this->locationWarehouse[$locationId] ?? null;
    }

    public function reason(int $reasonId): ?ReasonInfo
    {
        return $this->reasons[$reasonId] ?? null;
    }

    /** @var array<string, int> */
    public array $reasonCodes = ['AJU_CONTEO_FAVOR' => 3, 'AJU_ERROR_CONTEO' => 2];

    public function reasonIdByCode(string $code): ?int
    {
        return $this->reasonCodes[$code] ?? null;
    }

    public function averageCost(int $productId): ?string
    {
        return $this->costs[$productId] ?? null;
    }

    public function directAdjustmentLimit(): string
    {
        return $this->directLimit;
    }

    // ---- DocumentNumbers ----
    public function next(string $prefix): string
    {
        $this->counters[$prefix] = ($this->counters[$prefix] ?? 0) + 1;

        return sprintf('%s-2026-%06d', $prefix, $this->counters[$prefix]);
    }

    // ---- ReceiptRepository ----
    public function activeDocumentExists(int $supplierId, DocumentType $type, string $number): bool
    {
        foreach ($this->receipts as $r) {
            if ($r['supplierId'] === $supplierId && $r['docType'] === $type && $r['docNumber'] === $number && $r['status'] !== ReceiptStatus::Cancelled) {
                return true;
            }
        }

        return false;
    }

    public function insertReceipt(NewReceipt $receipt, string $code): int
    {
        $id = count($this->receipts) + 1;
        $this->receipts[$id] = [
            'code' => $code, 'status' => ReceiptStatus::Draft, 'supplierId' => $receipt->supplierId, 'warehouseId' => $receipt->warehouseId,
            'docType' => $receipt->docType, 'docNumber' => $receipt->docNumber, 'items' => [],
        ];
        $this->replaceReceiptItems($id, $receipt->lines);

        return $id;
    }

    public function replaceReceiptItems(int $receiptId, array $lines): void
    {
        $this->receipts[$receiptId]['items'] = [];
        foreach ($lines as $line) {
            $this->receipts[$receiptId]['items'][$this->nextItemId++] = ['line' => $line, 'movementId' => null];
        }
    }

    public function lockReceipt(int $id): ?ReceiptRecord
    {
        $r = $this->receipts[$id] ?? null;
        if ($r === null) {
            return null;
        }
        $items = [];
        foreach ($r['items'] as $itemId => $item) {
            $items[] = new ReceiptItemRecord($itemId, $item['line'], $item['movementId']);
        }

        return new ReceiptRecord($id, $r['code'], $r['status'], $r['supplierId'], $r['warehouseId'], $r['docType']->value, $r['docNumber'], $items);
    }

    public function setItemMovement(int $itemId, int $movementId): void
    {
        foreach ($this->receipts as $rid => $r) {
            if (isset($r['items'][$itemId])) {
                $this->receipts[$rid]['items'][$itemId]['movementId'] = $movementId;
            }
        }
    }

    public function setReceiptStatus(int $id, ReceiptStatus $status, ?\DateTimeImmutable $confirmedAt = null): void
    {
        $this->receipts[$id]['status'] = $status;
    }

    // ---- TransferRepository ----
    public function insertTransfer(NewTransfer $transfer, string $code): int
    {
        $id = count($this->transfers) + 1;
        $lines = [];
        foreach ($transfer->lines as $line) {
            $lines[$this->nextItemId++] = ['line' => $line, 'movementId' => null];
        }
        $this->transfers[$id] = ['code' => $code, 'status' => TransferStatus::Draft, 'requestedBy' => $transfer->requestedBy, 'lines' => $lines];

        return $id;
    }

    public function lockTransfer(int $id): ?TransferRecord
    {
        $t = $this->transfers[$id] ?? null;
        if ($t === null) {
            return null;
        }
        $lines = [];
        foreach ($t['lines'] as $lineId => $l) {
            $lines[] = new TransferLineRecord($lineId, $l['line'], $l['movementId']);
        }

        return new TransferRecord($id, $t['code'], $t['status'], $t['requestedBy'], $lines);
    }

    public function setLineMovement(int $lineId, int $movementId): void
    {
        foreach ($this->transfers as $tid => $t) {
            if (isset($t['lines'][$lineId])) {
                $this->transfers[$tid]['lines'][$lineId]['movementId'] = $movementId;
            }
        }
    }

    public function setTransferStatus(int $id, TransferStatus $status, ?\DateTimeImmutable $completedAt = null): void
    {
        $this->transfers[$id]['status'] = $status;
    }

    // ---- AdjustmentRepository ----
    public function insertAdjustment(NewAdjustment $adjustment, string $code): int
    {
        $id = count($this->adjustments) + 1;
        $this->adjustments[$id] = ['code' => $code, 'status' => AdjustmentStatus::Pending, 'request' => $adjustment, 'movementId' => null, 'reviewer' => null, 'notes' => null];

        return $id;
    }

    public function lockAdjustment(int $id): ?AdjustmentRecord
    {
        $a = $this->adjustments[$id] ?? null;

        return $a === null ? null : new AdjustmentRecord($id, $a['code'], $a['status'], $a['request'], $a['movementId']);
    }

    public function markApproved(int $id, int $reviewerId, int $movementId, ?string $reviewNotes): void
    {
        $this->adjustments[$id] = ['status' => AdjustmentStatus::Approved, 'movementId' => $movementId, 'reviewer' => $reviewerId, 'notes' => $reviewNotes] + $this->adjustments[$id];
    }

    public function markRejected(int $id, int $reviewerId, string $reviewNotes): void
    {
        $this->adjustments[$id] = ['status' => AdjustmentStatus::Rejected, 'reviewer' => $reviewerId, 'notes' => $reviewNotes] + $this->adjustments[$id];
    }

    // ---- transacción simulada ----
    /** @return array<int, mixed> */
    public function snapshot(): array
    {
        return [$this->receipts, $this->transfers, $this->adjustments, $this->counters, $this->nextItemId];
    }

    /** @param array<int, mixed> $state */
    public function restore(array $state): void
    {
        [$this->receipts, $this->transfers, $this->adjustments, $this->counters, $this->nextItemId] = $state;
    }
}
