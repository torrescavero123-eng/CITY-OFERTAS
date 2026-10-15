<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

final readonly class NewSupplier
{
    public string $businessName;

    public ?string $contactName;

    public ?string $phone;

    public ?string $email;

    public ?string $address;

    public function __construct(
        public Ruc $ruc,
        string $businessName,
        ?string $contactName = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
        public ?int $leadTimeDays = null,
        public ?int $actorId = null,
    ) {
        $fields = SupplierFields::clean($businessName, $contactName, $phone, $email, $address, $leadTimeDays);
        $this->businessName = (string) $fields['business_name'];
        $this->contactName = $fields['contact_name'];
        $this->phone = $fields['phone'];
        $this->email = $fields['email'];
        $this->address = $fields['address'];
    }
}
