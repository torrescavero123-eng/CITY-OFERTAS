<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditResource::class;
}
