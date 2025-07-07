<?php
// procesar-pago.php - VERSIÓN CORREGIDA PARA OPENPAY ARGENTINA
// ✅ FIX CRÍTICO: Precios divididos por 100 solucionado
require_once 'config-openpay.php';

// Headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://revestika.com.ar');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// ===== MANEJO DE REQUESTS GET (DEBUG Y TEST) =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (isset($_GET['debug'])) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        echo "<h3>🔍 Debug OpenPay</h3>";
        echo "<p>Environment: " . $OPENPAY_CONFIG['environment'] . "</p>";
        echo "<p>Base URL: " . $OPENPAY_CONFIG['base_url'] . "</p>";
        echo "<p>Client ID: " . substr($OPENPAY_CONFIG['client_id'], 0, 8) . "...</p>";
        
        // Test token
        $token = getOpenPayToken($OPENPAY_CONFIG);
        echo "<p>Token obtenido: " . ($token ? "✅ SÍ" : "❌ NO") . "</p>";
        
        if ($token) {
            echo "<p>Token: " . substr($token, 0, 20) . "...</p>";
        }
        exit;
    }
    
    if (isset($_GET['test'])) {
        echo "<h3>Test de Conectividad</h3>";
        
        $testUrls = [
            'Base' => 'https://api.openpayargentina.com.ar',
            'Auth' => 'https://auth.geopagos.com/oauth/token',
            'Orders' => 'https://api.openpayargentina.com.ar/api/v2/orders'
        ];
        
        foreach ($testUrls as $name => $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            echo "<p>$name: HTTP $code</p>";
        }
        exit;
    }
    
    // Si es GET pero no debug ni test, devolver error
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// ===== SOLO PERMITIR POST PARA PROCESAMIENTO REAL =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// ===== FUNCIONES =====

// FUNCIÓN: Obtener access token de OpenPay Argentina
function getOpenPayToken($config) {
    $authUrl = 'https://auth.geopagos.com/oauth/token';
    
    $possibleScopes = [
        'api_orders_post',
        'orders_write', 
        'orders',
        'read write',
        '', // Sin scope
    ];
    
    foreach ($possibleScopes as $scope) {
        logError("Probando scope: '$scope'");
        
        $tokenData = [
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ];
        
        if (!empty($scope)) {
            $tokenData['scope'] = $scope;
        }
        
        $ch = curl_init($authUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: Revestika/1.0'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            logError("Error cURL con scope '$scope': $curlError", 'ERROR');
            continue;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if ($result && isset($result['access_token'])) {
                logError("✅ Token obtenido exitosamente con scope: '$scope'");
                return $result['access_token'];
            }
        }
        
        logError("HTTP $httpCode con scope '$scope' - Response: " . substr($response, 0, 200));
    }
    
    return tryAlternativeAuth($config);
}

// FUNCIÓN: Método de autenticación alternativo
function tryAlternativeAuth($config) {
    logError("Intentando método de autenticación alternativo");
    
    $alternativeUrls = [
        'https://api.openpayargentina.com.ar/oauth/token',
        'https://auth.openpayargentina.com.ar/oauth/token'
    ];
    
    foreach ($alternativeUrls as $url) {
        $tokenData = [
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['access_token'])) {
                logError("✅ Token obtenido con URL alternativa: $url");
                return $result['access_token'];
            }
        }
        
        logError("Falló URL alternativa $url - HTTP: $httpCode");
    }
    
    return false;
}

// ✅ FUNCIÓN CRÍTICA: Logging detallado de precios
function logPriceDebug($orderId, $cartItems, $totalAmount) {
    logError("🔍 DEBUG PRECIOS - Orden: $orderId");
    logError("💰 Total enviado desde frontend: $totalAmount ARS");
    
    foreach ($cartItems as $index => $item) {
        $unitPrice = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        $itemTotal = $unitPrice * $quantity;
        
        logError("📋 Item $index: {$item['name']}");
        logError("   - Precio unitario: $unitPrice ARS");
        logError("   - Cantidad: $quantity");
        logError("   - Subtotal: $itemTotal ARS");
    }
}

// ===== PROCESAMIENTO PRINCIPAL =====

try {
    // Debug inicial
    logError("🔍 INICIO procesar-pago.php - Method: " . $_SERVER['REQUEST_METHOD']);
    logError("🔍 Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    // Verificar rate limiting
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Demasiadas solicitudes. Intenta nuevamente en unos minutos.'
        ]);
        exit;
    }
    
    // Obtener y validar input
    $input = file_get_contents('php://input');
    logError("🔍 Raw input length: " . strlen($input));
    
    if (empty($input)) {
        logError("❌ CRÍTICO: Input vacío en procesar-pago.php");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No se recibieron datos',
            'debug' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ]
        ]);
        exit;
    }
    
    // Decodificar JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("❌ JSON ERROR: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Datos JSON inválidos: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    logError("✅ JSON decodificado correctamente");
    
    // Validar estructura de datos
    if (!isset($data['cart']) || !isset($data['customer'])) {
        logError("❌ Estructura de datos incompleta");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Faltan datos requeridos del carrito o cliente'
        ]);
        exit;
    }
    
    $cartItems = $data['cart'];
    $customer = $data['customer'];
    $clientIP = getClientIP();
    
    logError("✅ Estructura de datos válida - IP: $clientIP");
    
    // Validaciones básicas
    if (empty($cartItems) || !is_array($cartItems)) {
        throw new Exception('Carrito vacío o inválido');
    }
    
    if (empty($customer['name']) || empty($customer['email'])) {
        throw new Exception('Datos del cliente incompletos');
    }
    
    // Generar orden
    $orderId = generateOrderId();
    logError("Procesando orden: $orderId");
    
    // ===== ✅ PROCESAMIENTO CORREGIDO DE ITEMS =====
    $orderItems = [];
    $totalAmount = 0;
    
    foreach ($cartItems as $index => $item) {
        if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception("Item $index del carrito incompleto");
        }
        
        $unitPrice = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        $itemTotal = $unitPrice * $quantity;
        
        if ($unitPrice <= 0 || $quantity <= 0) {
            throw new Exception("Precio o cantidad inválida en item $index");
        }
        
        // ✅ ESTRUCTURA CORREGIDA - PRECIO DIRECTO SIN NESTED OBJECT
        $orderItems[] = [
            'id' => $index + 1,
            'name' => substr($item['name'], 0, 50),
            'price' => $unitPrice,           // ← PRECIO DIRECTO
            'quantity' => $quantity,
            'currency' => 'ARS'              // ← MONEDA A NIVEL DE ITEM
        ];
        
        $totalAmount += $itemTotal;
    }
    
    // ✅ LOGGING DETALLADO DE PRECIOS
    logPriceDebug($orderId, $cartItems, $totalAmount);
    
    // Validar rangos de monto
    if ($totalAmount < $APP_CONFIG['min_order_amount']) {
        throw new Exception("El monto mínimo de compra es $" . number_format($APP_CONFIG['min_order_amount']));
    }
    
    if ($totalAmount > $APP_CONFIG['max_order_amount']) {
        throw new Exception("El monto máximo de compra es $" . number_format($APP_CONFIG['max_order_amount']));
    }
    
    logError("💰 Total calculado y validado: $totalAmount ARS");
    
    // ===== PASO 1: Obtener access token =====
    logError("PASO 1: Obteniendo access token...");
    $accessToken = getOpenPayToken($OPENPAY_CONFIG);
    
    if (!$accessToken) {
        throw new Exception('No se pudo obtener el token de autenticación');
    }
    
    logError("✅ Token obtenido exitosamente");
    
    // ===== PASO 2: Guardar orden localmente =====
    $localOrderData = [
        'id' => $orderId,
        'status' => 'pending',
        'items' => $cartItems,
        'customer' => $customer,
        'total' => $totalAmount,
        'currency' => 'ARS',
        'ip' => $clientIP
    ];
    
    if (!saveOrder($localOrderData)) {
        logError("Failed to save order $orderId locally", 'ERROR');
        throw new Exception('Error interno al procesar la orden');
    }
    
    // ===== PASO 3: Crear orden en OpenPay =====
    logError("PASO 3: Enviando orden a OpenPay - Monto: $totalAmount ARS");
    
    $orderUrl = $OPENPAY_CONFIG['base_url'] . '/api/v2/orders';
    
    // ✅ ESTRUCTURAS CORREGIDAS CON PRECIOS DIRECTOS
    $structures = [
        'direct_price' => [
            'amount' => $totalAmount,        // ← TOTAL DIRECTO
            'currency' => 'ARS',             // ← USAR ARS
            'description' => 'Compra Revestika - Orden ' . $orderId,
            'external_id' => $orderId,
            'items' => $orderItems,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'] ?? ''
            ],
            'redirect_urls' => [
                'success' => $SITE_CONFIG['success_url'] . '?order_id=' . $orderId,
                'failure' => $SITE_CONFIG['failed_url'] . '?order_id=' . $orderId
            ],
            'webhook_url' => $SITE_CONFIG['webhook_url']
        ],
        
        'centavos_version' => [
            'amount' => $totalAmount * 100,  // ← CONVERTIR A CENTAVOS
            'currency' => 'ARS',
            'description' => 'Compra Revestika - Orden ' . $orderId,
            'external_id' => $orderId,
            'items' => array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'] * 100,  // ← CENTAVOS
                    'quantity' => $item['quantity'],
                    'currency' => 'ARS'
                ];
            }, $orderItems),
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email']
            ],
            'redirect_urls' => [
                'success' => $SITE_CONFIG['success_url'],
                'failure' => $SITE_CONFIG['failed_url']
            ]
        ],
        
        'legacy_format' => [
            'currency' => '032',             // ← CÓDIGO ISO
            'items' => array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'unitPrice' => [
                        'currency' => '032',
                        'amount' => $item['price']
                    ],
                    'quantity' => $item['quantity']
                ];
            }, $orderItems),
            'redirectUrls' => [
                'success' => $SITE_CONFIG['success_url'],
                'failed' => $SITE_CONFIG['failed_url']
            ]
        ]
    ];
    
    $openpayResult = null;
    $successStructure = null;
    
    foreach ($structures as $structureName => $orderData) {
        logError("🧪 Probando estructura: $structureName");
        logError("📤 Enviando: " . json_encode($orderData));
        
        $ch = curl_init($orderUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: Revestika/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        logError("📥 Estructura $structureName - HTTP: $httpCode");
        
        if ($curlError) {
            logError("❌ Estructura $structureName - cURL Error: $curlError");
            continue;
        }
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            if ($result) {
                $openpayResult = ['success' => true, 'data' => $result];
                $successStructure = $structureName;
                logError("✅ ÉXITO con estructura: $structureName");
                logError("📋 Respuesta OpenPay: " . json_encode($result));
                break;
            }
        } else {
            logError("❌ Estructura $structureName - HTTP $httpCode: " . substr($response, 0, 300));
        }
    }
    
    // Verificar si alguna estructura funcionó
    if (!$openpayResult || !$openpayResult['success']) {
        logError("❌ NINGUNA estructura funcionó para OpenPay", 'ERROR');
        
        updateOrderStatus($orderId, 'failed', '', [
            'error' => 'openpay_creation_failed',
            'structures_tried' => array_keys($structures)
        ]);
        
        throw new Exception('Error del servicio de pagos. Intenta nuevamente en unos minutos.');
    }
    
    // ===== PASO 4: Procesar respuesta exitosa =====
    $openpayResponse = $openpayResult['data'];
    logError("✅ Orden creada exitosamente en OpenPay con estructura: $successStructure");
    
    // Buscar URL de checkout
    $checkoutUrl = null;
    $possiblePaths = [
        'data.links.checkout',
        'links.checkout',
        'checkout_url',
        'data.attributes.checkout_url',
        'data.checkout_url'
    ];
    
    foreach ($possiblePaths as $path) {
        $keys = explode('.', $path);
        $current = $openpayResponse;
        
        foreach ($keys as $key) {
            if (isset($current[$key])) {
                $current = $current[$key];
            } else {
                $current = null;
                break;
            }
        }
        
        if ($current && filter_var($current, FILTER_VALIDATE_URL)) {
            $checkoutUrl = $current;
            logError("✅ URL de checkout encontrada en: $path");
            break;
        }
    }
    
    if (!$checkoutUrl) {
        logError("❌ No se encontró URL de checkout válida", 'ERROR');
        logError("📋 Respuesta completa: " . json_encode($openpayResponse));
        
        updateOrderStatus($orderId, 'failed', '', ['error' => 'no_checkout_url']);
        throw new Exception('No se pudo generar la URL de pago');
    }
    
    // Actualizar orden con datos de OpenPay
    $updateData = [
        'openpay_id' => $openpayResponse['data']['id'] ?? $openpayResponse['id'] ?? '',
        'checkout_url' => $checkoutUrl,
        'status' => 'checkout_created',
        'structure_used' => $successStructure,
        'total_sent' => $totalAmount
    ];
    updateOrderStatus($orderId, 'checkout_created', '', $updateData);
    
    logError("✅ Checkout creado exitosamente para orden $orderId");
    logError("🌐 URL de checkout: $checkoutUrl");
    
    // ===== RESPUESTA EXITOSA =====
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'order_id' => $orderId,
        'amount' => $totalAmount,
        'currency' => 'ARS',
        'message' => 'Orden creada exitosamente',
        'debug' => [
            'structure_used' => $successStructure,
            'openpay_id' => $updateData['openpay_id'],
            'total_verified' => $totalAmount
        ]
    ]);
    
} catch (Exception $e) {
    $errorCode = $e->getCode() ?: 'GENERAL_ERROR';
    
    logError("❌ ERROR - IP: " . getClientIP() . " - " . $e->getMessage(), 'ERROR');
    
    $userMessage = $e->getMessage();
    if (empty($userMessage) || strpos($userMessage, 'Error') === false) {
        $userMessage = 'Error procesando el pago. Intenta nuevamente.';
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $userMessage,
        'code' => $errorCode,
        'timestamp' => date('c'),
        'debug' => [
            'error_type' => get_class($e),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]
    ]);
}
?>