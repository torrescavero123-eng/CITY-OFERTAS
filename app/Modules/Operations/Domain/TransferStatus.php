<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum TransferStatus: string
{
    case Draft = 'DRAFT';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
