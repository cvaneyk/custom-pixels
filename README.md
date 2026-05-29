# Custom Pixels (Meta + TikTok)

Plugin de WordPress para tracking full-funnel con envío browser + server.

## Eventos soportados

- PageView
- ViewContent
- AddToCart
- InitiateCheckout
- AddPaymentInfo
- Purchase
- Search
- CompleteRegistration
- Lead

## Integración de checkout personalizado

Dispara hooks desde tu lógica de negocio:

```php
do_action('custom_pixels_view_content', $payload);
do_action('custom_pixels_add_to_cart', $payload);
do_action('custom_pixels_initiate_checkout', $payload);
do_action('custom_pixels_purchase', $payload);
```

`$payload` puede incluir:

- `event_id` (string)
- `consent` (bool)
- `email` (string)
- `phone` (string)
- `external_id` (string)
- `value` (number/string)
- `currency` (string, ej. USD)
- `content_ids` (array)
- `contents` (array)
- `order_id` (string)
- `event_source_url` (string)

## API JS frontend

```js
window.CustomPixels.track("AddToCart", {
  consent: true,
  custom_data: {
    value: 49.9,
    currency: "USD",
    content_ids: ["sku_123"],
  },
  user_data: {
    email: "user@example.com",
  },
});
```

## QA / Debug plan (E2E)

1. Configurar IDs/tokens en `Settings > Custom Pixels`.
2. Activar `Enable debug mode`.
3. Confirmar carga de scripts `fbq` y `ttq` en el frontend.
4. Confirmar `PageView` browser.
5. Enviar un evento custom por JS (`window.CustomPixels.track(...)`).
6. Verificar en `WP_DEBUG_LOG` que el evento se registró con `event_id`.
7. Validar eventos test con:
   - Meta Events Manager (Test Events)
   - TikTok Events Manager (Test Events)
8. Confirmar deduplicación cliente/servidor usando el mismo `event_id`.
9. Confirmar que con `require_consent=1` no se disparan eventos sin consentimiento.
