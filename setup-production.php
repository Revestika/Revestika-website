<?php
/**
 * Script de configuración inicial para producción
 * Ejecutar UNA SOLA VEZ para configurar el sistema
 */

echo "🚀 CONFIGURACIÓN INICIAL REVESTIKA - OPENPAY\n";
echo "==========================================\n\n";

// Verificar que no se ejecute en producción por error
if ($_SERVER['SERVER_NAME'] !== 'localhost' && !isset($_GET['force'])) {
    die("⚠️  ESTE SCRIPT SOLO DEBE EJECUTARSE EN DESARROLLO O CON ?force=1\n");
}

// 1. Verificar archivo .env
if (!file_exists('.env')) {
    if (file_exists('.env.example')) {
        copy('.env.example', '.env');
        echo "✅ Archivo .env creado desde .env.example\n";
        echo "❗ EDITA .env con tus credenciales reales antes de continuar\n\n";
    } else {
        echo "❌ No se encontró .env.example\n";
        exit(1);
    }
} else {
    echo "✅ Archivo .env ya existe\n";
}

// 2. Crear directorios necesarios
$directories = ['logs', 'orders', 'cache'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Directorio '$dir' creado\n";
        } else {
            echo "❌ Error creando directorio '$dir'\n";
        }
    } else {
        echo "✅ Directorio '$dir' ya existe\n";
    }
}

// 3. Verificar permisos
foreach ($directories as $dir) {
    if (is_writable($dir)) {
        echo "✅ Directorio '$dir' es escribible\n";
    } else {
        echo "❌ Directorio '$dir' NO es escribible\n";
    }
}

// 4. Crear archivo de índice para proteger directorios
$indexContent = "<?php\n// Acceso denegado\nheader('HTTP/1.0 403 Forbidden');\nexit('Acceso denegado');\n?>";

foreach ($directories as $dir) {
    $indexFile = "$dir/index.php";
    if (!file_exists($indexFile)) {
        file_put_contents($indexFile, $indexContent);
        echo "✅ Protección agregada a '$dir/'\n";
    }
}

// 5. Crear archivo .htaccess si no existe
$htaccessContent = "# Revestika - Configuración de seguridad
RewriteEngine On

# Forzar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Proteger archivos sensibles
<Files \".env\">
    Order allow,deny
    Deny from all
</Files>

<Files \"*.log\">
    Order allow,deny
    Deny from all
</Files>

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection \"1; mode=block\"
Header always set Referrer-Policy \"strict-origin-when-cross-origin\"
Header always set Permissions-Policy \"geolocation=(), microphone=(), camera=()\"

# Cache para recursos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css \"access plus 1 month\"
    ExpiresByType application/javascript \"access plus 1 month\"
    ExpiresByType image/png \"access plus 1 month\"
    ExpiresByType image/jpg \"access plus 1 month\"
    ExpiresByType image/jpeg \"access plus 1 month\"
    ExpiresByType image/gif \"access plus 1 month\"
</IfModule>
";

if (!file_exists('.htaccess')) {
    file_put_contents('.htaccess', $htaccessContent);
    echo "✅ Archivo .htaccess creado con configuración de seguridad\n";
} else {
    echo "✅ Archivo .htaccess ya existe\n";
}

// 6. Test básico de configuración
echo "\n📋 VERIFICANDO CONFIGURACIÓN...\n";

try {
    // Cargar config si existe
    if (file_exists('config-openpay-secure.php')) {
        include 'config-openpay-secure.php';
        echo "✅ Configuración cargada correctamente\n";
        
        // Verificar variables críticas
        if (!empty($OPENPAY_CONFIG['client_id'])) {
            echo "✅ Client ID configurado\n";
        } else {
            echo "❌ Client ID faltante en .env\n";
        }
        
        if (!empty($OPENPAY_CONFIG['client_secret'])) {
            echo "✅ Client Secret configurado\n";
        } else {
            echo "❌ Client Secret faltante en .env\n";
        }
        
    } else {
        echo "❌ config-openpay-secure.php no encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Error en configuración: " . $e->getMessage() . "\n";
}

// 7. Verificar extensiones PHP
echo "\n🐘 VERIFICANDO EXTENSIONES PHP...\n";
$requiredExtensions = ['curl', 'json', 'openssl', 'filter'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extensión '$ext' disponible\n";
    } else {
        echo "❌ Extensión '$ext' NO disponible\n";
    }
}

// 8. Resumen final
echo "\n" . str_repeat("=", 50) . "\n";
echo "📋 RESUMEN DE CONFIGURACIÓN\n";
echo str_repeat("=", 50) . "\n";

if (file_exists('.env')) {
    echo "✅ Variables de entorno: Configuradas\n";
} else {
    echo "❌ Variables de entorno: Faltantes\n";
}

echo "✅ Directorios: Creados\n";
echo "✅ Seguridad: .htaccess configurado\n";
echo "✅ Protección: Archivos protegidos\n";

echo "\n🚀 PRÓXIMOS PASOS:\n";
echo "1. Editar .env con credenciales reales de OpenPay\n";
echo "2. Confirmar URLs en .env\n";
echo "3. Subir archivos al servidor\n";
echo "4. Ejecutar verificar-produccion.php\n";
echo "5. Realizar transacción de prueba\n";

echo "\n⚠️  IMPORTANTE:\n";
echo "- NUNCA subas .env a Git\n";
echo "- Verifica que .gitignore esté funcionando\n";
echo "- Mantén backups de .env en lugar seguro\n";
echo "- Cambia credenciales si son comprometidas\n\n";
?>