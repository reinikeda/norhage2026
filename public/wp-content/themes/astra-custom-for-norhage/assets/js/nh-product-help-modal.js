(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }

  function setWpformsContext(container, productName, pageUrl) {
    var productInput = qs(".wpforms-field.nh_wpf_product input, .wpforms-field.nh_wpf_product textarea", container);
    var urlInput     = qs(".wpforms-field.nh_wpf_url input, .wpforms-field.nh_wpf_url textarea", container);

    if (productInput) productInput.value = productName || "";
    if (urlInput) urlInput.value = pageUrl || window.location.href;
  }

  function showStatus(container, message) {
    var statusEl = qs(".nh-help-accordion__status", container);
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.classList.add("is-visible");
    setTimeout(function () {
      statusEl.classList.remove("is-visible");
      statusEl.textContent = "";
    }, 8000);
  }

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-nh-help-toggle]");
    if (!btn) return;

    var wrap  = btn.closest("[data-nh-help]");
    var panel = qs("#nh-help-panel", wrap);
    if (!panel) return;

    var willOpen = panel.hasAttribute("hidden");
    btn.setAttribute("aria-expanded", willOpen ? "true" : "false");

    if (willOpen) {
      panel.removeAttribute("hidden");
      setWpformsContext(wrap, btn.getAttribute("data-nh-product"), btn.getAttribute("data-nh-url"));

      setTimeout(function () {
        var focusTarget =
          qs(".wpforms-container input:not([type='hidden']):not([disabled])", panel) ||
          qs(".wpforms-container textarea:not([disabled])", panel);
        if (focusTarget && focusTarget.focus) focusTarget.focus();
      }, 0);
    } else {
      panel.setAttribute("hidden", "");
    }

    e.preventDefault();
  });

  // Close only when the form inside the accordion succeeds
  document.addEventListener("wpformsAjaxSubmitSuccess", function (e) {
    var formId = e && e.detail && (e.detail.formId || e.detail.form_id);
    if (!formId) return;

    var form = document.getElementById("wpforms-form-" + formId);
    if (!form) return;

    var wrap = form.closest("[data-nh-help]");
    if (!wrap) return;

    var panel = qs("#nh-help-panel", wrap);
    var btn   = qs("[data-nh-help-toggle]", wrap);
    if (!panel || !btn) return;

    showStatus(wrap, "✅ Message sent. We’ll reply soon.");

    // collapse after success
    setTimeout(function () {
      panel.setAttribute("hidden", "");
      btn.setAttribute("aria-expanded", "false");
    }, 1500);
  });
})();