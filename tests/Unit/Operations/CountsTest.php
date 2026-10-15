<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Application\CancelCount;
use App\Modules\Operations\Application\CloseCount;
use App\Modules\Operations\Application\RecordCount;
use App\Modules\Operations\Application\StartCount;
use App\Modules\Operations\Application\SubmitCountForReview;
use App\Modules\Operations\Domain\AdjustmentStatus;
use App\Modules\Operations\Domain\CountBalance;
use App\Modules\Operations\Domain\CountScope;
use App\Modules\Operations\Domain\CountScopeType;
use App\Modules\Operations\Domain\CountStatus;
use App\Modules\Operations\Domain\MovementKind;
use App\Modules\Operations\Domain\NewCount;
use App\Modules\Operations\Domain\OperationsException;
use Tests\Support\Operations\InMemoryCountStore;
use Tests\Support\Operations\OperationsKit;
use Tests\Support\Operations\SnapshotTransaction;

function countKit(): array
{
    $k = new OperationsKit;
    $store = new InMemoryCountStore;
    $store->scope = new CountScope([10, 11], [
        new CountBalance(5, 10, Quantity::of('10')),
        new CountBalance(6, 10, Quantity::of('4')),
        new CountBalance(5, 11, Quantity::of('2')),
    ]);
    $tx = new SnapshotTransaction([$k->store, $k->movements, $store]);

    return [
        $k, $store,
        new StartCount($store, $store, $store, $k->store, $k->store, $tx, $k->audit, $k->clock),
        new RecordCount($store, $k->store, $tx, $k->clock),
        new SubmitCountForReview($store, $tx, $k->audit),
        new CloseCount($store, $k->store, $k->movements, $store, $k->store, $k->store, $tx, $k->audit, $k->clock),
        new CancelCount($store, $store, $tx, $k->audit),
    ];
}

function startedCount(array $kit): int
{
    return $kit[2](new NewCount(1, CountScopeType::Full, null, 7))['id'];
}

function countAll(array $kit, int $id, array $values, int $by = 8): void
{
    foreach ($values as [$product, $loc, $qty]) {
        $kit[3]($id, $product, $loc, Quantity::of($qty), $by);
    }
}

it('al iniciar fija el stock esperado, congela las ubicaciones y numera el conteo', function () {
    $kit = countKit();
    $result = $kit[2](new NewCount(1, CountScopeType::Full, null, 7));

    expect($result)->toBe(['id' => 1, 'code' => 'CNT-2026-000001', 'locations' => 2, 'lines' => 3]);
    expect($kit[1]->frozen)->toBe([10, 11]);
    expect($kit[1]->counts[1]['status'])->toBe(CountStatus::Counting);
    expect($kit[0]->audit->entries[0]->action)->toBe('count.started');
});

it('no se inicia un conteo sobre ubicaciones ya congeladas, ni con alcance vacío o sin valor', function () {
    $kit = countKit();
    startedCount($kit);

    expect(fn () => $kit[2](new NewCount(1, CountScopeType::Full, null, 7)))->toThrow(OperationsException::class, 'ya está congelada');

    $kit[1]->scope = new CountScope([], []);
    expect(fn () => $kit[2](new NewCount(1, CountScopeType::Full, null, 7)))->toThrow(OperationsException::class, 'no tiene ubicaciones');
    expect(fn () => new NewCount(1, CountScopeType::Zone, null, 7))->toThrow(OperationsException::class, 'zona');
    expect(fn () => new NewCount(1, CountScopeType::Full, 'Z1', 7))->toThrow(OperationsException::class, 'no lleva');
});

it('contar reemplaza el valor anterior y exige ubicaciones del conteo', function () {
    $kit = countKit();
    $id = startedCount($kit);

    $kit[3]($id, 5, 10, Quantity::of('9'), 8);
    $kit[3]($id, 5, 10, Quantity::of('10'), 8);

    expect((string) $kit[1]->findLine($id, 5, 10)?->counted?->value)->toBe('10.000');
    expect(fn () => $kit[3]($id, 5, 99, Quantity::of('1'), 8))->toThrow(OperationsException::class, 'no pertenece');
    expect(fn () => $kit[3](404, 5, 10, Quantity::of('1'), 8))->toThrow(OperationsException::class, 'no existe');
});

it('un hallazgo (producto sin saldo esperado) crea la línea con esperado cero; un producto inexistente se rechaza', function () {
    $kit = countKit();
    $id = startedCount($kit);

    $kit[3]($id, 6, 11, Quantity::of('3'), 8); // el producto 6 no estaba esperado en la ubicación 11

    $line = $kit[1]->findLine($id, 6, 11);
    expect($line?->expected->value)->toBe('0.000');
    expect($line?->difference())->toBe('3.000');
    expect(fn () => $kit[3]($id, 999, 11, Quantity::of('1'), 8))->toThrow(OperationsException::class, 'no existe');
});

it('no se envía a revisión con líneas sin contar (no contar NO es cero)', function () {
    $kit = countKit();
    $id = startedCount($kit);
    $kit[3]($id, 5, 10, Quantity::of('10'), 8);

    expect(fn () => $kit[4]($id, 8))->toThrow(OperationsException::class, 'Faltan 2 líneas');
    expect($kit[1]->counts[$id]['status'])->toBe(CountStatus::Counting);
});

it('al enviar a revisión marca para reconteo solo las líneas con diferencia', function () {
    $kit = countKit();
    $id = startedCount($kit);
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '3'], [5, 11, '2']]); // una diferencia: 6 en 10 (esperado 4)

    expect($kit[4]($id, 8))->toBe(['lines' => 3, 'withDifference' => 1]);

    expect($kit[1]->counts[$id]['status'])->toBe(CountStatus::Review);
    expect($kit[1]->findLine($id, 6, 10)?->recountRequested)->toBeTrue();
    expect($kit[1]->findLine($id, 5, 10)?->recountRequested)->toBeFalse();
});

it('en revisión solo se recuentan las líneas marcadas y el reconteo limpia la marca', function () {
    $kit = countKit();
    $id = startedCount($kit);
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '3'], [5, 11, '2']]);
    $kit[4]($id, 8);

    expect(fn () => $kit[3]($id, 5, 10, Quantity::of('9'), 9))->toThrow(OperationsException::class, 'recontar');
    $kit[3]($id, 6, 10, Quantity::of('3'), 9); // el reconteo confirma 3

    expect($kit[1]->findLine($id, 6, 10)?->recountRequested)->toBeFalse();
    expect($kit[1]->findLine($id, 6, 10)?->countedBy)->toBe(9);
});

it('no se cierra con reconteos pendientes ni lo cierra quien contó las diferencias', function () {
    $kit = countKit();
    $id = startedCount($kit);
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '3'], [5, 11, '2']], 8);
    $kit[4]($id, 8);

    expect(fn () => $kit[5]($id, 9))->toThrow(OperationsException::class, 'por recontar');

    $kit[3]($id, 6, 10, Quantity::of('3'), 8); // recuenta la misma persona
    expect(fn () => $kit[5]($id, 8))->toThrow(OperationsException::class, 'otra persona');
    expect($kit[0]->movements->requests)->toHaveCount(0);
});

it('al cerrar aplica las diferencias como ajustes ligados al conteo, descongela y calcula la exactitud', function () {
    $kit = countKit();
    $id = startedCount($kit);
    // diferencias: (6,10) contó 3 de 4 => -1 ; (6,11) hallazgo 2 => +2 ; (5,10) y (5,11) correctas
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '3'], [5, 11, '2'], [6, 11, '2']], 8);
    $kit[4]($id, 8);
    $kit[3]($id, 6, 10, Quantity::of('3'), 8);
    $kit[3]($id, 6, 11, Quantity::of('2'), 8);

    $result = $kit[5]($id, 9);

    expect($result)->toBe(['adjustments' => 2, 'lines' => 4, 'accuracyPct' => '50.00']);
    expect($kit[1]->counts[$id]['status'])->toBe(CountStatus::Closed);
    expect($kit[1]->frozen)->toBe([]);

    expect($kit[0]->movements->requests)->toHaveCount(2);
    [$negative, $positive] = $kit[0]->movements->requests; // orden por producto y ubicación: (6,10) luego (6,11)
    expect($negative->kind)->toBe(MovementKind::AdjustmentOut);
    expect([$negative->fromLocationId, $negative->quantity->value])->toBe([10, '1.000']);
    expect($positive->kind)->toBe(MovementKind::AdjustmentIn);
    expect([$positive->toLocationId, $positive->quantity->value])->toBe([11, '2.000']);
    expect($negative->sourceType)->toBe('COUNT');
    expect($negative->approvedBy)->toBe(9);
    expect($negative->userId)->toBe(8);
    expect($negative->documentRef)->toBe('CNT-2026-000001');
    expect($negative->idempotencyKey)->toStartWith('CNT-2026-000001:L');

    foreach ($kit[0]->store->adjustments as $a) {
        expect($a['status'])->toBe(AdjustmentStatus::Approved);
        expect($a['reviewer'])->toBe(9);
    }
    expect($kit[1]->findLine($id, 6, 10)?->adjustmentId)->not->toBeNull();
    expect($kit[1]->findLine($id, 5, 10)?->adjustmentId)->toBeNull();
});

it('un conteo sin diferencias se cierra con exactitud 100 % y sin ajustes', function () {
    $kit = countKit();
    $id = startedCount($kit);
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '4'], [5, 11, '2']]);
    $kit[4]($id, 8);

    expect($kit[5]($id, 9))->toBe(['adjustments' => 0, 'lines' => 3, 'accuracyPct' => '100.00']);
    expect($kit[0]->movements->requests)->toHaveCount(0);
});

it('cerrar dos veces no vuelve a ajustar', function () {
    $kit = countKit();
    $id = startedCount($kit);
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '3'], [5, 11, '2']]);
    $kit[4]($id, 8);
    $kit[3]($id, 6, 10, Quantity::of('3'), 8);

    $kit[5]($id, 9);
    $kit[5]($id, 9);

    expect($kit[0]->movements->requests)->toHaveCount(1);
});

it('si el ledger rechaza un ajuste, el conteo sigue en revisión y las ubicaciones congeladas', function () {
    $kit = countKit();
    $id = startedCount($kit);
    countAll($kit, $id, [[5, 10, '10'], [6, 10, '3'], [5, 11, '2']]);
    $kit[4]($id, 8);
    $kit[3]($id, 6, 10, Quantity::of('3'), 8);
    $kit[0]->movements->rejectProducts = [6];

    expect(fn () => $kit[5]($id, 9))->toThrow(OperationsException::class, 'Stock insuficiente');

    expect($kit[1]->counts[$id]['status'])->toBe(CountStatus::Review);
    expect($kit[1]->frozen)->toBe([10, 11]);
    expect($kit[0]->store->adjustments)->toBe([]);
});

it('anular descongela sin ajustar; un conteo cerrado no se anula', function () {
    $kit = countKit();
    $id = startedCount($kit);

    $kit[6]($id, 7);
    $kit[6]($id, 7);
    expect($kit[1]->counts[$id]['status'])->toBe(CountStatus::Cancelled);
    expect($kit[1]->frozen)->toBe([]);
    expect(fn () => $kit[3]($id, 5, 10, Quantity::of('1'), 8))->toThrow(OperationsException::class, 'ya no admite');

    $kit[1]->scope = new CountScope([12], [new CountBalance(5, 12, Quantity::of('1'))]);
    $second = $kit[2](new NewCount(1, CountScopeType::Full, null, 7))['id'];
    $kit[3]($second, 5, 12, Quantity::of('1'), 8);
    $kit[4]($second, 8);
    $kit[5]($second, 9);
    expect(fn () => $kit[6]($second, 7))->toThrow(OperationsException::class, 'cerrado');
});
