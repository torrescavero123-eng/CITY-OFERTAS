<?php

return [
    /*
    | Obliga a administradores y supervisores a activar la verificación en dos pasos (app de autenticación).
    | Actívelo (WMS_REQUIRE_2FA=true) DESPUÉS de que cada uno haya configurado su app desde "Mi perfil".
    */
    'require_2fa' => (bool) env('WMS_REQUIRE_2FA', false),
];
