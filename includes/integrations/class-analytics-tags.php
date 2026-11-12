<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prints GA4, Google Tag Manager and Microsoft Clarity in <head>, plus the consent bootstrap
 * (cookie-banner detection + Consent Mode v2 defaults) that assets/js/tracker.js builds on.
 */
class Custom_Pixels_Analytics_Tags
{
    public static function register()
    {
        add_action('wp_head', array(__CLASS__, 'print_head'), 1);
        add_action('wp_body_open', array(__CLASS__, 'print_body_open'), 1);
    }

    public static function print_head()
    {
        $settings = Custom_Pixels_Settings_Page::get_settings();

        $config = array(
            'requireConsent' => !empty($settings['require_consent']),
            'mooveMarketingCategory' => $settings['moove_marketing_category'] === 'advanced' ? 'advanced' : 'thirdparty',
            'ga4Id' => $settings['ga4_measurement_id'],
            'gtmId' => $settings['gtm_container_id'],
            'clarityId' => $settings['clarity_project_id'],
        );

        // "cmplz-native" stops Complianz from blocking this script: consent is handled inside it.
        ?>
<script class="cmplz-native" data-category="functional" id="custom-pixels-consent">
(function (w, d, cfg) {
  function cookie(name) {
    var m = d.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
    return m ? decodeURIComponent(m[1]) : null;
  }

  // Returns {statistics, marketing} from Complianz or GDPR Cookie Compliance (Moove), or null if neither is present.
  w.CustomPixelsCmp = function () {
    if (typeof w.cmplz_has_consent === "function") {
      return { statistics: !!w.cmplz_has_consent("statistics"), marketing: !!w.cmplz_has_consent("marketing") };
    }
    var stats = cookie("cmplz_statistics"), mkt = cookie("cmplz_marketing");
    if (stats !== null || mkt !== null) {
      return { statistics: stats === "allow", marketing: mkt === "allow" };
    }
    var moove = cookie("moove_gdpr_popup");
    if (moove) {
      try {
        var o = JSON.parse(moove);
        return { statistics: String(o.thirdparty) === "1", marketing: String(o[cfg.mooveMarketingCategory]) === "1" };
      } catch (e) {}
    }
    return null;
  };

  var state = cfg.requireConsent ? (w.CustomPixelsCmp() || { statistics: false, marketing: false }) : { statistics: true, marketing: true };
  w.CustomPixelsConsentState = state;

  function v(granted) { return granted ? "granted" : "denied"; }

  if (cfg.ga4Id || cfg.gtmId) {
    w.dataLayer = w.dataLayer || [];
    w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
    w.gtag("consent", "default", {
      analytics_storage: v(state.statistics),
      ad_storage: v(state.marketing),
      ad_user_data: v(state.marketing),
      ad_personalization: v(state.marketing),
      wait_for_update: 500
    });
  }

  if (cfg.gtmId) {
    w.dataLayer.push({ "gtm.start": new Date().getTime(), event: "gtm.js" });
    var g = d.createElement("script"); g.async = true;
    g.src = "https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(cfg.gtmId);
    d.head.appendChild(g);
  }

  if (cfg.ga4Id) {
    var a = d.createElement("script"); a.async = true;
    a.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(cfg.ga4Id);
    d.head.appendChild(a);
    w.gtag("js", new Date());
    w.gtag("config", cfg.ga4Id);
  }

  if (cfg.clarityId) {
    (function (c, l, a, r, i, t, y) {
      c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
      t = l.createElement(r); t.async = 1; t.src = "https://www.clarity.ms/tag/" + encodeURIComponent(i);
      y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
    })(w, d, "clarity", "script", cfg.clarityId);
    w.clarity("consentv2", { ad_Storage: v(state.marketing), analytics_Storage: v(state.statistics) });
  }
})(window, document, <?php echo wp_json_encode($config); ?>);
</script>
        <?php
    }

    public static function print_body_open()
    {
        $settings = Custom_Pixels_Settings_Page::get_settings();
        if (empty($settings['gtm_container_id'])) {
            return;
        }
        ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr(rawurlencode($settings['gtm_container_id'])); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <?php
    }
}
