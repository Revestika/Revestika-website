<?php
// config-openpay.php - VERSIÓN SEGURA PARA PRODUCCIÓN
session_start();

// Cargar variables de entorno
function loadEnvFile($file = '.env') {
    if (!file_exists($file)) {
        throw new Exception("Archivo .env no encontrado. Configuración requerida para producción.");
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Ignorar comentarios
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            if (preg_match('/^".*"$/', $value) || preg_match("/^'.*'$/", $value)) {
                $value = substr($value, 1, -1);
            }
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Función para obtener variable de entorno con fallback
function getEnvVar($key, $default = null) {
    $value = getenv($key) ?: $_ENV[$key] ?? $default;
    
    if ($value === null) {
        throw new Exception("Variable de entorno requerida no encontrada: $key");
    }
    
    return $value;
}

// Cargar variables de entorno
try {
    loadEnvFile();
} catch (Exception $e) {
    error_log("Error cargando configuración: " . $e->getMessage());
    die("Error de configuración del sistema");
}

// Forzar HTTPS en producción
if (!isset($_SERVER['HTTPS']) && $_SERVER['SERVER_NAME'] !== 'localhost') {
    $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirectURL");
    exit();
}

// Configuración de producción Openpay Argentina - DESDE VARIABLES DE ENTORNO
try {
    $OPENPAY_CONFIG = [
        'base_url' => getEnvVar('OPENPAY_BASE_URL'),
        'client_id' => getEnvVar('OPENPAY_CLIENT_ID'),
        'client_secret' => getEnvVar('OPENPAY_CLIENT_SECRET'),
        // merchant_id removido - no necesario para OpenPay Argentina
        'currency' => 'ARS',
        'environment' => getEnvVar('OPENPAY_ENVIRONMENT', 'production')
    ];
    
    // URLs de tu sitio Revestika - DESDE VARIABLES DE ENTORNO
    $SITE_CONFIG = [
        'success_url' => getEnvVar('SITE_SUCCESS_URL'),
        'failed_url' => getEnvVar('SITE_FAILED_URL'),
        'webhook_url' => getEnvVar('SITE_WEBHOOK_URL')
    ];
    
    // Configuración de la aplicación - DESDE VARIABLES DE ENTORNO
    $APP_CONFIG = [
        'max_cart_items' => (int)getEnvVar('APP_MAX_CART_ITEMS', '50'),
        'max_item_quantity' => 999,
        'min_order_amount' => (int)getEnvVar('APP_MIN_ORDER_AMOUNT', '1000'),
        'max_order_amount' => (int)getEnvVar('APP_MAX_ORDER_AMOUNT', '2000000'),
        'session_timeout' => 30 * 60,
        'rate_limit_window' => 3600,
        'rate_limit_max_attempts' => (int)getEnvVar('APP_RATE_LIMIT_MAX_ATTEMPTS', '15'),
        'order_expiry_minutes' => 30
    ];
    
} catch (Exception $e) {
    logError("Error de configuración crítico: " . $e->getMessage(), 'CRITICAL');
    die("Error de configuración del sistema");
}

// Configurar para producción
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/php_errors.log');

// Validar que las credenciales no estén vacías
if (empty($OPENPAY_CONFIG['client_id']) || empty($OPENPAY_CONFIG['client_secret'])) {
    logError("Credenciales de OpenPay faltantes o vacías", 'CRITICAL');
    die("Error de configuración crítico");
}

// Función para obtener IP del cliente
function getClientIP() {
    $ipkeys = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',             // Proxy
        'HTTP_X_FORWARDED_FOR',       // Load balancer/proxy
        'HTTP_X_FORWARDED',           // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
        'HTTP_FORWARDED_FOR',         // Proxy
        'HTTP_FORWARDED',             // Proxy
        'REMOTE_ADDR'                 // Standard
    ];
    
    foreach ($ipkeys as $keyword) {
        if (array_key_exists($keyword, $_SERVER) && !empty($_SERVER[$keyword])) {
            $ip = $_SERVER[$keyword];
            
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validar IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Función para validar datos del carrito
function validateCartData($cartData) {
    global $APP_CONFIG;
    
    if (empty($cartData) || !is_array($cartData)) {
        return false;
    }
    
    if (count($cartData) > $APP_CONFIG['max_cart_items']) {
        return false;
    }
    
    $totalAmount = 0;
    
    foreach ($cartData as $item) {
        if (!isset($item['id']) || !isset($item['name']) || 
            !isset($item['price']) || !isset($item['quantity'])) {
            return false;
        }
        
        if (!is_numeric($item['price']) || !is_numeric($item['quantity'])) {
            return false;
        }
        
        $price = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        
        if ($quantity <= 0 || $quantity > $APP_CONFIG['max_item_quantity']) {
            return false;
        }
        
        if ($price <= 0) {
            return false;
        }
        
        if (strlen($item['name']) < 1 || strlen($item['name']) > 255) {
            return false;
        }
        
        $totalAmount += $price * $quantity;
    }
    
    if ($totalAmount < $APP_CONFIG['min_order_amount'] || 
        $totalAmount > $APP_CONFIG['max_order_amount']) {
        return false;
    }
    
    return true;
}

// Función para generar ID único de orden
function generateOrderId() {
    $prefix = 'REV';
    $date = date('Ymd');
    $time = date('His');
    $random = strtoupper(substr(uniqid(), -6));
    
    return "$prefix-$date-$time-$random";
}

// Sistema de logging para producción
function logError($message, $level = 'ERROR') {
    $logDir = dirname(__FILE__) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/openpay_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $logMessage = "[$timestamp] [$level] [IP:$ip] $message\n";
    
    // En producción, solo loggear errores importantes
    if (in_array($level, ['ERROR', 'CRITICAL', 'WARNING'])) {
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    // Limpiar logs antiguos automáticamente
    cleanOldLogs($logDir);
}

// Función para limpiar logs antiguos
function cleanOldLogs($logDir) {
    static $lastCleanup = 0;
    
    // Solo limpiar una vez por hora
    if (time() - $lastCleanup < 3600) {
        return;
    }
    
    $files = glob($logDir . '/openpay_*.log');
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if (filemtime($file) < $thirtyDaysAgo) {
            unlink($file);
        }
    }
    
    $lastCleanup = time();
}

// Rate limiting para producción
function checkRateLimit($identifier = null) {
    global $APP_CONFIG;
    
    if (!$identifier) {
        $identifier = getClientIP();
    }
    
    $rateLimitKey = "rate_limit_" . md5($identifier);
    $rateLimitFile = "cache/$rateLimitKey.tmp";
    
    // Crear directorio cache si no existe
    if (!file_exists('cache')) {
        mkdir('cache', 0755, true);
    }
    
    $currentTime = time();
    $attempts = [];
    
    if (file_exists($rateLimitFile)) {
        $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        // Filtrar intentos de la última hora
        $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $APP_CONFIG) {
            return ($currentTime - $timestamp) < $APP_CONFIG['rate_limit_window'];
        });
    }
    
    if (count($attempts) >= $APP_CONFIG['rate_limit_max_attempts']) {
        logError("Rate limit exceeded for: $identifier", 'WARNING');
        return false;
    }
    
    // Registrar este intento
    $attempts[] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($attempts));
    
    return true;
}

// Función para sanitizar datos
function sanitizeInput($data, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        default:
            return filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    }
}

// Función para validar configuración
function validateConfiguration() {
    global $OPENPAY_CONFIG, $SITE_CONFIG;
    
    $required_fields = ['base_url', 'client_id', 'client_secret', 'currency'];
    foreach ($required_fields as $field) {
        if (empty($OPENPAY_CONFIG[$field])) {
            logError("Missing required configuration: $field", 'CRITICAL');
            throw new Exception("Configuration error");
        }
    }
    
    foreach ($SITE_CONFIG as $key => $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            logError("Invalid URL in configuration: $key", 'CRITICAL');
            throw new Exception("Configuration error");
        }
    }
    
    return true;
}

// Funciones para manejo de órdenes
function saveOrder($orderData) {
    $ordersDir = 'orders';
    if (!file_exists($ordersDir)) {
        mkdir($ordersDir, 0755, true);
    }
    
    $orderFile = "$ordersDir/order_{$orderData['id']}.json";
    $orderData['created_at'] = date('Y-m-d H:i:s');
    $orderData['ip'] = getClientIP();
    
    return file_put_contents($orderFile, json_encode($orderData, JSON_PRETTY_PRINT));
}

function getOrder($orderId) {
    $orderFile = "orders/order_$orderId.json";
    if (file_exists($orderFile)) {
        $data = file_get_contents($orderFile);
        return json_decode($data, true);
    }
    return null;
}

function updateOrderStatus($orderId, $status, $transactionId = '', $additionalData = []) {
    $orderFile = "orders/order_$orderId.json";
    
    if (file_exists($orderFile)) {
        $orderData = json_decode(file_get_contents($orderFile), true);
        $orderData['status'] = $status;
        $orderData['transaction_id'] = $transactionId;
        $orderData['updated_at'] = date('Y-m-d H:i:s');
        
        foreach ($additionalData as $key => $value) {
            $orderData[$key] = $value;
        }
        
        file_put_contents($orderFile, json_encode($orderData, JSON_PRETTY_PRINT));
        logError("Order $orderId status updated to: $status");
        return true;
    }
    
    logError("Order $orderId not found for status update", 'WARNING');
    return false;
}

// Función para limpiar logs de retry (evitar logs muy largos)
function cleanRetryLogs() {
    static $lastCleanup = 0;
    
    if (time() - $lastCleanup > 3600) { // Cada hora
        $logFiles = glob('logs/openpay_*.log');
        foreach ($logFiles as $logFile) {
            if (filesize($logFile) > 10 * 1024 * 1024) { // Archivos > 10MB
                $content = file_get_contents($logFile);
                // Mantener solo las últimas 1000 líneas
                $lines = explode("\n", $content);
                if (count($lines) > 1000) {
                    $lines = array_slice($lines, -1000);
                    file_put_contents($logFile, implode("\n", $lines));
                }
            }
        }
        $lastCleanup = time();
    }
}

// Validar configuración al cargar
try {
    validateConfiguration();
    logError("Configuración validada correctamente");
} catch (Exception $e) {
    logError('Critical configuration error: ' . $e->getMessage(), 'CRITICAL');
    
    // En producción, mostrar página de error genérica
    http_response_code(503);
    die('<!DOCTYPE html>
    <html>
    <head><title>Servicio Temporalmente No Disponible</title></head>
    <body>
        <h1>Servicio Temporalmente No Disponible</h1>
        <p>Estamos experimentando dificultades técnicas. Por favor intenta nuevamente en unos minutos.</p>
        <p>Si el problema persiste, contacta con nosotros por WhatsApp.</p>
    </body>
    </html>');
}

// Crear directorios necesarios
$dirs = ['logs', 'orders', 'cache'];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>