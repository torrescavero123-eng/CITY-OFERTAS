<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de LECTURA de la auditoría (la tabla es inmutable: ni siquiera la base permite modificarla).
 *
 * @property int $id
 * @property string $action
 * @property string $entity_type
 * @property int|null $entity_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
