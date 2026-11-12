(function () {
  const cfg = window.CustomPixelsConfig || {};
  const debug = !!cfg.debugMode;
  const queue = [];

  // Anything else is sent with fbq("trackCustom").
  const META_STANDARD_EVENTS = new Set([
    "AddPaymentInfo", "AddToCart", "AddToWishlist", "CompleteRegistration", "Contact", "CustomizeProduct",
    "Donate", "FindLocation", "InitiateCheckout", "Lead", "PageView", "Purchase", "Schedule", "Search",
    "StartTrial", "SubmitApplication", "Subscribe", "ViewContent",
  ]);

  // Plugin event => [OpenAI event, data type]. Keep in sync with class-openai-conversions-api-adapter.php.
  const OPENAI_EVENTS = {
    PageView: ["page_viewed", "contents"],
    ViewContent: ["contents_viewed", "contents"],
    AddToCart: ["items_added", "contents"],
    InitiateCheckout: ["checkout_started", "contents"],
    Purchase: ["order_created", "contents"],
    CompleteRegistration: ["registration_completed", "customer_action"],
    Lead: ["lead_created", "customer_action"],
    Schedule: ["appointment_scheduled", "customer_action"],
    Subscribe: ["subscription_created", "plan_enrollment"],
    StartTrial: ["trial_started", "plan_enrollment"],
  };
  const OPENAI_CONTACT_EVENTS = { phone: "telefono", whatsapp: "whatsapp" };

  function log(...args) {
    if (debug) {
      console.log("[custom-pixels]", ...args);
    }
  }

  // Plugin event => GA4 event. Contact becomes contact_phone / contact_whatsapp.
  const GA4_EVENTS = {
    ViewContent: "view_item",
    AddToCart: "add_to_cart",
    InitiateCheckout: "begin_checkout",
    AddPaymentInfo: "add_payment_info",
    Purchase: "purchase",
    Search: "search",
    CompleteRegistration: "generate_lead",
    Lead: "generate_lead",
    Schedule: "schedule",
    Subscribe: "subscribe",
    StartTrial: "start_trial",
  };

  // statistics => GA4/Clarity storage, marketing => ad pixels. Seeded by the <head> script (Custom_Pixels_Analytics_Tags).
  const consentState = Object.assign(
    { statistics: !cfg.requireConsent, marketing: !cfg.requireConsent },
    window.CustomPixelsConsentState || {}
  );
  if (window.customPixelsConsent === true) {
    consentState.statistics = consentState.marketing = true;
  }

  function hasConsent(payload) {
    if (!cfg.requireConsent) return true;
    if (payload && payload.consent === true) return true;
    return consentState.marketing || window.customPixelsConsent === true;
  }

  function generateEventId() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
    return "evt_" + Date.now() + "_" + Math.random().toString(36).slice(2, 10);
  }

  function getCookie(name) {
    const match = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/[.$?*|{}()[\]\\/+^]/g, "\\$&") + "=([^;]*)"));
    return match ? decodeURIComponent(match[1]) : "";
  }

  function getParam(name) {
    try {
      return new URLSearchParams(window.location.search).get(name) || "";
    } catch (e) {
      return "";
    }
  }

  // Keeps a landing-page query param for the rest of the session.
  function storedParam(name) {
    const value = getParam(name);
    try {
      if (value) sessionStorage.setItem("cp_" + name, value);
      return value || sessionStorage.getItem("cp_" + name) || "";
    } catch (e) {
      return value;
    }
  }

  // Click ids the server can't read from pixel cookies (e.g. when a browser pixel is disabled).
  function clickIds() {
    const ids = {};
    const fbclid = storedParam("fbclid");
    ids.fbc = getCookie("_fbc") || (fbclid ? "fb.1." + Date.now() + "." + fbclid : "");
    ids.fbp = getCookie("_fbp");
    ids.ttclid = storedParam("ttclid");
    Object.keys(ids).forEach((k) => ids[k] || delete ids[k]);
    return ids;
  }

  async function sha256(value) {
    if (!value || !window.crypto || !window.crypto.subtle) return "";
    const buf = await window.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
    return Array.from(new Uint8Array(buf)).map((x) => x.toString(16).padStart(2, "0")).join("");
  }

  // Same rules as Custom_Pixels_Event_Normalizer::normalize_phone(): digits with country code, no "+".
  function normalizePhone(phone) {
    const raw = String(phone || "").trim();
    const hasCountryCode = raw.startsWith("+") || raw.startsWith("00");
    let digits = raw.replace(/\D+/g, "").replace(/^0+/, "");
    const prefix = cfg.defaultPhonePrefix || "";
    if (digits && !hasCountryCode && prefix && !digits.startsWith(prefix)) {
      digits = prefix + digits;
    }
    return digits;
  }

  async function hashedIdentity(userData) {
    const email = userData.email ? String(userData.email).trim().toLowerCase() : "";
    const phone = userData.phone ? normalizePhone(userData.phone) : "";
    const externalId = userData.external_id ? String(userData.external_id).trim() : "";
    return {
      email: await sha256(email),
      phone: await sha256(phone),
      phoneE164: phone ? await sha256("+" + phone) : "",
      externalId: await sha256(externalId),
    };
  }

  /* ---------------------------------------------------------------- Meta */

  function loadMetaPixel(pixelId) {
    if (!pixelId || !cfg.enableMetaBrowser || window.fbq) return;
    !(function (f, b, e, v, n, t, s) {
      if (f.fbq) return;
      n = f.fbq = function () {
        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
      };
      if (!f._fbq) f._fbq = n;
      n.push = n;
      n.loaded = true;
      n.version = "2.0";
      n.queue = [];
      t = b.createElement(e);
      t.async = true;
      t.src = v;
      s = b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t, s);
    })(window, document, "script", "https://connect.facebook.net/en_US/fbevents.js");
    if (!hasConsent({})) window.fbq("consent", "revoke");
    window.fbq("init", pixelId);
    log("Meta pixel loaded", pixelId);
  }

  /* -------------------------------------------------------------- TikTok */

  function loadTikTokPixel(pixelId) {
    if (!pixelId || !cfg.enableTikTokBrowser || window.ttq) return;
    !(function (w, d, t) {
      w.TiktokAnalyticsObject = t;
      var ttq = (w[t] = w[t] || []);
      ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie", "holdConsent", "revokeConsent", "grantConsent"];
      ttq.setAndDefer = function (t, e) {
        t[e] = function () {
          t.push([e].concat(Array.prototype.slice.call(arguments, 0)));
        };
      };
      for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
      ttq.instance = function (t) {
        for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]);
        return e;
      };
      ttq.load = function (e, n) {
        var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
        ttq._i = ttq._i || {};
        ttq._i[e] = [];
        ttq._i[e]._u = i;
        ttq._t = ttq._t || {};
        ttq._t[e] = +new Date();
        ttq._o = ttq._o || {};
        ttq._o[e] = n || {};
        var o = d.createElement("script");
        o.type = "text/javascript";
        o.async = true;
        o.src = i + "?sdkid=" + e + "&lib=" + t;
        var a = d.getElementsByTagName("script")[0];
        a.parentNode.insertBefore(o, a);
      };
      if (!hasConsent({})) ttq.holdConsent();
      ttq.load(pixelId);
    })(window, document, "ttq");
    log("TikTok pixel loaded", pixelId);
  }

  function tiktokProperties(customData) {
    const props = Object.assign({}, customData);
    if (props.value !== undefined && props.value !== "") props.value = Number(props.value);
    if (Array.isArray(props.contents)) {
      props.contents = props.contents.map((item) => {
        if (item && item.id !== undefined && item.content_id === undefined) {
          const copy = Object.assign({ content_id: item.id }, item);
          delete copy.id;
          return copy;
        }
        return item;
      });
    }
    return props;
  }

  /* ------------------------------------------------------ ChatGPT/OpenAI */

  let openaiReady = Promise.resolve(false);
  let openaiIdentityKey = "";
  let openaiUser = {};

  function loadOpenAIPixel(pixelId) {
    if (!pixelId || !cfg.enableOpenAIBrowser || window.oaiq) return Promise.resolve(false);
    !(function (w, d, s, u) {
      if (w.oaiq) return;
      var q = function () {
        q.q.push(arguments);
      };
      q.q = [];
      w.oaiq = q;
      var j = d.createElement(s);
      j.async = true;
      j.src = u;
      var f = d.getElementsByTagName(s)[0];
      f.parentNode.insertBefore(j, f);
    })(window, document, "script", "https://bzrcdn.openai.com/sdk/oaiq.min.js");

    if (!hasConsent({})) window.oaiq("consent", false);

    const externalId = cfg.userData && cfg.userData.external_id ? String(cfg.userData.external_id).trim() : "";
    return sha256(externalId).then((externalIdHash) => {
      openaiUser = {};
      if (cfg.defaultCountry) openaiUser.country = cfg.defaultCountry;
      if (externalIdHash) openaiUser.external_id_sha256 = externalIdHash;

      const init = { pixelId };
      if (Object.keys(openaiUser).length) init.user = openaiUser;
      if (debug) init.debug = true;
      window.oaiq("init", init);
      log("OpenAI pixel loaded", pixelId);
      return true;
    });
  }

  // Re-init with the complete user object once we learn email/phone (e.g. on form submit).
  function openaiIdentify(identity) {
    if (!identity.email && !identity.phone) return;
    const key = identity.email + "|" + identity.phone;
    if (key === openaiIdentityKey) return;
    openaiIdentityKey = key;

    if (identity.email) openaiUser.email_sha256 = identity.email;
    if (identity.phone) openaiUser.phone_number_sha256 = identity.phone;
    window.oaiq("init", { pixelId: cfg.openaiPixelId, user: Object.assign({}, openaiUser) });
  }

  function openaiEvent(eventName, customData) {
    let name, dataType, customName;
    if (OPENAI_EVENTS[eventName]) {
      [name, dataType] = OPENAI_EVENTS[eventName];
    } else if (eventName === "Contact") {
      name = "custom";
      dataType = "custom";
      customName = OPENAI_CONTACT_EVENTS[customData.type] || "contacto";
    } else {
      return null;
    }

    const data = { type: dataType };
    const value = Number(customData.value);
    if (customData.value !== undefined && customData.value !== "" && !isNaN(value) && customData.currency) {
      // Integer minor units: 49.90 EUR => 4990.
      data.amount = Math.round(value * 100);
      data.currency = String(customData.currency).toUpperCase();
    }

    if (dataType !== "customer_action") {
      let contents = [];
      if (Array.isArray(customData.contents)) {
        contents = customData.contents
          .filter((item) => item && (item.id || item.content_id))
          .map((item) => {
            const content = { id: String(item.id || item.content_id) };
            if (item.quantity) content.quantity = parseInt(item.quantity, 10);
            return content;
          });
      } else if (Array.isArray(customData.content_ids)) {
        contents = customData.content_ids.map((id) => ({ id: String(id) }));
      }
      if (contents.length) data.contents = contents;
    }

    return { name, data, customName };
  }

  /* ------------------------------------------------- GA4 / Tag Manager */

  function ga4Items(customData) {
    if (Array.isArray(customData.contents)) {
      return customData.contents
        .filter((item) => item && (item.id || item.content_id))
        .map((item) => {
          const ga = { item_id: String(item.id || item.content_id) };
          if (item.quantity) ga.quantity = parseInt(item.quantity, 10);
          if (item.item_price || item.price) ga.price = Number(item.item_price || item.price);
          return ga;
        });
    }
    if (Array.isArray(customData.content_ids)) {
      return customData.content_ids.map((id) => ({ item_id: String(id) }));
    }
    return [];
  }

  function toSnakeCase(name) {
    return String(name).replace(/([a-z0-9])([A-Z])/g, "$1_$2").replace(/[^A-Za-z0-9_]+/g, "_").toLowerCase();
  }

  // GA4 and GTM apply Consent Mode themselves, so these fire regardless of marketing consent. No user data is sent.
  function fireAnalytics(eventName, customData, eventId) {
    customData = customData || {};

    if (cfg.dataLayerEvents) {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({
        event: "cp_" + toSnakeCase(eventName),
        cp_event_id: eventId,
        cp_data: customData,
      });
    }

    if (!cfg.ga4Events || typeof window.gtag !== "function") return;

    let name = GA4_EVENTS[eventName];
    if (eventName === "Contact") name = "contact_" + toSnakeCase(customData.type || "other");
    if (!name) return;

    const params = { event_id: eventId };
    const value = Number(customData.value);
    if (customData.value !== undefined && customData.value !== "" && !isNaN(value)) {
      params.value = value;
      if (customData.currency) params.currency = String(customData.currency).toUpperCase();
    }
    if (customData.order_id) params.transaction_id = String(customData.order_id);
    if (customData.search_string || customData.search_term) params.search_term = customData.search_string || customData.search_term;
    if (customData.form_id) params.form_id = customData.form_id;
    const items = ga4Items(customData);
    if (items.length) params.items = items;

    window.gtag("event", name, params);
  }

  /* -------------------------------------------------------------- Firing */

  async function fireBrowser(eventName, customData, eventId, userData) {
    customData = customData || {};
    userData = userData || {};

    if (cfg.enableMetaBrowser && window.fbq) {
      const method = META_STANDARD_EVENTS.has(eventName) ? "track" : "trackCustom";
      window.fbq(method, eventName, customData, { eventID: eventId });
    }

    let identity = null;
    if (userData.email || userData.phone) {
      identity = await hashedIdentity(userData);
    }

    if (cfg.enableTikTokBrowser && window.ttq) {
      if (eventName === "PageView") {
        window.ttq.page();
      } else {
        if (identity) {
          const ttUser = {};
          if (identity.email) ttUser.email = identity.email;
          if (identity.phoneE164) ttUser.phone_number = identity.phoneE164;
          if (identity.externalId) ttUser.external_id = identity.externalId;
          window.ttq.identify(ttUser);
        }
        window.ttq.track(eventName, tiktokProperties(customData), { event_id: eventId });
      }
    }

    const oai = cfg.enableOpenAIBrowser ? openaiEvent(eventName, customData) : null;
    if (oai && (await openaiReady) && window.oaiq) {
      if (identity) openaiIdentify(identity);
      const options = { event_id: eventId };
      if (oai.customName) options.custom_event_name = oai.customName;
      window.oaiq("measure", oai.name, oai.data, options);
    }
  }

  async function fireServer(payload) {
    if (!cfg.restUrl) return;
    const res = await fetch(cfg.restUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.restNonce || "",
      },
      body: JSON.stringify(payload),
      credentials: "same-origin",
      keepalive: true,
    });
    if (!res.ok) throw new Error(`Server tracking failed: ${res.status}`);
    return res.json();
  }

  async function track(eventName, payload = {}) {
    const eventId = payload.event_id || generateEventId();
    const consent = hasConsent(payload);

    const normalized = {
      event_name: eventName,
      event_id: eventId,
      source: "browser",
      event_time: Math.floor(Date.now() / 1000),
      event_source_url: window.location.href,
      referrer_url: document.referrer || "",
      action_source: "website",
      consent,
      user_data: Object.assign({}, cfg.userData || {}, clickIds(), payload.user_data || {}),
      custom_data: payload.custom_data || {},
    };

    // Events replayed from the consent queue were already sent to analytics.
    if (!payload._skipAnalytics) {
      try {
        fireAnalytics(eventName, normalized.custom_data, eventId);
      } catch (err) {
        log("Analytics tracking error:", err);
      }
    }

    if (!consent) {
      log("Consent missing, event queued:", normalized);
      queue.push(normalized);
      return { queued: true };
    }

    try {
      await fireBrowser(eventName, normalized.custom_data, eventId, normalized.user_data);
    } catch (err) {
      log("Browser tracking error:", err);
    }

    if (cfg.enableMetaServer || cfg.enableTikTokServer || cfg.enableOpenAIServer) {
      try {
        const result = await fireServer(normalized);
        log("Server result:", result);
      } catch (err) {
        log(err.message);
      }
    }

    return { sent: true, event_id: eventId };
  }

  function applyConsentToPixels(granted) {
    if (window.fbq) window.fbq("consent", granted ? "grant" : "revoke");
    if (window.ttq) {
      if (granted && window.ttq.grantConsent) window.ttq.grantConsent();
      if (!granted && window.ttq.revokeConsent) window.ttq.revokeConsent();
    }
    if (window.oaiq) window.oaiq("consent", !!granted);
  }

  function applyConsentToAnalytics() {
    const v = (granted) => (granted ? "granted" : "denied");
    if (typeof window.gtag === "function") {
      window.gtag("consent", "update", {
        analytics_storage: v(consentState.statistics),
        ad_storage: v(consentState.marketing),
        ad_user_data: v(consentState.marketing),
        ad_personalization: v(consentState.marketing),
      });
    }
    if (typeof window.clarity === "function") {
      window.clarity("consentv2", {
        ad_Storage: v(consentState.marketing),
        analytics_Storage: v(consentState.statistics),
      });
    }
  }

  function flushQueue() {
    if (!hasConsent({})) return;
    applyConsentToPixels(true);
    while (queue.length > 0) {
      const ev = queue.shift();
      track(ev.event_name, {
        event_id: ev.event_id,
        consent: true,
        user_data: ev.user_data,
        custom_data: ev.custom_data,
        _skipAnalytics: true,
      });
    }
  }

  // setConsent({ statistics: true, marketing: false }); omitted categories keep their current value.
  function setConsent(categories) {
    const next = Object.assign({}, consentState, categories || {});
    next.statistics = !!next.statistics;
    next.marketing = !!next.marketing;
    if (next.statistics === consentState.statistics && next.marketing === consentState.marketing) return;

    const marketingChanged = next.marketing !== consentState.marketing;
    Object.assign(consentState, next);
    window.customPixelsConsent = consentState.marketing;
    log("Consent updated:", consentState);

    applyConsentToAnalytics();
    if (marketingChanged) {
      if (consentState.marketing) {
        flushQueue();
      } else {
        applyConsentToPixels(false);
      }
    }
  }

  function grantConsent() {
    setConsent({ statistics: true, marketing: true });
  }

  function revokeConsent() {
    setConsent({ statistics: false, marketing: false });
  }

  // Keeps consent in sync with Complianz / GDPR Cookie Compliance (detected by window.CustomPixelsCmp in <head>).
  function watchCookieBanner() {
    if (!cfg.requireConsent || typeof window.CustomPixelsCmp !== "function") return;
    const sync = function () {
      const detected = window.CustomPixelsCmp();
      if (detected) setConsent(detected);
    };
    ["cmplz_status_change", "cmplz_enable_category", "cmplz_revoke"].forEach((name) => {
      document.addEventListener(name, function () {
        setTimeout(sync, 0);
      });
    });
    // GDPR Cookie Compliance has no public JS event, so its cookie is polled.
    setInterval(sync, 1500);
    sync();
  }

  function interceptMetaEvents() {
    const handleFbqCall = function (target, thisArg, argumentsList) {
      const action = argumentsList[0];
      const eventName = argumentsList[1];
      if ((action === "track" || action === "trackCustom") && eventName === "Form submitted") {
        log("Intercepted 'Form submitted' event. Triggering 'CompleteRegistration'...");
        track("CompleteRegistration", {
          custom_data: argumentsList[2] || {},
        });
      }
      return Reflect.apply(target, thisArg, argumentsList);
    };

    if (!window.fbq) {
      let currentFbq = undefined;
      Object.defineProperty(window, "fbq", {
        get() {
          return currentFbq;
        },
        set(val) {
          if (val && !val.isWrappedByCustomPixels) {
            currentFbq = new Proxy(val, {
              apply: handleFbqCall,
              get(target, prop, receiver) {
                if (prop === "isWrappedByCustomPixels") return true;
                return Reflect.get(target, prop, receiver);
              },
              set(target, prop, value, receiver) {
                return Reflect.set(target, prop, value, receiver);
              }
            });
          } else {
            currentFbq = val;
          }
        },
        configurable: true,
        enumerable: true,
      });
    } else if (!window.fbq.isWrappedByCustomPixels) {
      window.fbq = new Proxy(window.fbq, {
        apply: handleFbqCall,
        get(target, prop, receiver) {
          if (prop === "isWrappedByCustomPixels") return true;
          return Reflect.get(target, prop, receiver);
        },
        set(target, prop, value, receiver) {
          return Reflect.set(target, prop, value, receiver);
        }
      });
    }
  }

  interceptMetaEvents();
  loadMetaPixel(cfg.metaPixelId);
  loadTikTokPixel(cfg.tiktokPixelId);
  openaiReady = loadOpenAIPixel(cfg.openaiPixelId);

  // Click tracking
  document.addEventListener("click", function (e) {
    const target = e.target.closest("a, button, [data-track-click]");
    if (!target) return;

    let eventName = "Click";
    let customData = {};

    if (target.hasAttribute("data-track-click")) {
      eventName = target.getAttribute("data-track-click") || "Click";
    } else if (target.tagName.toLowerCase() === "a") {
      const href = (target.getAttribute("href") || "").trim();
      const lower = href.toLowerCase();
      // The clicked number belongs to the business, not the visitor, so it is not sent as user_data.
      if (lower.startsWith("tel:")) {
        eventName = "Contact";
        customData = { type: "phone" };
      } else if (/(^whatsapp:|wa\.me\/|whatsapp\.com\/)/.test(lower)) {
        eventName = "Contact";
        customData = { type: "whatsapp" };
      } else if (href && href.startsWith("http") && !href.includes(window.location.hostname)) {
        eventName = "OutboundClick";
        customData = { url: href, text: target.innerText.trim() };
      } else {
        return;
      }
    } else {
      return;
    }

    track(eventName, { custom_data: customData });
  });

  // Scroll tracking
  const scrollMarks = new Set();
  window.addEventListener(
    "scroll",
    function () {
      const depth = ((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight) * 100;
      const thresholds = [25, 50, 75, 100];
      for (const t of thresholds) {
        if (depth >= t && !scrollMarks.has(t)) {
          scrollMarks.add(t);
          track("ScrollDepth", { custom_data: { percentage: t } });
        }
      }
    },
    { passive: true }
  );

  // Form submit tracking (capture phase, so it runs before handlers that redirect).
  document.addEventListener("submit", function (e) {
    const form = e.target;
    if (form.tagName.toLowerCase() !== "form") return;

    const action = form.getAttribute("action") || "";
    if (action.includes("?s=") || form.querySelector('input[name="s"]')) return;

    // Extract user data from form fields
    const userData = {};

    // Find email field
    const emailInput = form.querySelector('input[type="email"]') ||
                       form.querySelector('input[name*="email" i]') ||
                       form.querySelector('input[name*="correo" i]') ||
                       form.querySelector('input[name*="mail" i]') ||
                       form.querySelector('input[id*="email" i]') ||
                       form.querySelector('input[id*="correo" i]') ||
                       form.querySelector('input[id*="mail" i]');
    if (emailInput && emailInput.value) {
      userData.email = emailInput.value.trim().toLowerCase();
    }

    // Find phone field
    const phoneInput = form.querySelector('input[type="tel"]') ||
                       form.querySelector('input[name*="phone" i]') ||
                       form.querySelector('input[name*="tel" i]') ||
                       form.querySelector('input[name*="movil" i]') ||
                       form.querySelector('input[name*="celular" i]') ||
                       form.querySelector('input[id*="phone" i]') ||
                       form.querySelector('input[id*="tel" i]') ||
                       form.querySelector('input[id*="movil" i]') ||
                       form.querySelector('input[id*="celular" i]');
    if (phoneInput && phoneInput.value) {
      userData.phone = phoneInput.value.trim();
    }

    track("CompleteRegistration", {
      user_data: userData,
      custom_data: {
        form_id: form.id || "",
        form_action: action,
      },
    });
  }, true);

  window.CustomPixels = {
    track,
    flushQueue,
    grantConsent,
    revokeConsent,
    setConsent,
    getConsent: () => Object.assign({}, consentState),
  };

  track("PageView", { consent: hasConsent({}) });
  watchCookieBanner();
})();
