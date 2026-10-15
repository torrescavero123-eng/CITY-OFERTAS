<?php

declare(strict_types=1);

namespace App\Filament\Resources\Counts;

use App\Filament\Resources\Counts\Pages\ListCounts;
use App\Filament\Support\SearchOptions;
use App\Models\StockCount;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Application\CancelCount;
use App\Modules\Operations\Application\CloseCount;
use App\Modules\Operations\Application\RecordCount;
use App\Modules\Operations\Application\SubmitCountForReview;
use App\Modules\Operations\Domain\OperationsException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CountResource extends Resource
{
    protected static ?string $model = StockCount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Almacén';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Conteos';

    protected static ?string $modelLabel = 'conteo';

    protected static ?string $pluralModelLabel = 'conteos de inventario';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('warehouse_name')->label('Almacén'),
                TextColumn::make('scope_type')->label('Alcance')
                    ->formatStateUsing(static fn (string $state, StockCount $record): string => (['FULL' => 'Almacén completo', 'ZONE' => 'Zona', 'LOCATION' => 'Ubicación', 'CATEGORY' => 'Categoría', 'ABC_A' => 'Clase A'][$state] ?? $state).($record->scope_value !== null ? " {$record->scope_value}" : '')),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(static fn (string $state): string => ['OPEN' => 'Abierto', 'COUNTING' => 'Contando', 'REVIEW' => 'En revisión', 'CLOSED' => 'Cerrado', 'CANCELLED' => 'Anulado'][$state] ?? $state)
                    ->color(static fn (string $state): string => ['COUNTING' => 'warning', 'REVIEW' => 'info', 'CLOSED' => 'success', 'CANCELLED' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('lines_counted')->label('Contadas')->alignEnd()
                    ->formatStateUsing(static fn ($state, StockCount $record): string => $state.' / '.$record->getAttribute('lines_total')),
                TextColumn::make('lines_diff')->label('Con diferencia')->alignEnd()->badge()
                    ->color(static fn ($state): string => (int) $state > 0 ? 'warning' : 'success'),
                TextColumn::make('recounts_pending')->label('Reconteos pendientes')->alignEnd(),
                TextColumn::make('opener.name')->label('Inició'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i', 'America/Lima')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')
                    ->options(['COUNTING' => 'Contando', 'REVIEW' => 'En revisión', 'CLOSED' => 'Cerrado', 'CANCELLED' => 'Anulado']),
            ])
            ->recordActions([
                Action::make('detail')->label('Ver líneas')->icon('heroicon-o-list-bullet')->color('gray')
                    ->modalHeading(static fn (StockCount $record): string => 'Conteo '.$record->code)
                    ->modalWidth('7xl')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                    ->modalContent(static fn (StockCount $record): View => view('filament.count-lines', [
                        'count' => $record,
                        'showExpected' => auth()->user()?->can('counts.close') ?? false,
                        'lines' => DB::select(
                            'SELECT l.id, l.expected_qty::text AS expected, l.counted_qty::text AS counted, l.difference::text AS difference, l.recount_requested,
                                    p.sku, p.name, loc.code AS location_code
                               FROM stock_count_lines l
                               JOIN products p ON p.id = l.product_id
                               JOIN locations loc ON loc.id = l.location_id
                              WHERE l.count_id = ?
                              ORDER BY loc.code, p.sku',
                            [$record->id],
                        ),
                    ])),
                Action::make('count')->label('Contar')->icon('heroicon-o-calculator')->color('primary')
                    ->modalHeading('Registrar lo contado')
                    ->modalDescription('Cuente el producto en esa ubicación. Si lo vuelve a contar, se reemplaza el valor. Si no hay nada, ponga 0.')
                    ->visible(static fn (StockCount $record): bool => in_array($record->status, ['COUNTING', 'REVIEW'], true) && (auth()->user()?->can('counts.run') ?? false))
                    ->schema([
                        Select::make('product_id')->label('Producto (SKU, nombre o código de barras)')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => SearchOptions::products($search))
                            ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::productLabel($value)),
                        Select::make('location_id')->label('Ubicación')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => SearchOptions::locations($search))
                            ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::locationLabel($value)),
                        TextInput::make('quantity')->label('Cantidad contada')->numeric()->minValue(0)->required(),
                    ])
                    ->action(static function (array $data, StockCount $record): void {
                        try {
                            app(RecordCount::class)((int) $record->id, (int) $data['product_id'], (int) $data['location_id'], Quantity::of((string) $data['quantity']), (int) auth()->id());
                            Notification::make()->title('Conteo registrado')->success()->send();
                        } catch (OperationsException|InventoryException $e) {
                            Notification::make()->title('No se pudo registrar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('submit')->label('Enviar a revisión')->icon('heroicon-o-paper-airplane')->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Todas las líneas deben estar contadas (no contar NO significa cero). Las que tengan diferencia quedarán marcadas para reconteo.')
                    ->visible(static fn (StockCount $record): bool => $record->status === 'COUNTING' && (auth()->user()?->can('counts.run') ?? false))
                    ->action(static function (StockCount $record): void {
                        try {
                            $r = app(SubmitCountForReview::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Enviado a revisión')->body("Líneas: {$r['lines']}. Con diferencia (a recontar): {$r['withDifference']}.")->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo enviar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('close')->label('Cerrar y ajustar')->icon('heroicon-o-lock-closed')->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar el conteo')
                    ->modalDescription('Se aplicarán las diferencias al stock, se descongelarán las ubicaciones y se calculará la exactitud del inventario. Quien contó las diferencias no puede cerrarlo.')
                    ->visible(static fn (StockCount $record): bool => $record->status === 'REVIEW' && (auth()->user()?->can('counts.close') ?? false))
                    ->action(static function (StockCount $record): void {
                        try {
                            $r = app(CloseCount::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Conteo cerrado')->body("Ajustes aplicados: {$r['adjustments']}. Exactitud del inventario: {$r['accuracyPct']} %.")->success()->persistent()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo cerrar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('cancel')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Se descongelan las ubicaciones y no se ajusta nada.')
                    ->visible(static fn (StockCount $record): bool => in_array($record->status, ['COUNTING', 'REVIEW'], true) && (auth()->user()?->can('counts.close') ?? false))
                    ->action(static function (StockCount $record): void {
                        try {
                            app(CancelCount::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Conteo anulado')->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo anular')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aún no hay conteos')
            ->emptyStateDescription('Inicie uno con "Iniciar conteo": se congela la zona y se fija el stock esperado.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('stock_counts.*')
            ->addSelect([
                'warehouse_name' => DB::table('warehouses')->select('name')->whereColumn('warehouses.id', 'stock_counts.warehouse_id'),
                'lines_total' => DB::table('stock_count_lines')->selectRaw('COUNT(*)')->whereColumn('stock_count_lines.count_id', 'stock_counts.id'),
                'lines_counted' => DB::table('stock_count_lines')->selectRaw('COUNT(*)')->whereColumn('stock_count_lines.count_id', 'stock_counts.id')->whereNotNull('counted_qty'),
                'lines_diff' => DB::table('stock_count_lines')->selectRaw('COUNT(*)')->whereColumn('stock_count_lines.count_id', 'stock_counts.id')->whereRaw('counted_qty IS NOT NULL AND counted_qty <> expected_qty'),
                'recounts_pending' => DB::table('stock_count_lines')->selectRaw('COUNT(*)')->whereColumn('stock_count_lines.count_id', 'stock_counts.id')->where('recount_requested', true),
            ])
            ->with('opener');
    }

    public static function getPages(): array
    {
        return ['index' => ListCounts::route('/')];
    }
}
