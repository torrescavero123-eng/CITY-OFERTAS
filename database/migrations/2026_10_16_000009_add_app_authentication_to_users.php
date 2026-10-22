<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificación en dos pasos con app (Google Authenticator, Microsoft Authenticator, Authy...). Se guardan cifrados.
        DB::unprepared(<<<'SQL'
        ALTER TABLE users ADD COLUMN app_authentication_secret TEXT;
        ALTER TABLE users ADD COLUMN app_authentication_recovery_codes TEXT;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE users DROP COLUMN IF EXISTS app_authentication_recovery_codes; ALTER TABLE users DROP COLUMN IF EXISTS app_authentication_secret;');
    }
};
