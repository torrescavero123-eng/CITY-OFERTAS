<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transfers;

use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Support\SearchOptions;
use App\Models\TransferOrder;
use App\Modules\Operations\Application\CancelTransfer;
use App\Modules\Operations\Application\CompleteTransfer;
use App\Modules\Operations\Domain\OperationsException;
use BackedEnum;
use Filament\Actions\Action;
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

class TransferResource extends Resource
{
    protected static ?string $model = TransferOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Almacén';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Transferencias';

    protected static ?string $modelLabel = 'transferencia';

    protected static ?string $pluralModelLabel = 'transferencias';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Productos a mover')
                ->description('Bodega → piso de venta, o de un anaquel a otro. Si una línea no tiene stock suficiente, no se mueve ninguna.')
                ->schema([
                    Repeater::make('lines')->hiddenLabel()->defaultItems(1)->minItems(1)->maxItems(500)->addActionLabel('Agregar línea')
                        ->schema([
                            Select::make('product_id')->label('Producto')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => SearchOptions::products($search))
                                ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::productLabel($value)),
                            Select::make('from_location_id')->label('Desde')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => SearchOptions::locations($search))
                                ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::locationLabel($value)),
                            Select::make('to_location_id')->label('Hacia')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => SearchOptions::locations($search))
                                ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::locationLabel($value)),
                            TextInput::make('quantity')->label('Cantidad')->numeric()->minValue(0.001)->required(),
                        ])->columns(4),
                    Textarea::make('notes')->label('Observaciones')->rows(2)->maxLength(500),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('requester.name')->label('Solicitada por'),
                TextColumn::make('lines_count')->label('Líneas')->alignEnd(),
                TextColumn::make('units_total')->label('Unidades')->alignEnd()
                    ->formatStateUsing(static fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, '.', ','), '0'), '.') ?: '0'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(static fn (string $state): string => ['DRAFT' => 'Borrador', 'COMPLETED' => 'Completada', 'CANCELLED' => 'Anulada'][$state] ?? $state)
                    ->color(static fn (string $state): string => ['DRAFT' => 'gray', 'COMPLETED' => 'success', 'CANCELLED' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Creada')->dateTime('d/m/Y H:i', 'America/Lima')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(['DRAFT' => 'Borrador', 'COMPLETED' => 'Completada', 'CANCELLED' => 'Anulada']),
            ])
            ->recordActions([
                Action::make('complete')->label('Completar')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Completar transferencia')
                    ->modalDescription('Se moverá el stock de todas las líneas. Si alguna no tiene stock suficiente, no se mueve ninguna.')
                    ->visible(static fn (TransferOrder $record): bool => $record->status === 'DRAFT' && (auth()->user()?->can('inventory.transfer') ?? false))
                    ->action(static function (TransferOrder $record): void {
                        try {
                            $result = app(CompleteTransfer::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Transferencia completada')->body("Movimientos registrados: {$result['movements']}.")->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('cancel')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')
                    ->requiresConfirmation()
                    ->visible(static fn (TransferOrder $record): bool => $record->status === 'DRAFT' && (auth()->user()?->can('inventory.transfer') ?? false))
                    ->action(static function (TransferOrder $record): void {
                        try {
                            app(CancelTransfer::class)((int) $record->id, (int) auth()->id());
                            Notification::make()->title('Borrador anulado')->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo anular')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aún no hay transferencias');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('transfer_orders.*')
            ->addSelect([
                'lines_count' => DB::table('transfer_order_items')->selectRaw('COUNT(*)')->whereColumn('transfer_order_items.order_id', 'transfer_orders.id'),
                'units_total' => DB::table('transfer_order_items')->selectRaw('COALESCE(SUM(quantity), 0)')->whereColumn('transfer_order_items.order_id', 'transfer_orders.id'),
            ])
            ->with('requester');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransfers::route('/'),
            'create' => CreateTransfer::route('/create'),
        ];
    }
}
