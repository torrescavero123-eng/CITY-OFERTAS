<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\TransferLine;
use App\Modules\Operations\Domain\TransferLineRecord;
use App\Modules\Operations\Domain\TransferRecord;
use App\Modules\Operations\Domain\TransferRepository;
use App\Modules\Operations\Domain\TransferStatus;
use Illuminate\Support\Facades\DB;

final class DatabaseTransferRepository implements TransferRepository
{
    public function insertTransfer(NewTransfer $transfer, string $code): int
    {
        $id = (int) DB::table('transfer_orders')->insertGetId([
            'code' => $code,
            'status' => TransferStatus::Draft->value,
            'notes' => $transfer->notes,
            'requested_by' => $transfer->requestedBy,
        ], 'id');

        DB::table('transfer_order_items')->insert(array_map(static fn (TransferLine $l): array => [
            'order_id' => $id,
            'product_id' => $l->productId,
            'from_location_id' => $l->fromLocationId,
            'to_location_id' => $l->toLocationId,
            'quantity' => $l->quantity->value,
        ], $transfer->lines));

        return $id;
    }

    public function lockTransfer(int $id): ?TransferRecord
    {
        $t = DB::table('transfer_orders')->where('id', $id)->lockForUpdate()->first();
        if ($t === null) {
            return null;
        }

        $lines = [];
        foreach (DB::table('transfer_order_items')->where('order_id', $id)->orderBy('id')->get() as $l) {
            $lines[] = new TransferLineRecord(
                (int) $l->id,
                new TransferLine((int) $l->product_id, (int) $l->from_location_id, (int) $l->to_location_id, Quantity::of((string) $l->quantity)),
                $l->movement_id === null ? null : (int) $l->movement_id,
            );
        }

        return new TransferRecord((int) $t->id, (string) $t->code, TransferStatus::from((string) $t->status), (int) $t->requested_by, $lines);
    }

    public function setLineMovement(int $lineId, int $movementId): void
    {
        DB::table('transfer_order_items')->where('id', $lineId)->update(['movement_id' => $movementId]);
    }

    public function setTransferStatus(int $id, TransferStatus $status, ?\DateTimeImmutable $completedAt = null): void
    {
        DB::table('transfer_orders')->where('id', $id)->update([
            'status' => $status->value,
            'completed_at' => $completedAt?->format('Y-m-d H:i:sP'),
            'updated_at' => now(),
        ]);
    }
}
