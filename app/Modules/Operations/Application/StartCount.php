<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\CountLocations;
use App\Modules\Operations\Domain\CountRepository;
use App\Modules\Operations\Domain\CountScopeResolver;
use App\Modules\Operations\Domain\DocumentNumbers;
use App\Modules\Operations\Domain\NewCount;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

/** Inicia el conteo: fija el stock esperado (foto) y congela las ubicaciones para que nada lo altere mientras se cuenta. */
final class StartCount
{
    public function __construct(
        private readonly CountRepository $counts,
        private readonly CountScopeResolver $scopes,
        private readonly CountLocations $locations,
        private readonly OperationsLookup $lookup,
        private readonly DocumentNumbers $numbers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {}

    /** @return array{id: int, code: string, locations: int, lines: int} */
    public function __invoke(NewCount $count): array
    {
        return $this->transaction->run(function () use ($count): array {
            if (! $this->lookup->warehouseIsActive($count->warehouseId)) {
                throw OperationsException::warehouseNotFound();
            }

            $scope = $this->scopes->resolve($count->warehouseId, $count->scopeType, $count->scopeValue);
            if ($scope->locationIds === []) {
                throw OperationsException::invalid('El alcance elegido no tiene ubicaciones para contar.');
            }

            $code = $this->numbers->next('CNT');
            $this->locations->freeze($scope->locationIds, $count->openedBy, $code); // falla si otra operación ya las congeló
            $id = $this->counts->insertCount($count, $code, $scope, $this->clock->now());

            $this->audit->record(new AuditEntry('count.started', 'count', $id, $count->openedBy, null, [
                'code' => $code, 'scope' => $count->scopeType->value, 'value' => $count->scopeValue,
                'locations' => count($scope->locationIds), 'lines' => count($scope->balances),
            ]));

            return ['id' => $id, 'code' => $code, 'locations' => count($scope->locationIds), 'lines' => count($scope->balances)];
        });
    }
}
