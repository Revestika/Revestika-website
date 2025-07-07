<?php
// procesar-pago.php - VERSIÓN FINAL CON ITEMS OBLIGATORIOS
require_once 'config-openpay.php';

// Headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://revestika.com.ar');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// FUNCIÓN: Token
function getOpenPayToken($config) {
    $authUrl = 'https://auth.geopagos.com/oauth/token';
    
    $tokenData = [
        'grant_type' => 'client_credentials',
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret']
    ];
    
    $ch = curl_init($authUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($tokenData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['access_token'])) {
            return $result['access_token'];
        }
    }
    
    return false;
}

try {
    // Validar datos
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['cart']) || !isset($data['customer'])) {
        throw new Exception('Datos inválidos');
    }
    
    $cartItems = $data['cart'];
    $customer = $data['customer'];
    $orderId = generateOrderId();
    
    // Calcular totales
    $totalAmount = 0;
    $orderItems = [];
    
    foreach ($cartItems as $index => $item) {
        $unitPrice = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        $itemTotal = $unitPrice * $quantity;
        $totalAmount += $itemTotal;
        
        // Preparar item para OpenPay
        $orderItems[] = [
            'id' => $index + 1,
            'name' => substr($item['name'], 0, 50),
            'price' => $unitPrice,
            'quantity' => $quantity
        ];
    }
    
    logError("💰 Total calculado: $totalAmount ARS");
    logError("📦 Items preparados: " . count($orderItems));
    
    // Obtener token
    $accessToken = getOpenPayToken($OPENPAY_CONFIG);
    if (!$accessToken) {
        throw new Exception('No se pudo obtener token');
    }
    
    // Guardar orden localmente
    saveOrder([
        'id' => $orderId,
        'total' => $totalAmount,
        'items' => $cartItems,
        'customer' => $customer
    ]);
    
    $orderUrl = $OPENPAY_CONFIG['base_url'] . '/api/v2/orders';
    
    // ===== 💰 ESTRUCTURA CORRECTA PARA OPENPAY ARGENTINA (CENTAVOS) =====
    // OpenPay Argentina requiere montos en centavos, no en pesos
    $orderData = [
        'amount' => $totalAmount * 100, // CONVERTIR A CENTAVOS
        'currency' => 'ARS',
        'external_id' => $orderId,
        'description' => 'Compra Revestika - ' . $orderId,
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'amount' => $item['price'] * 100,  // OpenPay espera 'amount', no 'price'
                'quantity' => $item['quantity']
            ];
        }, $orderItems),
        'customer' => [
            'name' => $customer['name'],
            'email' => $customer['email']
        ],
        'redirect_urls' => [
            'success' => $SITE_CONFIG['success_url'] . '?order_id=' . $orderId,
            'failure' => $SITE_CONFIG['failed_url'] . '?order_id=' . $orderId
        ]
    ];
    
    logError("� Enviando order con amount en centavos: " . ($totalAmount * 100));
    logError("💱 Currency: ARS");
    logError("📦 Items count: " . count($orderData['items']));
    logError("💵 First item amount (centavos): " . ($orderItems[0]['price'] * 100));

    // Enviar orden a OpenPay
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
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    logError("📥 HTTP Response: $httpCode");
    
    if ($curlError) {
        throw new Exception("cURL Error: $curlError");
    }
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorDetail = "HTTP $httpCode";
        if ($response) {
            $errorData = json_decode($response, true);
            if ($errorData) {
                $errorDetail .= " - " . json_encode($errorData);
            } else {
                $errorDetail .= " - " . substr($response, 0, 200);
            }
        }
        
        throw new Exception("OpenPay API Error: $errorDetail");
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        throw new Exception("Invalid JSON response from OpenPay");
    }
    
    logError("✅ Orden creada exitosamente en OpenPay");
    
    // Buscar URL de checkout
    $checkoutUrl = null;
    $urlPaths = [
        'data.links.checkout', 'links.checkout', 'checkout_url',
        'data.checkout_url', 'data.attributes.checkout_url'
    ];
    
    foreach ($urlPaths as $path) {
        $keys = explode('.', $path);
        $current = $result;
        
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
            break;
        }
    }
    
    if (!$checkoutUrl) {
        throw new Exception('No se encontró URL de checkout en la respuesta de OpenPay');
    }
    
    // Actualizar orden
    updateOrderStatus($orderId, 'checkout_created', '', [
        'openpay_id' => $result['data']['id'] ?? $result['id'] ?? '',
        'checkout_url' => $checkoutUrl,
        'amount_in_centavos' => $totalAmount * 100
    ]);
    
    logError("✅ ORDEN EXITOSA - Amount enviado: " . ($totalAmount * 100) . " centavos (ARS " . $totalAmount . ")");
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'order_id' => $orderId,
        'debug' => [
            'total_pesos' => $totalAmount,
            'total_centavos_sent' => $totalAmount * 100,
            'item_amount_pesos' => $orderItems[0]['price'],
            'item_amount_centavos_sent' => $orderItems[0]['price'] * 100,
            'openpay_response' => $result
        ]
    ]);
    
} catch (Exception $e) {
    logError("❌ ERROR: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'order_id' => $orderId ?? null
    ]);
}
?>