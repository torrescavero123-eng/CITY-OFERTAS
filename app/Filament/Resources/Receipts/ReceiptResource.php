<?php

declare(strict_types=1);

namespace App\Filament\Resources\Receipts;

use App\Filament\Resources\Products\CatalogOptions;
use App\Filament\Resources\Receipts\Pages\CreateReceipt;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Support\SearchOptions;
use App\Models\PurchaseReceipt;
use App\Modules\Operations\Application\CancelReceipt;
use App\Modules\Operations\Application\ConfirmReceipt;
use App\Modules\Operations\Domain\OperationsException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'Almacén';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Recepciones';

    protected static ?string $modelLabel = 'recepción';

    protected static ?string $pluralModelLabel = 'recepciones';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Documento del proveedor')->schema([
                Select::make('supplier_id')->label('Proveedor')->options(fn (): array => CatalogOptions::suppliers())->searchable()->required(),
                Select::make('warehouse_id')->label('Almacén de destino')->options(fn (): array => CatalogOptions::warehouses())->required(),
                Select::make('doc_type')->label('Tipo de documento')->required()->default('FACTURA')
                    ->options(['FACTURA' => 'Factura', 'GUIA_REMISION' => 'Guía de remisión', 'BOLETA' => 'Boleta', 'OTRO' => 'Otro']),
                TextInput::make('doc_number')->label('Número')->required()->maxLength(30)->placeholder('F001-000234'),
                DatePicker::make('doc_date')->label('Fecha del documento')->required()->default(now('America/Lima')),
                Textarea::make('notes')->label('Observaciones')->rows(2)->maxLength(500),
            ])->columns(2),

            Section::make('Mercadería recibida')
                ->description('Cantidades en unidad base. La ubicación debe pertenecer al almacén elegido (use "BOD-REC" si aún no la ubica). El costo es por unidad, sin IGV.')
                ->schema([
                    Repeater::make('lines')->hiddenLabel()->defaultItems(1)->minItems(1)->maxItems(500)->addActionLabel('Agregar línea')
                        ->schema([
                            Select::make('product_id')->label('Producto')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => SearchOptions::products($search))
                                ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::productLabel($value)),
                            Select::make('location_id')->label('Ubicación')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => SearchOptions::locations($search))
                                ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::locationLabel($value)),
                            TextInput::make('qty_expected')->label('Esperado')->numeric()->minValue(0),
                            TextInput::make('qty_received')->label('Recibido')->numeric()->minValue(0)->required(),
                            TextInput::make('unit_cost')->label('Costo unitario')->numeric()->minValue(0)->prefix('S/')->required(),
                        ])->columns(5),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('supplier.business_name')->label('Proveedor')->searchable(),
                TextColumn::make('doc_number')->label('Documento')->searchable()
                    ->description(static fn (PurchaseReceipt $record): string => match ($record->doc_type) {
                        'FACTURA' => 'Factura', 'GUIA_REMISION' => 'Guía de remisión', 'BOLETA' => 'Boleta', default => 'Otro',
                    }),
                TextColumn::make('doc_date')->label('Fecha doc.')->date('d/m/Y')->sortable(),
                TextColumn::make('items_count')->label('Líneas')->alignEnd(),
                TextColumn::make('total_cost')->label('Valor (sin IGV)')->alignEnd()
                    ->formatStateUsing(static fn ($state): string => 'S/ '.number_format((float) $state, 2, '.', ',')),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(static fn (string $state): string => ['DRAFT' => 'Borrador', 'CONFIRMED' => 'Confirmada', 'CANCELLED' => 'Anulada'][$state] ?? $state)
                    ->color(static fn (string $state): string => ['DRAFT' => 'gray', 'CONFIRMED' => 'success', 'CANCELLED' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Creada')->dateTime('d/m/Y H:i', 'America/Lima')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(['DRAFT' => 'Borrador', 'CONFIRMED' => 'Confirmada', 'CANCELLED' => 'Anulada']),
            ])
            ->recordActions([
                Action::make('confirm')->label('Confirmar')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar recepción')
                    ->modalDescription('Se registrarán todas las entradas en el stock. No se puede deshacer: un error se corrige con un ajuste.')
                    ->visible(static fn (PurchaseReceipt $record): bool => $record->status === 'DRAFT' && (auth()->user()?->can('inventory.receive') ?? false))
                    ->action(static function (PurchaseReceipt $record): void {
                        try {
                            $result = app(ConfirmReceipt::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Recepción confirmada')
                                ->body("Entradas registradas: {$result['movements']}.".($result['discrepancies'] > 0 ? " Líneas con diferencia contra lo esperado: {$result['discrepancies']}." : ''))
                                ->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo confirmar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('cancel')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular borrador')
                    ->modalDescription('Solo se anulan borradores. Una recepción confirmada se corrige con un ajuste.')
                    ->visible(static fn (PurchaseReceipt $record): bool => $record->status === 'DRAFT' && (auth()->user()?->can('inventory.receive') ?? false))
                    ->action(static function (PurchaseReceipt $record): void {
                        try {
                            app(CancelReceipt::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Borrador anulado')->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo anular')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aún no hay recepciones')
            ->emptyStateDescription('Registre la mercadería que llega con el botón "Nueva recepción".');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('purchase_receipts.*')
            ->addSelect([
                'items_count' => DB::table('purchase_receipt_items')->selectRaw('COUNT(*)')->whereColumn('purchase_receipt_items.receipt_id', 'purchase_receipts.id'),
                'total_cost' => DB::table('purchase_receipt_items')->selectRaw('COALESCE(SUM(qty_received * unit_cost), 0)')->whereColumn('purchase_receipt_items.receipt_id', 'purchase_receipts.id'),
            ])
            ->with('supplier');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceipts::route('/'),
            'create' => CreateReceipt::route('/create'),
        ];
    }
}
