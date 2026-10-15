<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum CountStatus: string
{
    case Open = 'OPEN';
    case Counting = 'COUNTING';
    case Review = 'REVIEW';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';
}
