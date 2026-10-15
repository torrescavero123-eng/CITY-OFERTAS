<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

enum Symbology: string
{
    case Ean13 = 'EAN13';
    case Ean8 = 'EAN8';
    case UpcA = 'UPCA';
    case Code128 = 'CODE128';
    case Qr = 'QR';
    case Internal = 'INTERNAL';
}
