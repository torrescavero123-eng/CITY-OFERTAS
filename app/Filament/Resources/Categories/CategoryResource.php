<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')->label('Categoría padre')
                ->options(static function (): array {
                    $options = [];
                    foreach (DB::table('categories')->where('depth', '<', 3)->orderBy('name')->get(['id', 'name']) as $c) {
                        $options[(int) $c->id] = (string) $c->name;
                    }

                    return $options;
                })
                ->placeholder('Ninguna (categoría de primer nivel)')
                ->helperText('Se permiten hasta 3 niveles. No se puede cambiar después de crearla.')
                ->disabled(static fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(static fn (string $operation): bool => $operation === 'create'),
            TextInput::make('name')->label('Nombre')->required()->maxLength(80),
            TextInput::make('code')->label('Código (prefijo del SKU)')->maxLength(4)
                ->helperText('Obligatorio en las de primer nivel: 2 a 4 letras o números (por ejemplo LIB).')
                ->disabled(static fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(static fn (string $operation): bool => $operation === 'create'),
            Toggle::make('is_active')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('code')->label('Código')->badge(),
                TextColumn::make('parent.name')->label('Padre')->placeholder('—'),
                TextColumn::make('depth')->label('Nivel')->sortable(),
                IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->defaultSort('depth')
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
