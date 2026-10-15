<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

/** Cambios parciales (null = no cambiar). El RUC nunca cambia. */
final readonly class SupplierChanges
{
    public ?string $businessName;

    public ?string $contactName;

    public ?string $phone;

    public ?string $email;

    public ?string $address;

    public function __construct(
        ?string $businessName = null,
        ?string $contactName = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
        public ?int $leadTimeDays = null,
        public ?int $actorId = null,
    ) {
        $f = SupplierFields::clean($businessName, $contactName, $phone, $email, $address, $leadTimeDays, nameRequired: false);
        $this->businessName = $f['business_name'];
        $this->contactName = $f['contact_name'];
        $this->phone = $f['phone'];
        $this->email = $f['email'];
        $this->address = $f['address'];
    }

    /** @return array<string, scalar> */
    public function fields(): array
    {
        return array_filter([
            'business_name' => $this->businessName,
            'contact_name' => $this->contactName,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'lead_time_days' => $this->leadTimeDays,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
