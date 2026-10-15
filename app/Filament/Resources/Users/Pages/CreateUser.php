<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Iam\Application\CreateUser as CreateUserUseCase;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $id = app(CreateUserUseCase::class)(new NewUser(
                name: (string) ($data['name'] ?? ''),
                username: (string) ($data['username'] ?? ''),
                email: (string) ($data['email'] ?? ''),
                password: (string) ($data['password'] ?? ''),
                role: RoleName::tryFrom((string) ($data['role'] ?? '')) ?? RoleName::Lectura,
                defaultWarehouseId: ($data['default_warehouse_id'] ?? null) === null ? null : (int) $data['default_warehouse_id'],
                actorId: auth()->id() === null ? null : (int) auth()->id(),
            ));
        } catch (IamException $e) {
            Notification::make()->title('No se pudo crear el usuario')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return User::query()->findOrFail($id);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Usuario creado';
    }
}
