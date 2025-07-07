<?php
// webhook-openpay.php - VERSIÓN FINAL PARA PRODUCCIÓN
require_once 'config-openpay.php';

// Headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');

// CRÍTICO: Permitir OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit(0);
}

// Solo permitir POST después de OPTIONS
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    logError("Webhook: Method not allowed - " . $_SERVER['REQUEST_METHOD'], 'WARNING');
    exit('Method Not Allowed');
}

// Función para verificar firma del webhook (si Openpay la proporciona)
function verifyWebhookSignature($payload, $signature, $secret) {
    if (empty($signature)) {
        return true; // No hay firma que verificar
    }
    
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expectedSignature, $signature);
}

// Función para enviar email de notificación (implementar según necesidades)
function sendOrderNotification($orderId, $status, $customerEmail = '') {
    // TODO: Implementar envío de emails
    // Por ahora solo loggear
    logError("Email notification: Order $orderId - Status: $status - Email: $customerEmail");
    
    // Ejemplo de implementación con mail() básico:
    /*
    if ($customerEmail && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $subject = "Estado de tu pedido $orderId - Revestika";
        $message = "Tu pedido $orderId ha cambiado de estado a: $status";
        $headers = "From: ventas@revestika.com.ar\r\n";
        mail($customerEmail, $subject, $message, $headers);
    }
    */
}

// Función para notificar por WhatsApp (webhook a servicio externo)
function notifyWhatsApp($orderId, $status, $amount = '') {
    // TODO: Implementar notificación automática por WhatsApp
    logError("WhatsApp notification: Order $orderId - Status: $status");
}

try {
    // Obtener IP del cliente (solo para logging)
    $clientIP = getClientIP();
    
    // Obtener datos del webhook
    $input = file_get_contents('php://input');
    $headers = getallheaders();
    
    // Log inicial
    logError("Webhook recibido desde IP: $clientIP");
    
    if (empty($input)) {
        logError("Webhook: Empty payload received", 'WARNING');
        http_response_code(400);
        exit('Bad Request');
    }
    
    // Verificar firma del webhook (si está disponible)
    $signature = $headers['X-Openpay-Signature'] ?? 
                 $headers['X-Signature'] ?? 
                 $headers['HTTP_X_OPENPAY_SIGNATURE'] ?? '';
    
    if (!verifyWebhookSignature($input, $signature, $OPENPAY_CONFIG['client_secret'])) {
        logError("Webhook: Invalid signature from IP $clientIP", 'SECURITY');
        http_response_code(403);
        exit('Forbidden');
    }
    
    // Decodificar datos del webhook
    $webhookData = json_decode($input, true);
    
    if (!$webhookData) {
        logError("Webhook: Invalid JSON payload", 'ERROR');
        http_response_code(400);
        exit('Bad Request');
    }
    
    // Log datos recibidos (sin información sensible)
    logError("Webhook data keys: " . implode(', ', array_keys($webhookData)));
    
    // Extraer información del webhook
    $eventType = $webhookData['event_type'] ?? $webhookData['type'] ?? '';
    $status = $webhookData['status'] ?? $webhookData['transaction']['status'] ?? 'unknown';
    $orderId = $webhookData['order_id'] ?? 
               $webhookData['external_id'] ?? 
               $webhookData['transaction']['order_id'] ?? 
               'unknown';
    $transactionId = $webhookData['transaction_id'] ?? 
                     $webhookData['id'] ?? 
                     $webhookData['transaction']['id'] ?? '';
    $amountCentavos = $webhookData['amount'] ?? 
                      $webhookData['transaction']['amount'] ?? 0;
    
    // IMPORTANTE: OpenPay envía el monto en centavos, convertir a pesos para logging
    $amountPesos = $amountCentavos / 100;
    
    logError("Webhook procesando - Order: $orderId, Status: $status, Event: $eventType");
    logError("💰 Amount recibido: $amountCentavos centavos = ARS $amountPesos");
    
    // Verificar que la orden existe
    $orderData = getOrder($orderId);
    if (!$orderData) {
        logError("Webhook: Order $orderId not found in local storage", 'WARNING');
        // Aún así responder OK para evitar reenvíos
        http_response_code(200);
        echo json_encode(['status' => 'order_not_found', 'order_id' => $orderId]);
        exit;
    }
    
    // Procesar según el estado
    $newStatus = '';
    $shouldNotifyCustomer = false;
    
    switch (strtolower($status)) {
        case 'completed':
        case 'approved':
        case 'paid':
        case 'success':
            $newStatus = 'paid';
            $shouldNotifyCustomer = true;
            logError("PAGO EXITOSO - Order: $orderId, Amount: ARS $amountPesos");
            break;
            
        case 'pending':
        case 'in_process':
        case 'processing':
            $newStatus = 'pending';
            logError("Pago pendiente - Order: $orderId");
            break;
            
        case 'failed':
        case 'declined':
        case 'rejected':
        case 'error':
            $newStatus = 'failed';
            $shouldNotifyCustomer = true;
            logError("PAGO FALLIDO - Order: $orderId, Reason: " . ($webhookData['failure_reason'] ?? 'unknown'));
            break;
            
        case 'cancelled':
        case 'canceled':
            $newStatus = 'cancelled';
            logError("Pago cancelado - Order: $orderId");
            break;
            
        case 'refunded':
            $newStatus = 'refunded';
            $shouldNotifyCustomer = true;
            logError("Pago reembolsado - Order: $orderId");
            break;
            
        case 'chargeback':
            $newStatus = 'chargeback';
            logError("CHARGEBACK - Order: $orderId", 'WARNING');
            break;
            
        default:
            $newStatus = 'unknown';
            logError("Estado desconocido en webhook - Order: $orderId, Status: $status", 'WARNING');
    }
    
    // Actualizar estado de la orden
    $webhookUpdateData = [
        'webhook_received_at' => date('Y-m-d H:i:s'),
        'openpay_status' => $status,
        'event_type' => $eventType,
        'amount_confirmed_centavos' => $amountCentavos,
        'amount_confirmed_pesos' => $amountPesos,
        'failure_reason' => $webhookData['failure_reason'] ?? null,
        'webhook_ip' => $clientIP
    ];
    
    $updateSuccess = updateOrderStatus($orderId, $newStatus, $transactionId, $webhookUpdateData);
    
    if (!$updateSuccess) {
        logError("Failed to update order status for $orderId", 'ERROR');
    }
    
    // Enviar notificaciones si es necesario
    if ($shouldNotifyCustomer && isset($orderData['customer']['email'])) {
        sendOrderNotification($orderId, $newStatus, $orderData['customer']['email']);
        notifyWhatsApp($orderId, $newStatus, "ARS $amountPesos");
    }
    
    // Acciones específicas según el estado
    switch ($newStatus) {
        case 'paid':
            // Acciones para pago exitoso
            logError("Procesando pago exitoso para orden $orderId");
            // TODO: 
            // - Enviar email de confirmación
            // - Notificar al equipo de ventas
            // - Actualizar inventario si corresponde
            // - Generar factura
            // - Iniciar proceso de envío
            break;
            
        case 'failed':
            // Acciones para pago fallido
            logError("Procesando pago fallido para orden $orderId");
            // TODO:
            // - Enviar email de pago fallido
            // - Ofrecer métodos alternativos de pago
            // - Notificar al equipo comercial
            break;
            
        case 'chargeback':
            // Acciones críticas para chargeback
            logError("ALERTA: Chargeback detectado para orden $orderId", 'CRITICAL');
            // TODO:
            // - Notificar inmediatamente al equipo
            // - Iniciar proceso de disputa
            // - Revisar orden para posible fraude
            // - Bloquear cliente si es necesario
            break;
            
        case 'cancelled':
            // Acciones para cancelación
            logError("Procesando cancelación para orden $orderId");
            // TODO:
            // - Liberar inventario reservado
            // - Notificar cancelación al cliente
            break;
            
        case 'refunded':
            // Acciones para reembolso
            logError("Procesando reembolso para orden $orderId");
            // TODO:
            // - Confirmar reembolso al cliente
            // - Actualizar contabilidad
            // - Gestionar devolución de productos
            break;
    }
    
    // Respuesta exitosa al webhook
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook procesado correctamente',
        'order_id' => $orderId,
        'new_status' => $newStatus,
        'transaction_id' => $transactionId,
        'processed_at' => date('Y-m-d H:i:s'),
        'event_type' => $eventType
    ]);
    
    logError("Webhook procesado exitosamente para orden $orderId - Nuevo estado: $newStatus");
    
} catch (Exception $e) {
    logError("Error procesando webhook: " . $e->getMessage(), 'ERROR');
    
    // Responder con 200 para evitar reenvíos constantes de Openpay
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno procesando webhook',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Cleanup automático al final
try {
    // Limpiar archivos temporales viejos (mayores a 1 día)
    $tempFiles = glob('cache/*.tmp');
    $oneDayAgo = time() - (24 * 60 * 60);
    
    foreach ($tempFiles as $file) {
        if (filemtime($file) < $oneDayAgo) {
            unlink($file);
        }
    }
    
    // Limpiar logs muy antiguos (mayores a 90 días)
    $oldLogs = glob('logs/openpay_*.log');
    $ninetyDaysAgo = time() - (90 * 24 * 60 * 60);
    
    foreach ($oldLogs as $logFile) {
        if (filemtime($logFile) < $ninetyDaysAgo) {
            unlink($logFile);
        }
    }
    
} catch (Exception $e) {
    // Ignorar errores de cleanup para no afectar el webhook
    logError("Cleanup error (non-critical): " . $e->getMessage(), 'WARNING');
}
?>