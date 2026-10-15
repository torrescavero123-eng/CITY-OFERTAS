<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property int $depth
 * @property bool $is_active
 */
class Category extends Model
{
    use RecordsAudit;

    protected $table = 'categories';

    /** @var list<string> */
    protected $fillable = ['parent_id', 'code', 'name', 'depth', 'sort_order', 'is_active'];

    protected static function booted(): void
    {
        // Reglas del árbol: hasta 3 niveles; las de primer nivel exigen un código único (prefijo del SKU).
        static::creating(static function (Category $category): void {
            $category->code = strtoupper(trim((string) $category->code));

            if ($category->parent_id === null) {
                if (preg_match('/^[A-Z0-9]{2,4}$/', $category->code) !== 1) {
                    throw ValidationException::withMessages(['data.code' => 'Las categorías de primer nivel necesitan un código de 2 a 4 letras o números.']);
                }
                if (DB::table('categories')->whereNull('parent_id')->where('code', $category->code)->exists()) {
                    throw ValidationException::withMessages(['data.code' => 'Ese código ya lo usa otra categoría de primer nivel.']);
                }
                $category->depth = 1;

                return;
            }

            $parentDepth = (int) DB::table('categories')->where('id', $category->parent_id)->value('depth');
            if ($parentDepth < 1 || $parentDepth >= 3) {
                throw ValidationException::withMessages(['data.parent_id' => 'Solo se permiten 3 niveles de categorías.']);
            }
            $category->depth = $parentDepth + 1;
        });
    }

    protected static function auditEntity(): string
    {
        return 'category';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
