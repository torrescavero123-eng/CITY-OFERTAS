<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\AdjustmentRecord;
use App\Modules\Operations\Domain\AdjustmentRepository;
use App\Modules\Operations\Domain\AdjustmentStatus;
use App\Modules\Operations\Domain\NewAdjustment;
use Illuminate\Support\Facades\DB;

final class DatabaseAdjustmentRepository implements AdjustmentRepository
{
    public function insertAdjustment(NewAdjustment $a, string $code): int
    {
        return (int) DB::table('stock_adjustment_requests')->insertGetId([
            'code' => $code,
            'kind' => $a->kind->value,
            'product_id' => $a->productId,
            'location_id' => $a->locationId,
            'quantity' => $a->quantity->value,
            'reason_id' => $a->reasonId,
            'notes' => $a->notes,
            'evidence_path' => $a->evidencePath,
            'status' => AdjustmentStatus::Pending->value,
            'requested_by' => $a->requestedBy,
        ], 'id');
    }

    public function lockAdjustment(int $id): ?AdjustmentRecord
    {
        $r = DB::table('stock_adjustment_requests')->where('id', $id)->lockForUpdate()->first();

        return $r === null ? null : new AdjustmentRecord(
            (int) $r->id, (string) $r->code, AdjustmentStatus::from((string) $r->status),
            new NewAdjustment(
                AdjustmentKind::from((string) $r->kind), (int) $r->product_id, (int) $r->location_id,
                Quantity::of((string) $r->quantity), (int) $r->reason_id, (string) $r->notes, (int) $r->requested_by,
                $r->evidence_path === null ? null : (string) $r->evidence_path,
            ),
            $r->movement_id === null ? null : (int) $r->movement_id,
        );
    }

    public function markApproved(int $id, int $reviewerId, int $movementId, ?string $reviewNotes): void
    {
        DB::table('stock_adjustment_requests')->where('id', $id)->update([
            'status' => AdjustmentStatus::Approved->value,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
            'movement_id' => $movementId,
        ]);
    }

    public function markRejected(int $id, int $reviewerId, string $reviewNotes): void
    {
        DB::table('stock_adjustment_requests')->where('id', $id)->update([
            'status' => AdjustmentStatus::Rejected->value,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);
    }
}
