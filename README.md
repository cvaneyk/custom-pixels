# Custom Pixels (Meta + TikTok + ChatGPT)

Plugin de WordPress para tracking full-funnel con envío browser + server y deduplicación por `event_id`.

| Plataforma | Navegador | Servidor |
|---|---|---|
| Meta | Pixel (`fbq`) | Conversions API (Graph v26.0) |
| TikTok | Pixel (`ttq`) | Events API 2.0 (`/open_api/v1.3/event/track/`) |
| ChatGPT (OpenAI Ads) | Pixel (`oaiq`) | Conversions API (`https://bzr.openai.com/v1/events`) |

## Eventos soportados

| Evento del plugin | Meta / TikTok | ChatGPT |
|---|---|---|
| PageView | PageView / `ttq.page()` | `page_viewed` |
| ViewContent | ViewContent | `contents_viewed` |
| AddToCart | AddToCart | `items_added` |
| InitiateCheckout | InitiateCheckout | `checkout_started` |
| Purchase | Purchase | `order_created` |
| CompleteRegistration (envío de formularios) | CompleteRegistration | `registration_completed` |
| Lead | Lead | `lead_created` |
| Schedule | Schedule | `appointment_scheduled` |
| Subscribe / StartTrial | Subscribe / StartTrial | `subscription_created` / `trial_started` |
| Contact (clic en `tel:`) | Contact | custom `telefono` |
| Contact (clic en WhatsApp) | Contact | custom `whatsapp` |
| AddPaymentInfo, Search, ScrollDepth, OutboundClick, Click | sí | no se envían |

Los importes se envían a ChatGPT en unidades menores (49,90 EUR → `amount: 4990`).

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
- `currency` (string, ej. EUR)
- `content_ids` (array)
- `contents` (array de `{id, quantity, item_price}`)
- `order_id` (string)
- `event_source_url` (string)

## API JS frontend

```js
// Si "Requerir consentimiento" está activo, llama a esto desde tu banner de cookies.
window.CustomPixels.grantConsent();

window.CustomPixels.track("AddToCart", {
  custom_data: {
    value: 49.9,
    currency: "EUR",
    content_ids: ["sku_123"],
  },
  user_data: {
    email: "user@example.com",
  },
});
```

## Datos de usuario

- Email, teléfono y `external_id` se hashean con SHA-256 antes de salir del servidor (y en el navegador para TikTok/ChatGPT).
- Teléfonos sin prefijo internacional reciben el **prefijo telefónico** configurado (por defecto `34`).
- Se leen automáticamente las cookies `_fbp`, `_fbc`, `_ttp`, `__obref`, `__oppref` y los parámetros `fbclid` / `ttclid`.

## QA / Debug plan (E2E)

1. Configurar IDs/tokens en `Ajustes > Custom Pixels`.
2. Activar `Modo debug`.
3. Confirmar carga de `fbq`, `ttq` y `oaiq` en el frontend.
4. Confirmar `PageView` browser.
5. Enviar un evento custom por JS (`window.CustomPixels.track(...)`).
6. Verificar en `WP_DEBUG_LOG` que el evento se registró con `event_id`.
7. Validar eventos test con:
   - Meta Events Manager (Test Events)
   - TikTok Events Manager (Test Events)
   - ChatGPT: activar `Modo validación` (envía `validate_only: true`) y revisar la pestaña de conversiones de Ads Manager.
8. Confirmar deduplicación cliente/servidor usando el mismo `event_id`.
9. Confirmar que con `require_consent=1` no se disparan eventos sin consentimiento.
