(function () {
  const cfg = window.CustomPixelsConfig || {};
  const debug = !!cfg.debugMode;
  const queue = [];

  function log(...args) {
    if (debug) {
      console.log("[custom-pixels]", ...args);
    }
  }

  function hasConsent(payload) {
    if (!cfg.requireConsent) return true;
    if (payload && payload.consent === true) return true;
    return window.customPixelsConsent === true;
  }

  function generateEventId() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
    return "evt_" + Date.now() + "_" + Math.random().toString(36).slice(2, 10);
  }

  function loadMetaPixel(pixelId) {
    if (!pixelId || window.fbq) return;
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
    window.fbq("init", pixelId);
    log("Meta pixel loaded", pixelId);
  }

  function loadTikTokPixel(pixelId) {
    if (!pixelId || window.ttq) return;
    !(function (w, d, t) {
      w.TiktokAnalyticsObject = t;
      var ttq = (w[t] = w[t] || []);
      ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"];
      ttq.setAndDefer = function (t, e) {
        t[e] = function () {
          t.push([e].concat(Array.prototype.slice.call(arguments, 0)));
        };
      };
      for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
      ttq.load = function (e) {
        var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
        ttq._i = ttq._i || {};
        ttq._i[e] = [];
        ttq._i[e]._u = i;
        ttq._t = ttq._t || {};
        ttq._t[e] = +new Date();
        ttq._o = ttq._o || {};
        ttq._o[e] = {};
        var o = document.createElement("script");
        o.type = "text/javascript";
        o.async = true;
        o.src = i + "?sdkid=" + e + "&lib=" + t;
        var a = document.getElementsByTagName("script")[0];
        a.parentNode.insertBefore(o, a);
      };
      ttq.load(pixelId);
      ttq.page();
    })(window, document, "ttq");
    log("TikTok pixel loaded", pixelId);
  }

  function fireBrowser(eventName, customData, eventId) {
    if (cfg.enableMetaBrowser && window.fbq) {
      window.fbq("track", eventName, customData || {}, { eventID: eventId });
    }
    if (cfg.enableTikTokBrowser && window.ttq) {
      window.ttq.track(eventName, Object.assign({}, customData || {}, { event_id: eventId }));
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
      action_source: "website",
      consent,
      user_data: Object.assign({}, cfg.userData || {}, payload.user_data || {}),
      custom_data: payload.custom_data || {},
    };

    if (!consent) {
      log("Consent missing, event queued:", normalized);
      queue.push(normalized);
      return { queued: true };
    }

    fireBrowser(eventName, normalized.custom_data, eventId);

    if (cfg.enableMetaServer || cfg.enableTikTokServer) {
      try {
        const result = await fireServer(normalized);
        log("Server result:", result);
      } catch (err) {
        log(err.message);
      }
    }

    return { sent: true, event_id: eventId };
  }

  function flushQueue() {
    if (!hasConsent({ consent: true })) return;
    while (queue.length > 0) {
      const ev = queue.shift();
      track(ev.event_name, {
        event_id: ev.event_id,
        consent: true,
        user_data: ev.user_data,
        custom_data: ev.custom_data,
      });
    }
  }

  loadMetaPixel(cfg.metaPixelId);
  loadTikTokPixel(cfg.tiktokPixelId);

  // Click tracking
  document.addEventListener("click", function (e) {
    const target = e.target.closest("a, button, [data-track-click]");
    if (!target) return;

    let eventName = "Click";
    let customData = {};

    if (target.hasAttribute("data-track-click")) {
      eventName = target.getAttribute("data-track-click") || "Click";
    } else if (target.tagName.toLowerCase() === "a") {
      const href = target.getAttribute("href");
      if (href && href.startsWith("http") && !href.includes(window.location.hostname)) {
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

  // Form submit tracking
  document.addEventListener("submit", function (e) {
    const form = e.target;
    if (form.tagName.toLowerCase() !== "form") return;

    const action = form.getAttribute("action") || "";
    if (action.includes("?s=") || form.querySelector('input[name="s"]')) return;

    track("CompleteRegistration", {
      custom_data: {
        form_id: form.id || "",
        form_action: action,
      },
    });
  });

  window.CustomPixels = {
    track,
    flushQueue,
  };

  track("PageView", { consent: hasConsent({}) });
})();
