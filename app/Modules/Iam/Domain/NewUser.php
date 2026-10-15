<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

final readonly class NewUser
{
    public string $name;

    public string $username;

    public string $email;

    public function __construct(
        string $name,
        string $username,
        string $email,
        public string $password,
        public RoleName $role,
        public ?int $defaultWarehouseId = null,
        public ?int $actorId = null,
    ) {
        $this->name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $this->username = mb_strtolower(trim($username));
        $this->email = mb_strtolower(trim($email));

        if (mb_strlen($this->name) < 2 || mb_strlen($this->name) > 120) {
            throw IamException::invalid('El nombre debe tener entre 2 y 120 caracteres.');
        }
        if (preg_match('/^[a-z0-9._-]{3,40}$/', $this->username) !== 1) {
            throw IamException::invalid('El usuario debe tener 3 a 40 caracteres: letras minúsculas, números, punto, guion o guion bajo.');
        }
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($this->email) > 160) {
            throw IamException::invalid('El correo no es válido.');
        }

        $violations = PasswordPolicy::violations($password, $this->username, $this->email);
        if ($violations !== []) {
            throw IamException::invalid(implode(' ', $violations));
        }
    }
}
