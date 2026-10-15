<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

interface CatalogRepository
{
    public function category(int $id): ?CategoryInfo;

    public function brandExists(int $id): bool;

    /**
     * @param  list<int>  $ids
     * @return list<int> los que existen
     */
    public function existingUnitIds(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return list<int> los que existen
     */
    public function existingWarehouseIds(array $ids): array;

    /** id del producto dueño del código, o null si está libre. */
    public function barcodeOwner(string $barcode): ?int;

    /** Inserta producto + presentaciones + códigos + políticas. Debe llamarse dentro de una transacción. */
    public function insert(NewProduct $product, string $sku): int;

    /** Bloquea la fila del producto (FOR UPDATE). */
    public function lock(int $productId): ?ProductRecord;

    /** Aplica los cambios e incrementa la versión. */
    public function update(int $productId, ProductChanges $changes): void;

    public function setStatus(int $productId, ProductStatus $status): void;

    /** Stock total del producto (suma de todas las ubicaciones), como decimal en texto. */
    public function totalStock(int $productId): string;

    /** @return list<SimilarProduct> */
    public function similarNames(string $name, float $threshold, int $limit): array;

    public function lookup(string $code): ?ProductLookup;
}
