<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Barcode;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\Symbology;

it('acepta códigos GS1 válidos', function () {
    expect(Barcode::of('4006381333931', Symbology::Ean13)->value)->toBe('4006381333931');
    expect(Barcode::of('96385074', Symbology::Ean8)->value)->toBe('96385074');
    expect(Barcode::of('036000291452', Symbology::UpcA)->value)->toBe('036000291452');
});

it('rechaza dígito verificador incorrecto', function () {
    expect(fn () => Barcode::of('4006381333932', Symbology::Ean13))->toThrow(CatalogException::class, 'verificador');
    expect(fn () => Barcode::of('96385075', Symbology::Ean8))->toThrow(CatalogException::class, 'verificador');
});

it('rechaza longitud o caracteres incorrectos', function () {
    expect(fn () => Barcode::of('400638133393', Symbology::Ean13))->toThrow(CatalogException::class, '13 dígitos');
    expect(fn () => Barcode::of('40063813339AB', Symbology::Ean13))->toThrow(CatalogException::class, '13 dígitos');
});

it('acepta códigos internos y Code128 imprimibles sin espacios', function () {
    expect(Barcode::of(' CITY-000231 ', Symbology::Internal)->value)->toBe('CITY-000231');
    expect(fn () => Barcode::of('con espacio', Symbology::Code128))->toThrow(CatalogException::class);
    expect(fn () => Barcode::of('', Symbology::Internal))->toThrow(CatalogException::class);
});
