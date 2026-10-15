<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\CountBalance;
use App\Modules\Operations\Domain\CountLineRecord;
use App\Modules\Operations\Domain\CountRecord;
use App\Modules\Operations\Domain\CountRepository;
use App\Modules\Operations\Domain\CountScope;
use App\Modules\Operations\Domain\CountStatus;
use App\Modules\Operations\Domain\NewCount;
use Illuminate\Support\Facades\DB;

final class DatabaseCountRepository implements CountRepository
{
    public function insertCount(NewCount $count, string $code, CountScope $scope, \DateTimeImmutable $snapshotAt): int
    {
        $id = (int) DB::table('stock_counts')->insertGetId([
            'code' => $code,
            'warehouse_id' => $count->warehouseId,
            'scope_type' => $count->scopeType->value,
            'scope_value' => $count->scopeValue,
            'status' => CountStatus::Counting->value,
            'snapshot_at' => $snapshotAt->format('Y-m-d H:i:sP'),
            'opened_by' => $count->openedBy,
        ], 'id');

        foreach (array_chunk($scope->locationIds, 1000) as $chunk) {
            DB::table('stock_count_locations')->insert(array_map(static fn (int $l): array => ['count_id' => $id, 'location_id' => $l], $chunk));
        }
        foreach (array_chunk($scope->balances, 1000) as $chunk) {
            DB::table('stock_count_lines')->insert(array_map(static fn (CountBalance $b): array => [
                'count_id' => $id, 'product_id' => $b->productId, 'location_id' => $b->locationId, 'expected_qty' => $b->quantity->value,
            ], $chunk));
        }

        return $id;
    }

    public function lockCount(int $id): ?CountRecord
    {
        $c = DB::table('stock_counts')->where('id', $id)->lockForUpdate()->first();
        if ($c === null) {
            return null;
        }

        $locations = array_map('intval', DB::table('stock_count_locations')->where('count_id', $id)->orderBy('location_id')->pluck('location_id')->all());

        return new CountRecord((int) $c->id, (string) $c->code, (int) $c->warehouse_id, CountStatus::from((string) $c->status), (int) $c->opened_by, $locations);
    }

    public function countLines(int $countId): array
    {
        return array_map($this->toRecord(...), DB::table('stock_count_lines')->where('count_id', $countId)->orderBy('id')->get()->all());
    }

    public function findLine(int $countId, int $productId, int $locationId): ?CountLineRecord
    {
        $r = DB::table('stock_count_lines')->where('count_id', $countId)->where('product_id', $productId)->where('location_id', $locationId)->first();

        return $r === null ? null : $this->toRecord($r);
    }

    public function saveCounted(int $countId, int $productId, int $locationId, Quantity $counted, int $userId, \DateTimeImmutable $at, bool $recountRequested): void
    {
        DB::statement(
            'INSERT INTO stock_count_lines (count_id, product_id, location_id, expected_qty, counted_qty, recount_requested, counted_by, counted_at)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?)
             ON CONFLICT (count_id, product_id, location_id)
             DO UPDATE SET counted_qty = EXCLUDED.counted_qty, recount_requested = EXCLUDED.recount_requested,
                           counted_by = EXCLUDED.counted_by, counted_at = EXCLUDED.counted_at',
            [$countId, $productId, $locationId, $counted->value, $recountRequested ? 'true' : 'false', $userId, $at->format('Y-m-d H:i:sP')],
        );
    }

    public function flagRecounts(int $countId, array $lineIds): void
    {
        foreach (array_chunk($lineIds, 1000) as $chunk) {
            DB::table('stock_count_lines')->where('count_id', $countId)->whereIn('id', $chunk)->update(['recount_requested' => true]);
        }
    }

    public function setLineAdjustment(int $lineId, int $adjustmentId): void
    {
        DB::table('stock_count_lines')->where('id', $lineId)->update(['adjustment_request_id' => $adjustmentId]);
    }

    public function setCountStatus(int $id, CountStatus $status, ?int $closedBy = null, ?\DateTimeImmutable $closedAt = null): void
    {
        DB::table('stock_counts')->where('id', $id)->update([
            'status' => $status->value,
            'closed_by' => $closedBy,
            'closed_at' => $closedAt?->format('Y-m-d H:i:sP'),
        ]);
    }

    private function toRecord(object $r): CountLineRecord
    {
        return new CountLineRecord(
            (int) $r->id, (int) $r->product_id, (int) $r->location_id,
            Quantity::of((string) $r->expected_qty),
            $r->counted_qty === null ? null : Quantity::of((string) $r->counted_qty),
            filter_var($r->recount_requested, FILTER_VALIDATE_BOOLEAN),
            $r->counted_by === null ? null : (int) $r->counted_by,
            $r->adjustment_request_id === null ? null : (int) $r->adjustment_request_id,
        );
    }
}
