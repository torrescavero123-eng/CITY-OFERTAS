<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use App\Modules\Suppliers\Application\ChangeSupplierState;
use App\Modules\Suppliers\Domain\SupplierException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Proveedores';

    protected static ?string $modelLabel = 'proveedor';

    protected static ?string $pluralModelLabel = 'proveedores';

    protected static ?string $recordTitleAttribute = 'business_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del proveedor')->schema([
                TextInput::make('ruc')->label('RUC')->required()->length(11)->regex('/^\d{11}$/')
                    ->disabled(static fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(static fn (string $operation): bool => $operation === 'create')
                    ->helperText('11 dígitos; se valida con su dígito verificador. No se cambia después.'),
                TextInput::make('business_name')->label('Razón social')->required()->maxLength(160),
                TextInput::make('contact_name')->label('Persona de contacto')->maxLength(120),
                TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
                TextInput::make('email')->label('Correo')->email()->maxLength(160),
                TextInput::make('lead_time_days')->label('Plazo de entrega (días)')->numeric()->minValue(0)->maxValue(365)
                    ->helperText('Sirve para calcular cuándo conviene pedir.'),
                TextInput::make('address')->label('Dirección')->maxLength(255)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ruc')->label('RUC')->searchable()->copyable(),
                TextColumn::make('business_name')->label('Razón social')->searchable()->sortable()->wrap(),
                TextColumn::make('contact_name')->label('Contacto')->searchable()->toggleable(),
                TextColumn::make('phone')->label('Teléfono')->toggleable(),
                TextColumn::make('lead_time_days')->label('Plazo (días)')->alignEnd()->placeholder('—'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Activo')->trueLabel('Activos')->falseLabel('Desactivados')])
            ->recordActions([
                EditAction::make('edit')->label('Editar'),
                Action::make('deactivate')->label('Desactivar')->icon('heroicon-o-no-symbol')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('No podrá usarse en nuevas recepciones. Su historial se conserva.')
                    ->visible(static fn (Supplier $record): bool => $record->is_active)
                    ->action(static fn (Supplier $record) => self::run(static fn () => app(ChangeSupplierState::class)->deactivate((int) $record->id, (int) auth()->id()), 'Proveedor desactivado')),
                Action::make('activate')->label('Reactivar')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->visible(static fn (Supplier $record): bool => ! $record->is_active)
                    ->action(static fn (Supplier $record) => self::run(static fn () => app(ChangeSupplierState::class)->activate((int) $record->id, (int) auth()->id()), 'Proveedor reactivado')),
            ])
            ->defaultSort('business_name')
            ->emptyStateHeading('Aún no hay proveedores');
    }

    public static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
            Notification::make()->title($successTitle)->success()->send();
        } catch (SupplierException $e) {
            Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
