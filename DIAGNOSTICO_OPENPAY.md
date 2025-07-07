# Diagnóstico del Problema de OpenPay

## 🚨 Problema Identificado

**Error:** HTTP 422 en OpenPay con mensaje "Este valor no debería estar vacío" para el campo `amount`.

**Ubicación:** El error ocurre en `cart.js:438` durante el procesamiento del pago.

## 🔍 Análisis del Código

### 1. Flujo del Pago
- El usuario hace clic en "Procesar Pago" en `cart.js`
- Se ejecuta `handleCustomerFormSubmit()` (línea ~380 en cart.js)
- Se envían los datos a `procesar-pago.php`
- El PHP intenta múltiples estructuras de datos con OpenPay

### 2. Problemas Identificados

#### A. Configuración de OpenPay
- ❌ **Archivo .env faltante**: El sistema requiere un archivo `.env` con credenciales
- ❌ **Credenciales no configuradas**: Sin client_id y client_secret válidos

#### B. Validación de Datos
- ⚠️ **Formato de amount**: OpenPay puede requerir formato específico (enteros vs decimales)
- ⚠️ **Validación de items**: Los items son obligatorios según el código PHP
- ⚠️ **Tipos de datos**: Los precios pueden estar llegando como strings en lugar de números

#### C. Estructura de Datos
El PHP está probando 5 estructuras diferentes:
1. `items_normales` - Precios tal como están
2. `items_centavos` - Precios multiplicados por 100
3. `codigo_032` - Usando código de moneda numérico
4. `unitprice_nested` - Estructura anidada de precios
5. `solo_items` - Sin amount total, solo items

## 🛠️ Soluciones Implementadas

### 1. Archivo de Debugging
✅ Creé `debug-openpay.php` para diagnosticar datos de entrada

### 2. Archivo de Configuración
✅ Creé `.env.example` con las variables necesarias

### 3. Próximos Pasos Recomendados

#### Paso 1: Configurar Credenciales
```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# Editar con credenciales reales de OpenPay
# OPENPAY_CLIENT_ID=tu_client_id_real
# OPENPAY_CLIENT_SECRET=tu_client_secret_real
```

#### Paso 2: Verificar Datos de Entrada
```javascript
// En cart.js, agregar logging antes del envío:
console.log('🔍 Datos enviados a OpenPay:', {
    cart: cart,
    total: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
    customer: customerData
});
```

#### Paso 3: Probar con Debug
```javascript
// Cambiar temporalmente la URL en cart.js línea ~400:
const response = await fetch('debug-openpay.php', {
    // ... resto del código
});
```

## 🚨 Problemas Críticos a Resolver

### 1. Archivo .env Faltante
**Síntoma:** Error de configuración crítico
**Solución:** Crear archivo `.env` con credenciales reales

### 2. Precios como String vs Number
**Síntoma:** OpenPay rechaza amounts no numéricos
**Solución:** Asegurar que `item.price` sea number en JavaScript

### 3. Validación de Amount
**Síntoma:** HTTP 422 - "amount no debe estar vacío"
**Posibles causas:**
- Amount es 0 o negativo
- Amount no es numérico
- Formato incorrecto (centavos vs pesos)

## 🧪 Script de Testing

```javascript
// Ejecutar en consola del navegador:
RevestikaDebug.testPriceExtraction();
RevestikaDebug.getCartTotal();
RevestikaDebug.simulateCheckout();
```

## 📋 Checklist de Verificación

- [ ] Archivo `.env` existe y tiene credenciales válidas
- [ ] `logs/` directorio existe y es escribible
- [ ] `orders/` directorio existe y es escribible
- [ ] Los precios en el carrito son números, no strings
- [ ] El total del carrito es mayor a $1000 (mínimo configurado)
- [ ] Las credenciales de OpenPay son de producción válidas

## 🔧 Fixes Inmediatos Recomendados

### 1. Verificar Función extractPrice()
La función `extractPrice()` en cart.js debe asegurar que retorna números:

```javascript
function extractPrice(productCard) {
    const priceElement = productCard.querySelector('.product-price');
    if (!priceElement) return 0;
    
    const priceText = priceElement.textContent;
    const numericValue = parseFloat(priceText.replace(/[^\d.,]/g, '').replace(',', '.'));
    
    // ASEGURAR QUE ES UN NÚMERO VÁLIDO
    return isNaN(numericValue) ? 0 : numericValue;
}
```

### 2. Validar Datos Antes del Envío
```javascript
// En handleCustomerFormSubmit(), antes de enviar:
const total = cart.reduce((sum, item) => {
    const price = parseFloat(item.price);
    const quantity = parseInt(item.quantity);
    if (isNaN(price) || isNaN(quantity)) {
        throw new Error(`Precio o cantidad inválida en ${item.name}`);
    }
    return sum + (price * quantity);
}, 0);

if (total <= 0) {
    throw new Error('El total debe ser mayor a 0');
}
```

## 📞 Contacto con OpenPay

Si los problemas persisten, verificar con OpenPay Argentina:
- Documentación: https://docs.geopagos.com/
- Soporte técnico para validar credenciales y formato de datos