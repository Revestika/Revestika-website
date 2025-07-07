<?php require_once 'config-openpay.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago No Completado - Revestika</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .failed-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            padding: 20px;
            margin-top: 70px;
        }
        
        .failed-card {
            background: white;
            padding: 50px;
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .failed-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .failed-title {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .failed-message {
            color: var(--medium-gray);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .options-list {
            text-align: left;
            margin: 30px 0;
            background: var(--light-color);
            padding: 20px;
            border-radius: 8px;
        }
        
        .options-list h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .options-list ul {
            list-style-type: none;
            padding: 0;
        }
        
        .options-list li {
            margin: 10px 0;
            padding-left: 20px;
            position: relative;
        }
        
        .options-list li:before {
            content: "•";
            color: var(--accent-color);
            position: absolute;
            left: 0;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-retry {
            background-color: var(--accent-color);
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
            .failed-card {
                padding: 30px 20px;
            }
            
            .failed-title {
                font-size: 26px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="failed-container">
        <div class="failed-card">
            <div class="failed-icon">❌</div>
            <h1 class="failed-title">Pago No Completado</h1>
            <p class="failed-message">
                Tu pago no pudo ser procesado o fue cancelado. No te preocupes, no se realizó ningún cargo a tu tarjeta.
            </p>
            
            <div class="options-list">
                <h3>¿Qué puedes hacer?</h3>
                <ul>
                    <li>Intentar el pago nuevamente con la misma u otra tarjeta</li>
                    <li>Verificar que los datos de tu tarjeta sean correctos</li>
                    <li>Contactarnos para coordinar otro método de pago</li>
                    <li>Guardar tu carrito y proceder más tarde</li>
                </ul>
            </div>
            
            <div class="btn-group">
                <a href="index.html#productos" class="btn btn-retry">Intentar Nuevamente</a>
                <a href="https://wa.me/5492213176973?text=Hola! Tuve problemas con el pago online. Me gustaría coordinar otra forma de pago para mi pedido." 
                   class="btn btn-whatsapp" target="_blank">
                   Contactar por WhatsApp
                </a>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <p style="font-size: 14px; color: var(--medium-gray);">
                    Si continúas teniendo problemas, no dudes en contactarnos.
                    <br>Estamos aquí para ayudarte a completar tu compra.
                </p>
            </div>
        </div>
    </div>
</body>
</html>