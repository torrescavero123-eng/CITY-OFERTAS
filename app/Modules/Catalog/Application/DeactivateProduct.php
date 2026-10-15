<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Shared\Domain\Transaction;

final class DeactivateProduct
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $productId, ?int $actorId = null): void
    {
        $this->transaction->run(function () use ($productId, $actorId): void {
            $product = $this->catalog->lock($productId) ?? throw CatalogException::productNotFound($productId);

            if ($product->status !== ProductStatus::Active) {
                return; // idempotente
            }

            $stock = $this->catalog->totalStock($productId);
            if (bccomp($stock, '0', 3) > 0) {
                throw CatalogException::hasStock($product->sku, $stock);
            }

            $this->catalog->setStatus($productId, ProductStatus::Inactive);
            $this->audit->record(new AuditEntry(
                'product.deactivated', 'product', $productId, $actorId,
                ['status' => 'ACTIVE'], ['status' => 'INACTIVE'],
            ));
        });
    }
}
