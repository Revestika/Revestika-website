<?php
// verificar-configuracion.php - Script de diagnóstico
echo "<h1>🔍 Verificación de Configuración de OpenPay</h1>";

echo "<h2>1. Verificación de Archivos</h2>";
$requiredFiles = ['.env', 'config-openpay.php', 'procesar-pago.php', 'js/cart.js'];
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO EXISTE<br>";
    }
}

echo "<h2>2. Verificación de Directorios</h2>";
$requiredDirs = ['logs', 'orders', 'cache'];
foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ Directorio $dir existe<br>";
        if (is_writable($dir)) {
            echo "✅ $dir es escribible<br>";
        } else {
            echo "❌ $dir NO es escribible<br>";
        }
    } else {
        echo "❌ Directorio $dir NO EXISTE<br>";
        if (mkdir($dir, 0755, true)) {
            echo "✅ Directorio $dir creado<br>";
        } else {
            echo "❌ No se pudo crear $dir<br>";
        }
    }
}

echo "<h2>3. Verificación de Variables de Entorno</h2>";
if (file_exists('.env')) {
    echo "✅ Archivo .env encontrado<br>";
    
    $envContent = file_get_contents('.env');
    $requiredVars = [
        'OPENPAY_CLIENT_ID',
        'OPENPAY_CLIENT_SECRET', 
        'OPENPAY_BASE_URL',
        'SITE_SUCCESS_URL',
        'SITE_FAILED_URL'
    ];
    
    foreach ($requiredVars as $var) {
        if (strpos($envContent, $var) !== false) {
            echo "✅ $var está definido<br>";
        } else {
            echo "❌ $var NO está definido<br>";
        }
    }
} else {
    echo "❌ Archivo .env NO EXISTE<br>";
    echo "📝 Crea el archivo .env basándote en .env.example<br>";
}

echo "<h2>4. Verificación de PHP</h2>";
echo "✅ PHP Version: " . PHP_VERSION . "<br>";
echo "✅ cURL disponible: " . (function_exists('curl_init') ? 'Sí' : 'No') . "<br>";
echo "✅ JSON disponible: " . (function_exists('json_encode') ? 'Sí' : 'No') . "<br>";

echo "<h2>5. Verificación de Permisos</h2>";
$testFile = 'logs/test_write.tmp';
if (file_put_contents($testFile, 'test')) {
    echo "✅ Escritura en logs/ funciona<br>";
    unlink($testFile);
} else {
    echo "❌ No se puede escribir en logs/<br>";
}

echo "<h2>6. Logs Recientes</h2>";
if (is_dir('logs')) {
    $logFiles = glob('logs/openpay_*.log');
    if (empty($logFiles)) {
        echo "ℹ️ No hay logs de OpenPay<br>";
    } else {
        echo "📋 Logs encontrados:<br>";
        foreach ($logFiles as $logFile) {
            $size = filesize($logFile);
            $modified = date('Y-m-d H:i:s', filemtime($logFile));
            echo "- $logFile ({$size} bytes, modificado: $modified)<br>";
            
            // Mostrar últimas 5 líneas
            $lines = file($logFile);
            if ($lines) {
                echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 150px; overflow-y: auto;'>";
                echo implode('', array_slice($lines, -5));
                echo "</pre>";
            }
        }
    }
}

echo "<h2>✅ Instrucciones de Solución</h2>";
echo "<ol>";
echo "<li>Si falta .env: <code>cp .env.example .env</code> y configura las credenciales</li>";
echo "<li>Si hay errores de permisos: <code>chmod 755 logs/ orders/ cache/</code></li>";
echo "<li>Para probar datos: abre la consola del navegador y ejecuta <code>RevestikaDebug.testPriceExtraction()</code></li>";
echo "<li>Para debug de OpenPay: cambia temporalmente la URL en cart.js a 'debug-openpay.php'</li>";
echo "</ol>";
?>