<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum ReceiptStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
}
