<?php
/**
 * Plugin Name: API Connector
 * Plugin URI: https://seusite.com/
 * Description: Conecte seu WordPress a serviços externos via API REST personalizada.
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seusite.com/
 * Text Domain: api-connector
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Definições globais
define('APICONNECTOR_VERSION', '1.0.0');
define('APICONNECTOR_ROOT', __FILE__);
define('APICONNECTOR_ROOT_DIRNAME', basename(dirname(APICONNECTOR_ROOT)));

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

// Inicializar o plugin
$apiConnector = ApiConnector\ApiConnector::getInstance();
$apiConnector->boot();
