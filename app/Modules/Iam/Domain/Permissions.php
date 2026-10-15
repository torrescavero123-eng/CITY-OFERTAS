<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

/**
 * Única fuente de verdad de permisos y de la matriz rol x permiso (plan v3, sección 5).
 * Los permisos se asignan SIEMPRE por permiso, nunca preguntando por el nombre del rol.
 */
final class Permissions
{
    public const CATALOG_VIEW = 'catalog.view';

    public const CATALOG_WRITE = 'catalog.write';

    public const CATALOG_IMPORT = 'catalog.import';

    public const SUPPLIERS_MANAGE = 'suppliers.manage';

    public const LOCATIONS_MANAGE = 'locations.manage';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_RECEIVE = 'inventory.receive';

    public const INVENTORY_TRANSFER = 'inventory.transfer';

    public const INVENTORY_ADJUST_REQUEST = 'inventory.adjust.request';

    public const INVENTORY_ADJUST_APPROVE = 'inventory.adjust.approve';

    public const INVENTORY_OPENING_BALANCE = 'inventory.opening_balance';

    public const COUNTS_RUN = 'counts.run';

    public const COUNTS_CLOSE = 'counts.close';

    public const POS_IMPORT = 'pos.import';

    public const ALERTS_VIEW = 'alerts.view';

    public const ALERTS_MANAGE = 'alerts.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    public const USERS_MANAGE = 'users.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const AUDIT_VIEW = 'audit.view';

    /** @return list<string> */
    public static function all(): array
    {
        /** @var list<string> $values */
        $values = array_values((new \ReflectionClass(self::class))->getConstants());

        return $values;
    }

    /** @return list<string> */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::Admin => self::all(),
            RoleName::Supervisor => [
                self::CATALOG_VIEW, self::CATALOG_WRITE, self::CATALOG_IMPORT, self::LOCATIONS_MANAGE,
                self::INVENTORY_VIEW, self::INVENTORY_RECEIVE, self::INVENTORY_TRANSFER,
                self::INVENTORY_ADJUST_REQUEST, self::INVENTORY_ADJUST_APPROVE,
                self::COUNTS_RUN, self::COUNTS_CLOSE, self::POS_IMPORT,
                self::ALERTS_VIEW, self::ALERTS_MANAGE,
                self::REPORTS_VIEW, self::REPORTS_EXPORT, self::AUDIT_VIEW,
            ],
            RoleName::Almacenero => [
                self::CATALOG_VIEW, self::INVENTORY_VIEW, self::INVENTORY_RECEIVE, self::INVENTORY_TRANSFER,
                self::INVENTORY_ADJUST_REQUEST, self::COUNTS_RUN, self::ALERTS_VIEW,
            ],
            RoleName::Compras => [
                self::CATALOG_VIEW, self::SUPPLIERS_MANAGE, self::INVENTORY_VIEW,
                self::ALERTS_VIEW, self::REPORTS_VIEW, self::REPORTS_EXPORT,
            ],
            RoleName::Lectura => [
                self::CATALOG_VIEW, self::INVENTORY_VIEW, self::ALERTS_VIEW, self::REPORTS_VIEW,
            ],
        };
    }
}
