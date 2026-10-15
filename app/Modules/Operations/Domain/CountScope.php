<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class CountScope
{
    /**
     * @param  list<int>  $locationIds  ubicaciones que se congelan
     * @param  list<CountBalance>  $balances  stock esperado (solo saldos > 0)
     */
    public function __construct(public array $locationIds, public array $balances) {}
}
