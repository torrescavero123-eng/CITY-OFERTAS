<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

enum ProductStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Discontinued = 'DISCONTINUED';
}
