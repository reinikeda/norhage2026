document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("theme-toggle");
  if (!container || typeof lottie === "undefined") return;

  // 1. Decide on JSON path
  const path = (
    window.NorhageToggleConfig?.lottiePath ||
    container.dataset.lottiePath
  );
  if (!path) return; // nothing to load

  const body = document.body;
  const key  = "norhage-dark-mode";

  // 2. Load animation
  const anim = lottie.loadAnimation({
    container,
    renderer:  "svg",
    loop:      false,
    autoplay:  false,
    path
  });

  // 3. Define segments
  const toDark  = [0, 30];
  const toLight = [30, 60];

  // 4. On load: restore theme + frame
  const isDark = localStorage.getItem(key) === "enabled";
  body.classList.toggle("dark-mode", isDark);
  anim.goToAndStop(isDark ? toLight[1] : toDark[0], true);

  // 5. On click: toggle + play + persist
  container.addEventListener("click", () => {
    const nowDark = body.classList.toggle("dark-mode");
    localStorage.setItem(key, nowDark ? "enabled" : "disabled");

    if (nowDark)      anim.playSegments(toDark,  true);
    else              anim.playSegments(toLight, true);
  });
});
