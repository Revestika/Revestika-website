<?php require_once 'config-openpay.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso - Revestika</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            padding: 20px;
            margin-top: 70px;
        }
        
        .success-card {
            background: white;
            padding: 50px;
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .success-title {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .success-message {
            color: var(--medium-gray);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .order-details {
            background: var(--light-color);
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: left;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-whatsapp {
            background-color: #25D366;
            color: white;
        }
        
        .btn-whatsapp:hover {
            background-color: #20b358;
            color: white;
        }
        
        @media (max-width: 768px) {
            .success-card {
                padding: 30px 20px;
            }
            
            .success-title {
                font-size: 26px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">✅</div>
            <h1 class="success-title">¡Pago Exitoso!</h1>
            <p class="success-message">
                Tu pago ha sido procesado correctamente. Recibirás un email de confirmación en los próximos minutos con todos los detalles de tu pedido.
            </p>
            
            <?php if (isset($_GET['order_id'])): ?>
            <div class="order-details">
                <h3>Detalles de tu Pedido</h3>
                <p><strong>Número de Orden:</strong> <?php echo htmlspecialchars($_GET['order_id']); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                <p><strong>Estado:</strong> Pago Confirmado</p>
            </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <a href="index.html" class="btn">Volver al Inicio</a>
                <a href="https://wa.me/5492213176973?text=Hola! Mi pago fue exitoso. Número de orden: <?php echo isset($_GET['order_id']) ? $_GET['order_id'] : ''; ?>. Me gustaría coordinar la entrega." 
                   class="btn btn-whatsapp" target="_blank">
                   Contactar por WhatsApp
                </a>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <p style="font-size: 14px; color: var(--medium-gray);">
                    Nos pondremos en contacto contigo para coordinar la entrega de tus paneles.
                    <br>Si tienes alguna pregunta, no dudes en contactarnos.
                </p>
            </div>
        </div>
    </div>

    <!-- Meta Pixel Event -->
    <script>
    if (typeof fbq !== 'undefined') {
        fbq('track', 'Purchase', {
            value: 1,
            currency: 'ARS',
            content_name: 'Paneles Revestika',
            content_type: 'product'
        });
    }
    </script>
</body>
</html>