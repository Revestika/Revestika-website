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
    
    // ===== 🧪 ESTRUCTURAS CON ITEMS (OBLIGATORIO) + DIFERENTES PRECIOS =====
    $testStructures = [
        
        // 1. Items con precios normales
        'items_normales' => [
            'amount' => $totalAmount,
            'currency' => 'ARS',
            'external_id' => $orderId,
            'description' => 'Compra Revestika - ' . $orderId,
            'items' => $orderItems,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email']
            ],
            'redirect_urls' => [
                'success' => $SITE_CONFIG['success_url'] . '?order_id=' . $orderId,
                'failure' => $SITE_CONFIG['failed_url'] . '?order_id=' . $orderId
            ]
        ],
        
        // 2. Items con precios x100 (centavos)
        'items_centavos' => [
            'amount' => $totalAmount * 100,
            'currency' => 'ARS',
            'external_id' => $orderId,
            'description' => 'Compra Revestika - ' . $orderId,
            'items' => array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'] * 100,  // CENTAVOS
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
        ],
        
        // 3. Código de moneda numérico 
        'codigo_032' => [
            'amount' => $totalAmount,
            'currency' => '032',
            'external_id' => $orderId,
            'description' => 'Compra Revestika - ' . $orderId,
            'items' => $orderItems,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email']
            ],
            'redirect_urls' => [
                'success' => $SITE_CONFIG['success_url'] . '?order_id=' . $orderId,
                'failure' => $SITE_CONFIG['failed_url'] . '?order_id=' . $orderId
            ]
        ],
        
        // 4. Estructura con unitPrice nested (como estaba antes)
        'unitprice_nested' => [
            'amount' => $totalAmount,
            'currency' => 'ARS',
            'external_id' => $orderId,
            'description' => 'Compra Revestika - ' . $orderId,
            'items' => array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'unitPrice' => [
                        'currency' => 'ARS',
                        'amount' => $item['price']
                    ],
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
        ],
        
        // 5. Sin amount total (solo items)
        'solo_items' => [
            'currency' => 'ARS',
            'external_id' => $orderId,
            'description' => 'Compra Revestika - ' . $orderId,
            'items' => $orderItems,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email']
            ],
            'redirect_urls' => [
                'success' => $SITE_CONFIG['success_url'] . '?order_id=' . $orderId,
                'failure' => $SITE_CONFIG['failed_url'] . '?order_id=' . $orderId
            ]
        ]
    ];
    
    $successfulStructure = null;
    $successfulResponse = null;
    $allErrors = [];
    
    // ===== 🔬 PROBAR ESTRUCTURAS CON ITEMS =====
    foreach ($testStructures as $structureName => $orderData) {
        
        logError("🧪 === PROBANDO: $structureName ===");
        logError("💰 Amount: " . ($orderData['amount'] ?? 'NO_AMOUNT'));
        logError("💱 Currency: " . $orderData['currency']);
        logError("📦 Items count: " . count($orderData['items']));
        logError("💵 First item price: " . $orderData['items'][0]['price']);
        
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
        
        logError("📥 HTTP: $httpCode");
        
        if ($curlError) {
            $allErrors[$structureName] = "cURL: $curlError";
            continue;
        }
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            if ($result) {
                logError("✅ ¡ÉXITO CON: $structureName!");
                
                // 🔍 ANALIZAR QUÉ PRECIO MUESTRA OPENPAY
                $openpayAmount = null;
                $amountPaths = ['amount', 'data.amount', 'total', 'data.total'];
                
                foreach ($amountPaths as $path) {
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
                    
                    if ($current !== null && is_numeric($current)) {
                        $openpayAmount = $current;
                        logError("💰 OpenPay amount en '$path': $openpayAmount");
                        break;
                    }
                }
                
                // 🔍 COMPARAR PRECIOS
                $sentAmount = $orderData['amount'] ?? 'N/A';
                $sentItemPrice = $orderData['items'][0]['price'];
                
                logError("🔍 COMPARACIÓN:");
                logError("📤 Amount enviado: $sentAmount");
                logError("📤 Item price enviado: $sentItemPrice");
                logError("📥 OpenPay devuelve: " . ($openpayAmount ?? 'no_encontrado'));
                
                if ($openpayAmount && $sentAmount !== 'N/A') {
                    $ratio = $sentAmount / $openpayAmount;
                    logError("⚖️  Ratio: $ratio");
                }
                
                $successfulStructure = $structureName;
                $successfulResponse = $result;
                break;
            }
        }
        
        // Capturar error
        $errorDetail = "HTTP $httpCode";
        if ($response) {
            $errorData = json_decode($response, true);
            if ($errorData) {
                $errorDetail .= " - " . json_encode($errorData);
            } else {
                $errorDetail .= " - " . substr($response, 0, 100);
            }
        }
        
        logError("❌ Error: $errorDetail");
        $allErrors[$structureName] = $errorDetail;
    }
    
    if (!$successfulStructure) {
        logError("❌ TODAS LAS ESTRUCTURAS CON ITEMS FALLARON");
        throw new Exception('Todas las estructuras fallaron: ' . json_encode($allErrors));
    }
    
    // Buscar URL de checkout
    $checkoutUrl = null;
    $urlPaths = [
        'data.links.checkout', 'links.checkout', 'checkout_url',
        'data.checkout_url', 'data.attributes.checkout_url'
    ];
    
    foreach ($urlPaths as $path) {
        $keys = explode('.', $path);
        $current = $successfulResponse;
        
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
        throw new Exception('No se encontró URL de checkout');
    }
    
    // Actualizar orden
    updateOrderStatus($orderId, 'checkout_created', '', [
        'openpay_id' => $successfulResponse['data']['id'] ?? $successfulResponse['id'] ?? '',
        'checkout_url' => $checkoutUrl,
        'structure_used' => $successfulStructure
    ]);
    
    logError("✅ ORDEN EXITOSA - Estructura: $successfulStructure");
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'order_id' => $orderId,
        'debug' => [
            'structure_used' => $successfulStructure,
            'total_sent' => $testStructures[$successfulStructure]['amount'] ?? 'N/A',
            'item_price_sent' => $testStructures[$successfulStructure]['items'][0]['price'],
            'openpay_response' => $successfulResponse,
            'all_errors' => $allErrors
        ]
    ]);
    
} catch (Exception $e) {
    logError("❌ ERROR: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'all_errors' => $allErrors ?? []
        ]
    ]);
}
?>