<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;

it('registra una entrada con valores antes y después', function () {
    $actor = Fixtures::user();

    app(AuditTrail::class)->record(new AuditEntry('product.updated', 'product', 10, $actor, ['name' => 'A'], ['name' => 'B']));

    $row = DB::table('audit_logs')->where('entity_id', 10)->where('action', 'product.updated')->first();
    expect($row->actor_id)->toBe($actor);
    expect(json_decode((string) $row->old_values, true))->toBe(['name' => 'A']);
    expect(json_decode((string) $row->new_values, true))->toBe(['name' => 'B']);
});

it('la auditoría es inmutable: UPDATE y DELETE son rechazados por la BD', function () {
    app(AuditTrail::class)->record(new AuditEntry('x.created', 'x', 1));

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['action' => 'borrado'])))
        ->toThrow(QueryException::class, 'inmutable');
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))
        ->toThrow(QueryException::class, 'inmutable');
});
