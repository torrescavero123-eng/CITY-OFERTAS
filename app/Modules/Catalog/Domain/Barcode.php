<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/** Código de barras validado (dígito verificador GS1 para EAN-13, EAN-8 y UPC-A). */
final readonly class Barcode
{
    private function __construct(public string $value, public Symbology $symbology) {}

    public static function of(string $value, Symbology $symbology): self
    {
        $code = trim($value);

        $length = match ($symbology) {
            Symbology::Ean13 => 13,
            Symbology::Ean8 => 8,
            Symbology::UpcA => 12,
            default => null,
        };

        if ($length !== null) {
            if (preg_match('/^\d{'.$length.'}$/', $code) !== 1) {
                throw CatalogException::invalid("El código {$symbology->value} debe tener {$length} dígitos.");
            }
            if (! self::hasValidCheckDigit($code)) {
                throw CatalogException::invalid("El dígito verificador del código {$code} no es válido.");
            }
        } elseif (preg_match('/^[\x21-\x7E]{1,40}$/', $code) !== 1) {
            throw CatalogException::invalid('El código debe tener de 1 a 40 caracteres imprimibles, sin espacios.');
        }

        return new self($code, $symbology);
    }

    private static function hasValidCheckDigit(string $code): bool
    {
        $payload = substr($code, 0, -1);
        $sum = 0;
        $n = strlen($payload);

        for ($i = $n - 1; $i >= 0; $i--) {
            $weight = (($n - 1 - $i) % 2 === 0) ? 3 : 1; // el dígito más cercano al verificador pesa 3
            $sum += (int) $payload[$i] * $weight;
        }

        return ((10 - ($sum % 10)) % 10) === (int) substr($code, -1);
    }
}
