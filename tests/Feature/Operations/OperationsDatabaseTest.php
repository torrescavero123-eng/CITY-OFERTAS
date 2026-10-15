<?php

declare(strict_types=1);

use App\Modules\Inventory\Application\GetKardex;
use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\KardexQuery;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Operations\Application\ApproveAdjustment;
use App\Modules\Operations\Application\CancelReceipt;
use App\Modules\Operations\Application\CompleteTransfer;
use App\Modules\Operations\Application\ConfirmReceipt;
use App\Modules\Operations\Application\CreateReceiptDraft;
use App\Modules\Operations\Application\CreateTransferDraft;
use App\Modules\Operations\Application\RecordDirectAdjustment;
use App\Modules\Operations\Application\RequestAdjustment;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\TransferLine;
use App\Modules\Suppliers\Application\CreateSupplier;
use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\Ruc;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\Support\MovementFactory as M;

function opsSupplier(): int
{
    return app(CreateSupplier::class)(new NewSupplier(Ruc::of('20611519886'), 'Importadora Lima SAC'));
}

function opsBodega(): int
{
    return (int) DB::table('warehouses')->where('code', 'BOD')->value('id');
}

function opsReceipt(int $supplier, int $actor, array $lines, string $number = 'F001-234'): NewReceipt
{
    return new NewReceipt($supplier, opsBodega(), DocumentType::Invoice, $number, now('America/Lima')->format('Y-m-d'), $actor, $lines);
}

function opsLine(int $product, int $loc, string $qty, string $cost = '2'): ReceiptLine
{
    return new ReceiptLine($product, $loc, Quantity::of($qty), UnitCost::of($cost));
}

it('la recepción confirmada deja stock, costo promedio, movimientos enlazados y documento', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    $supplier = opsSupplier();

    $draft = app(CreateReceiptDraft::class)(opsReceipt($supplier, $actor, [opsLine($product, $loc, '30', '2.5')]));
    expect($draft['code'])->toBe('REC-'.now('America/Lima')->format('Y').'-000001');
    expect(Fixtures::balance($product, $loc))->toBe('0'); // el borrador no toca el stock

    $result = app(ConfirmReceipt::class)($draft['id'], $actor);

    expect($result)->toBe(['movements' => 1, 'discrepancies' => 0]);
    expect(Fixtures::balance($product, $loc))->toBe('30.000');
    expect((string) DB::table('products')->where('id', $product)->value('cost_avg'))->toBe('2.5000');
    expect(DB::table('purchase_receipts')->where('id', $draft['id'])->value('status'))->toBe('CONFIRMED');
    $item = DB::table('purchase_receipt_items')->where('receipt_id', $draft['id'])->first();
    $movement = DB::table('stock_movements')->where('id', $item->movement_id)->first();
    expect($movement->document_ref)->toBe('FACTURA F001-234');
    expect($movement->source_type)->toBe('RECEIPT');
    expect((int) $movement->source_id)->toBe($draft['id']);
});

it('confirmar dos veces no duplica movimientos ni stock', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    $draft = app(CreateReceiptDraft::class)(opsReceipt(opsSupplier(), $actor, [opsLine($product, $loc, '10')]));

    app(ConfirmReceipt::class)($draft['id'], $actor);
    app(ConfirmReceipt::class)($draft['id'], $actor);

    expect(DB::table('stock_movements')->where('product_id', $product)->count())->toBe(1);
    expect(Fixtures::balance($product, $loc))->toBe('10.000');
});

it('una recepción por cajas guarda la presentación y entra en unidad base', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    $presentationId = (int) DB::table('product_units')->insertGetId([
        'product_id' => $product, 'unit_id' => DB::table('units')->where('code', 'DOC')->value('id'), 'factor_to_base' => '12',
    ], 'id');
    $line = ReceiptLine::fromPresentation($product, $loc, $presentationId, Quantity::of('5'), '12', '24', Quantity::of('6'));
    $draft = app(CreateReceiptDraft::class)(opsReceipt(opsSupplier(), $actor, [$line]));

    $result = app(ConfirmReceipt::class)($draft['id'], $actor);

    expect($result['discrepancies'])->toBe(1); // esperaba 6 docenas, llegaron 5
    expect(Fixtures::balance($product, $loc))->toBe('60.000');
    expect((string) DB::table('products')->where('id', $product)->value('cost_avg'))->toBe('2.0000');
    $movement = DB::table('stock_movements')->where('product_id', $product)->first();
    expect((int) $movement->presentation_id)->toBe($presentationId);
    expect((string) $movement->presentation_qty)->toBe('5.000');
});

it('no se registra dos veces la misma factura del proveedor, salvo que se anule', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    $supplier = opsSupplier();
    $create = app(CreateReceiptDraft::class);

    $first = $create(opsReceipt($supplier, $actor, [opsLine($product, $loc, '1')]));
    expect(fn () => $create(opsReceipt($supplier, $actor, [opsLine($product, $loc, '1')])))->toThrow(OperationsException::class, 'Ya se registró');

    app(CancelReceipt::class)($first['id'], $actor);
    expect($create(opsReceipt($supplier, $actor, [opsLine($product, $loc, '1')]))['code'])->toEndWith('-000002');
});

it('si una línea de la transferencia no tiene stock, no se mueve ninguna', function () {
    $actor = Fixtures::user();
    $a = Fixtures::product();
    $b = Fixtures::product();
    $from = Fixtures::location('BOD');
    $to = Fixtures::location('PIS');
    $register = app(RegisterMovement::class);
    $register(M::purchase($a, $from, 20, '1', $actor));
    $register(M::purchase($b, $from, 1, '1', $actor));

    $transfer = app(CreateTransferDraft::class)(new NewTransfer($actor, [
        new TransferLine($a, $from, $to, Quantity::of('5')),
        new TransferLine($b, $from, $to, Quantity::of('9')), // solo hay 1
    ]));

    expect(fn () => app(CompleteTransfer::class)($transfer['id'], $actor))->toThrow(OperationsException::class, 'Stock insuficiente');

    expect(Fixtures::balance($a, $from))->toBe('20.000');
    expect(Fixtures::balance($a, $to))->toBe('0');
    expect(DB::table('transfer_orders')->where('id', $transfer['id'])->value('status'))->toBe('DRAFT');
});

it('la transferencia completa mueve el stock y enlaza los movimientos', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $from = Fixtures::location('BOD');
    $to = Fixtures::location('PIS');
    app(RegisterMovement::class)(M::purchase($product, $from, 20, '1', $actor));
    $transfer = app(CreateTransferDraft::class)(new NewTransfer($actor, [new TransferLine($product, $from, $to, Quantity::of('5'))]));

    app(CompleteTransfer::class)($transfer['id'], $actor);
    app(CompleteTransfer::class)($transfer['id'], $actor); // idempotente

    expect(Fixtures::balance($product, $from))->toBe('15.000');
    expect(Fixtures::balance($product, $to))->toBe('5.000');
    expect(DB::table('stock_movements')->where('product_id', $product)->where('type', 'TRANSFERENCIA')->count())->toBe(1);
    expect(DB::table('transfer_order_items')->where('order_id', $transfer['id'])->value('movement_id'))->not->toBeNull();
    expect(DB::table('transfer_orders')->where('id', $transfer['id'])->value('status'))->toBe('COMPLETED');
    expect(DB::select('SELECT * FROM fn_reconcile_ledger()'))->toHaveCount(0);
});

it('una merma solicitada por un almacenero solo descuenta cuando otra persona la aprueba', function () {
    $requester = Fixtures::user();
    $reviewer = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($product, $loc, 10, '4', $requester));
    $reason = (int) DB::table('movement_reasons')->where('code', 'MERMA_DANIO')->value('id');

    $request = app(RequestAdjustment::class)(new NewAdjustment(AdjustmentKind::WriteOff, $product, $loc, Quantity::of('3'), $reason, 'Se mojaron con la lluvia', $requester));
    expect(Fixtures::balance($product, $loc))->toBe('10.000');
    expect(fn () => app(ApproveAdjustment::class)($request['id'], $requester))->toThrow(OperationsException::class, 'otra persona');

    $movementId = app(ApproveAdjustment::class)($request['id'], $reviewer, 'Verificado en bodega');

    expect(Fixtures::balance($product, $loc))->toBe('7.000');
    $movement = DB::table('stock_movements')->where('id', $movementId)->first();
    expect($movement->type)->toBe('MERMA');
    expect((int) $movement->user_id)->toBe($requester);
    expect((int) $movement->approved_by)->toBe($reviewer);
    expect((string) $movement->unit_cost)->toBe('4.0000'); // valorizada al costo promedio
    expect(DB::table('stock_adjustment_requests')->where('id', $request['id'])->value('status'))->toBe('APPROVED');
});

it('una merma sin stock suficiente no se aprueba y la solicitud queda pendiente', function () {
    $requester = Fixtures::user();
    $reviewer = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($product, $loc, 2, '1', $requester));
    $reason = (int) DB::table('movement_reasons')->where('code', 'MERMA_DANIO')->value('id');
    $request = app(RequestAdjustment::class)(new NewAdjustment(AdjustmentKind::WriteOff, $product, $loc, Quantity::of('5'), $reason, 'Se dañaron todas', $requester));

    expect(fn () => app(ApproveAdjustment::class)($request['id'], $reviewer))->toThrow(OperationsException::class, 'Stock insuficiente');

    expect(DB::table('stock_adjustment_requests')->where('id', $request['id'])->value('status'))->toBe('PENDING');
    expect(Fixtures::balance($product, $loc))->toBe('2.000');
});

it('el registro directo respeta el límite configurado', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($product, $loc, 10, '2', $actor));
    $reason = (int) DB::table('movement_reasons')->where('code', 'AJU_ERROR_CONTEO')->value('id');
    $adjustment = new NewAdjustment(AdjustmentKind::Negative, $product, $loc, Quantity::of('2'), $reason, 'Diferencia en el conteo', $actor);
    $direct = app(RecordDirectAdjustment::class);

    expect(fn () => $direct($adjustment, 'DIRECT-TEST-0001'))->toThrow(OperationsException::class, 'límite'); // por defecto: deshabilitado

    DB::table('settings')->where('key', 'adjustment.auto_approve_limit_pen')->update(['value' => '50']);
    $direct($adjustment, 'DIRECT-TEST-0001');
    $direct($adjustment, 'DIRECT-TEST-0001'); // reintento: no duplica

    expect(Fixtures::balance($product, $loc))->toBe('8.000');
});

it('el kardex muestra saldo inicial, movimientos con saldo corrido y saldo final', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $a = Fixtures::location('BOD');
    $b = Fixtures::location('BOD');
    $register = app(RegisterMovement::class);
    $register(M::purchase($product, $a, 20, '2', $actor));
    $register(M::transfer($product, $a, $b, 5, $actor));
    $register(M::sale($product, $b, 3, $actor));
    $kardex = app(GetKardex::class);

    $warehouse = $kardex(new KardexQuery($product, warehouseId: opsBodega()));
    expect($warehouse->openingBalance)->toBe('0.000');
    expect(array_map(static fn ($r) => $r->delta, $warehouse->rows))->toBe(['20.000', '-5.000', '5.000', '-3.000']);
    expect(array_map(static fn ($r) => $r->runningBalance, $warehouse->rows))->toBe(['20.000', '15.000', '20.000', '17.000']);
    expect($warehouse->closingBalance)->toBe('17.000');
    expect($warehouse->rows[0]->type)->toBe('ENTRADA_COMPRA');

    $location = $kardex(new KardexQuery($product, locationId: $a));
    expect(array_map(static fn ($r) => $r->runningBalance, $location->rows))->toBe(['20.000', '15.000']);
    expect($location->closingBalance)->toBe('15.000');

    $later = $kardex(new KardexQuery($product, from: new DateTimeImmutable('+1 day')));
    expect($later->rows)->toBe([]);
    expect($later->openingBalance)->toBe('17.000');
    expect($later->closingBalance)->toBe('17.000');
});
