<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\ProductChanges;
use App\Shared\Domain\Transaction;

final class UpdateProduct
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $productId, ProductChanges $changes): void
    {
        $this->transaction->run(function () use ($productId, $changes): void {
            $current = $this->catalog->lock($productId) ?? throw CatalogException::productNotFound($productId);

            if ($current->version !== $changes->expectedVersion) {
                throw CatalogException::staleVersion($changes->expectedVersion, $current->version);
            }

            if ($changes->categoryId !== null) {
                $category = $this->catalog->category($changes->categoryId);
                if ($category === null || ! $category->isActive) {
                    throw CatalogException::categoryNotFound($changes->categoryId);
                }
            }
            if ($changes->brandId !== null && ! $this->catalog->brandExists($changes->brandId)) {
                throw CatalogException::brandNotFound($changes->brandId);
            }

            $before = [
                'name' => $current->name, 'description' => $current->description,
                'category_id' => $current->categoryId, 'brand_id' => $current->brandId,
                'reference_price' => $current->referencePrice,
            ];
            $new = $changes->fields();
            $changed = array_filter($new, static fn (mixed $v, string $k): bool => ($before[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);

            if ($changed === []) {
                return; // nada que cambiar: no se incrementa la versión
            }

            $this->catalog->update($productId, $changes);
            $this->audit->record(new AuditEntry(
                'product.updated', 'product', $productId, $changes->actorId,
                array_intersect_key($before, $changed), $changed,
            ));
        });
    }
}
