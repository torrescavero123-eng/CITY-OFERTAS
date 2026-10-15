<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adjustments;

use App\Filament\Resources\Adjustments\Pages\CreateAdjustment;
use App\Filament\Resources\Adjustments\Pages\ListAdjustments;
use App\Filament\Resources\Products\CatalogOptions;
use App\Filament\Support\SearchOptions;
use App\Models\StockAdjustmentRequest;
use App\Modules\Operations\Application\ApproveAdjustment;
use App\Modules\Operations\Application\RejectAdjustment;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\OperationsException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustmentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Almacén';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ajustes y mermas';

    protected static ?string $modelLabel = 'solicitud de ajuste';

    protected static ?string $pluralModelLabel = 'ajustes y mermas';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Solicitud')
                ->description('El stock NO cambia hasta que otra persona (supervisor) la apruebe.')
                ->schema([
                    Select::make('kind')->label('Tipo')->required()->live()
                        ->options([
                            'WRITE_OFF' => 'Merma (daño, robo, vencimiento, muestra)',
                            'NEGATIVE' => 'Ajuste negativo (hay menos físico que en el sistema)',
                            'POSITIVE' => 'Ajuste positivo (hay más físico que en el sistema)',
                        ])
                        ->afterStateUpdated(static fn (Set $set) => $set('reason_id', null)),
                    Select::make('reason_id')->label('Motivo')->required()
                        ->options(static function (Get $get): array {
                            $kind = AdjustmentKind::tryFrom((string) $get('kind'));

                            return $kind === null ? [] : CatalogOptions::reasons($kind->reasonMovementType());
                        }),
                    Select::make('product_id')->label('Producto')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => SearchOptions::products($search))
                        ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::productLabel($value)),
                    Select::make('location_id')->label('Ubicación')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => SearchOptions::locations($search))
                        ->getOptionLabelUsing(fn ($value): ?string => SearchOptions::locationLabel($value)),
                    TextInput::make('quantity')->label('Cantidad')->numeric()->minValue(0.001)->required(),
                    Textarea::make('notes')->label('Explicación (obligatoria)')->required()->minLength(5)->maxLength(500)->rows(3)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('kind')->label('Tipo')->badge()
                    ->formatStateUsing(static fn (string $state): string => ['WRITE_OFF' => 'Merma', 'NEGATIVE' => 'Ajuste (−)', 'POSITIVE' => 'Ajuste (+)'][$state] ?? $state)
                    ->color(static fn (string $state): string => $state === 'POSITIVE' ? 'info' : 'warning'),
                TextColumn::make('product.sku')->label('SKU')->searchable()->description(static fn (StockAdjustmentRequest $record): ?string => $record->product?->name),
                TextColumn::make('location_code')->label('Ubicación'),
                TextColumn::make('quantity')->label('Cantidad')->alignEnd()
                    ->formatStateUsing(static fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, '.', ','), '0'), '.') ?: '0'),
                TextColumn::make('reason_name')->label('Motivo')->wrap(),
                TextColumn::make('notes')->label('Explicación')->limit(40)->tooltip(static fn (StockAdjustmentRequest $record): string => (string) $record->getAttribute('notes'))->toggleable(),
                TextColumn::make('requester.name')->label('Solicitó'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(static fn (string $state): string => ['PENDING' => 'Pendiente', 'APPROVED' => 'Aprobada', 'REJECTED' => 'Rechazada'][$state] ?? $state)
                    ->color(static fn (string $state): string => ['PENDING' => 'warning', 'APPROVED' => 'success', 'REJECTED' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Solicitada')->dateTime('d/m/Y H:i', 'America/Lima')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(['PENDING' => 'Pendiente', 'APPROVED' => 'Aprobada', 'REJECTED' => 'Rechazada']),
                SelectFilter::make('kind')->label('Tipo')->options(['WRITE_OFF' => 'Merma', 'NEGATIVE' => 'Ajuste (−)', 'POSITIVE' => 'Ajuste (+)']),
            ])
            ->recordActions([
                Action::make('approve')->label('Aprobar')->icon('heroicon-o-check-circle')->color('success')
                    ->modalHeading('Aprobar solicitud')
                    ->modalDescription('Al aprobar, el stock cambia en ese momento y queda en el historial.')
                    ->schema([Textarea::make('notes')->label('Comentario (opcional)')->maxLength(500)])
                    ->visible(static fn (StockAdjustmentRequest $record): bool => $record->status === 'PENDING'
                        && (auth()->user()?->can('inventory.adjust.approve') ?? false)
                        && (int) $record->requested_by !== (int) auth()->id())
                    ->action(static function (array $data, StockAdjustmentRequest $record): void {
                        try {
                            app(ApproveAdjustment::class)((int) $record->id, (int) auth()->id(), ($data['notes'] ?? null) ?: null);
                            Notification::make()->title('Solicitud aprobada: el stock fue actualizado')->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo aprobar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('reject')->label('Rechazar')->icon('heroicon-o-x-circle')->color('danger')
                    ->modalHeading('Rechazar solicitud')
                    ->schema([Textarea::make('notes')->label('Motivo del rechazo')->required()->minLength(5)->maxLength(500)])
                    ->visible(static fn (StockAdjustmentRequest $record): bool => $record->status === 'PENDING'
                        && (auth()->user()?->can('inventory.adjust.approve') ?? false)
                        && (int) $record->requested_by !== (int) auth()->id())
                    ->action(static function (array $data, StockAdjustmentRequest $record): void {
                        try {
                            app(RejectAdjustment::class)((int) $record->id, (int) auth()->id(), (string) ($data['notes'] ?? ''));
                            Notification::make()->title('Solicitud rechazada')->success()->send();
                        } catch (OperationsException $e) {
                            Notification::make()->title('No se pudo rechazar')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay solicitudes')
            ->emptyStateDescription('Las mermas y ajustes que pida el personal aparecerán aquí para su aprobación.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('stock_adjustment_requests.*')
            ->addSelect([
                'location_code' => DB::table('locations')->select('code')->whereColumn('locations.id', 'stock_adjustment_requests.location_id'),
                'reason_name' => DB::table('movement_reasons')->select('name')->whereColumn('movement_reasons.id', 'stock_adjustment_requests.reason_id'),
            ])
            ->with(['product', 'requester']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdjustments::route('/'),
            'create' => CreateAdjustment::route('/create'),
        ];
    }
}
