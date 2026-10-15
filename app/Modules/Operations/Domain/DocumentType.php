<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum DocumentType: string
{
    case Invoice = 'FACTURA';
    case DeliveryNote = 'GUIA_REMISION';
    case Receipt = 'BOLETA';
    case Other = 'OTRO';
}
