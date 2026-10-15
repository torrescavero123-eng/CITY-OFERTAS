<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('una categoría de primer nivel exige código único y queda en nivel 1', function () {
    $c = Category::query()->create(['name' => 'Papelería', 'code' => ' pap ']);

    expect($c->code)->toBe('PAP');
    expect($c->depth)->toBe(1);
    expect(fn () => Category::query()->create(['name' => 'Otra', 'code' => 'PAP']))->toThrow(ValidationException::class);
    expect(fn () => Category::query()->create(['name' => 'Sin código', 'code' => '']))->toThrow(ValidationException::class);
});

it('las subcategorías heredan el nivel y se limitan a 3 niveles', function () {
    $root = Category::query()->create(['name' => 'Papelería', 'code' => 'PAP']);
    $child = Category::query()->create(['name' => 'Cuadernos', 'parent_id' => $root->id, 'code' => '']);
    $grandchild = Category::query()->create(['name' => 'Rayados', 'parent_id' => $child->id, 'code' => '']);

    expect($child->depth)->toBe(2);
    expect($grandchild->depth)->toBe(3);
    expect(fn () => Category::query()->create(['name' => 'Demasiado', 'parent_id' => $grandchild->id, 'code' => '']))
        ->toThrow(ValidationException::class);
});

it('categorías y marcas dejan rastro de auditoría con antes y después', function () {
    $brand = Brand::query()->create(['name' => 'Faber']);
    $brand->update(['name' => 'Faber-Castell']);

    expect(DB::table('audit_logs')->where('action', 'brand.created')->where('entity_id', $brand->id)->count())->toBe(1);
    $row = DB::table('audit_logs')->where('action', 'brand.updated')->where('entity_id', $brand->id)->first();
    expect(json_decode((string) $row->old_values, true))->toBe(['name' => 'Faber']);
    expect(json_decode((string) $row->new_values, true))->toBe(['name' => 'Faber-Castell']);
});
