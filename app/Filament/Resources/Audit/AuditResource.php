<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit;

use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AuditResource extends Resource
{
    /** Nombres legibles de las acciones más frecuentes (las demás se muestran con su código). */
    public const ACTION_LABELS = [
        'product.created' => 'Producto creado', 'product.updated' => 'Producto editado', 'product.deactivated' => 'Producto dado de baja',
        'category.created' => 'Categoría creada', 'category.updated' => 'Categoría editada', 'brand.created' => 'Marca creada', 'brand.updated' => 'Marca editada',
        'user.created' => 'Usuario creado', 'user.deactivated' => 'Usuario desactivado', 'user.activated' => 'Usuario reactivado',
        'user.password_reset' => 'Contraseña restablecida', 'user.password_reset_cli' => 'Contraseña restablecida (consola)',
        'user.login_failed' => 'Intento de acceso fallido', 'user.locked' => 'Cuenta bloqueada',
        'receipt.confirmed' => 'Recepción confirmada', 'receipt.cancelled' => 'Recepción anulada',
        'transfer.completed' => 'Transferencia completada', 'transfer.cancelled' => 'Transferencia anulada',
        'adjustment.requested' => 'Ajuste solicitado', 'adjustment.approved' => 'Ajuste aprobado', 'adjustment.rejected' => 'Ajuste rechazado',
        'count.started' => 'Conteo iniciado', 'count.submitted' => 'Conteo enviado a revisión', 'count.closed' => 'Conteo cerrado', 'count.cancelled' => 'Conteo anulado',
        'pos.imported' => 'Ventas del POS importadas', 'pos.simulated' => 'Ventas del POS simuladas',
        'location.frozen_bulk' => 'Ubicaciones congeladas', 'location.unfrozen_bulk' => 'Ubicaciones descongeladas',
        'supplier.created' => 'Proveedor creado', 'supplier.updated' => 'Proveedor editado',
    ];

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'auditoría del sistema';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s', 'America/Lima')->sortable(),
                TextColumn::make('actor.name')->label('Quién')->placeholder('Sistema')->searchable(),
                TextColumn::make('action')->label('Qué')->searchable()
                    ->formatStateUsing(static fn (string $state): string => self::ACTION_LABELS[$state] ?? $state)
                    ->description(static fn (AuditLog $record): string => $record->action),
                TextColumn::make('entity_type')->label('Sobre')->formatStateUsing(static fn (string $state, AuditLog $record): string => $state.($record->entity_id !== null ? " #{$record->entity_id}" : '')),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('Acción')->searchable()
                    ->options(static fn (): array => DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action')
                        ->mapWithKeys(static fn ($a): array => [(string) $a => self::ACTION_LABELS[(string) $a] ?? (string) $a])->all()),
                SelectFilter::make('actor_id')->label('Persona')->searchable()
                    ->options(static fn (): array => DB::table('users')->orderBy('name')->pluck('name', 'id')->map(static fn ($n): string => (string) $n)->all()),
                Filter::make('period')->label('Período')
                    ->schema([DatePicker::make('from')->label('Desde'), DatePicker::make('until')->label('Hasta')])
                    ->query(static fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, static fn (Builder $q, $d): Builder => $q->where('created_at', '>=', $d.' 00:00:00-05'))
                        ->when($data['until'] ?? null, static fn (Builder $q, $d): Builder => $q->where('created_at', '<=', $d.' 23:59:59-05'))),
            ])
            ->recordActions([
                Action::make('detail')->label('Ver detalle')->icon('heroicon-o-eye')->color('gray')
                    ->modalHeading(static fn (AuditLog $record): string => self::ACTION_LABELS[$record->action] ?? $record->action)
                    ->modalWidth('4xl')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                    ->modalContent(static fn (AuditLog $record): View => view('filament.audit-detail', ['log' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aún no hay registros');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('actor');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
