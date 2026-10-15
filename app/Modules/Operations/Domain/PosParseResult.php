<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class PosParseResult
{
    /**
     * @param  list<PosSaleRow>  $rows
     * @param  list<ImportError>  $errors
     */
    public function __construct(public array $rows, public array $errors, public int $totalRows) {}
}
