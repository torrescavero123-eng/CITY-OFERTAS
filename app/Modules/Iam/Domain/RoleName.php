<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

enum RoleName: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Almacenero = 'almacenero';
    case Compras = 'compras';
    case Lectura = 'lectura';
}
