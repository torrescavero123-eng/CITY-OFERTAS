<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Products\CatalogOptions;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Modules\Iam\Application\ActivateUser;
use App\Modules\Iam\Application\DeactivateUser;
use App\Modules\Iam\Application\ResetUserPassword;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\RoleName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return [
            RoleName::Admin->value => 'Administrador',
            RoleName::Supervisor->value => 'Supervisor',
            RoleName::Almacenero->value => 'Almacenero',
            RoleName::Compras->value => 'Compras',
            RoleName::Lectura->value => 'Solo lectura',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre completo')->required()->maxLength(120),
            TextInput::make('username')->label('Usuario')->required()->minLength(3)->maxLength(40)
                ->regex('/^[a-z0-9._-]+$/')->helperText('Minúsculas, números, punto, guion o guion bajo.')
                ->unique(table: 'users', column: 'username'),
            TextInput::make('email')->label('Correo')->email()->required()->maxLength(160)->unique(table: 'users', column: 'email'),
            TextInput::make('password')->label('Contraseña inicial')->password()->revealable()->required()->minLength(12)
                ->helperText('Mínimo 12 caracteres. No puede contener el usuario ni el correo.'),
            Select::make('role')->label('Rol')->options(self::roleOptions())->required(),
            Select::make('default_warehouse_id')->label('Almacén habitual')->options(fn (): array => CatalogOptions::warehouses())
                ->placeholder('Sin almacén por defecto'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('username')->label('Usuario')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->toggleable(),
                TextColumn::make('roles.name')->label('Rol')->badge()
                    ->formatStateUsing(static fn (string $state): string => self::roleOptions()[$state] ?? $state),
                IconColumn::make('is_active')->label('Activo')->boolean(),
                TextColumn::make('last_login_at')->label('Último acceso')->dateTime('d/m/Y H:i', 'America/Lima')->placeholder('Nunca')->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Estado')->trueLabel('Activos')->falseLabel('Desactivados')])
            ->recordActions([
                Action::make('deactivate')->label('Desactivar')->icon('heroicon-o-no-symbol')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('La persona no podrá volver a entrar. Su historial se conserva.')
                    ->visible(static fn (User $record): bool => $record->is_active && $record->getKey() !== auth()->id())
                    ->action(static fn (User $record) => self::run(
                        static fn () => app(DeactivateUser::class)((int) $record->getKey(), (int) auth()->id()),
                        'Usuario desactivado',
                    )),
                Action::make('activate')->label('Reactivar')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->visible(static fn (User $record): bool => ! $record->is_active)
                    ->action(static fn (User $record) => self::run(
                        static fn () => app(ActivateUser::class)((int) $record->getKey(), (int) auth()->id()),
                        'Usuario reactivado',
                    )),
                Action::make('resetPassword')->label('Restablecer contraseña')->icon('heroicon-o-key')->color('warning')
                    ->modalHeading('Restablecer contraseña')
                    ->modalDescription('Fije una contraseña temporal y entréguela en persona. También desbloquea la cuenta. Pídale que la cambie desde su perfil.')
                    ->schema([
                        TextInput::make('password')->label('Contraseña nueva')->password()->revealable()->required()->minLength(12)
                            ->helperText('Mínimo 12 caracteres. No puede contener el usuario ni el correo.'),
                        TextInput::make('password_confirmation')->label('Repita la contraseña')->password()->required()->same('password'),
                    ])
                    ->visible(static fn (User $record): bool => $record->getKey() !== auth()->id())
                    ->action(static fn (array $data, User $record) => self::run(
                        static fn () => app(ResetUserPassword::class)((int) $record->getKey(), (string) $data['password'], (int) auth()->id()),
                        'Contraseña restablecida',
                    )),
            ])
            ->defaultSort('name');
    }

    private static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
            Notification::make()->title($successTitle)->success()->send();
        } catch (IamException $e) {
            Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
        ];
    }
}
