<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

/** UUID derivado de la clave de idempotencia: reintentar produce siempre el mismo movimiento. */
final class DeterministicUuid
{
    public static function fromKey(string $key): string
    {
        $h = sha1('wms:'.$key);
        $timeHi = (hexdec(substr($h, 12, 4)) & 0x0FFF) | 0x5000;
        $clock = (hexdec(substr($h, 16, 4)) & 0x3FFF) | 0x8000;

        return sprintf('%s-%s-%04x-%04x-%s', substr($h, 0, 8), substr($h, 8, 4), $timeHi, $clock, substr($h, 20, 12));
    }
}
