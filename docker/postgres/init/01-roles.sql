-- Solo desarrollo: rol de ejecución de la app y BD de pruebas.
CREATE ROLE wms_app LOGIN PASSWORD 'wms_app_dev';
CREATE DATABASE wms_test OWNER wms_owner;
