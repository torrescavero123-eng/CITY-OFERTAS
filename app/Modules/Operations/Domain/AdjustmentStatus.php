<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum AdjustmentStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
