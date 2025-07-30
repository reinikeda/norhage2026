document.addEventListener("DOMContentLoaded", function () {
  const toggleButton = document.createElement("button");
  toggleButton.id = "darkModeToggle";
  toggleButton.ariaLabel = "Toggle dark mode";
  toggleButton.style.background = "none";
  toggleButton.style.border = "none";
  toggleButton.style.fontSize = "1.5rem";
  toggleButton.style.cursor = "pointer";
  toggleButton.style.marginLeft = "1rem";
  toggleButton.textContent = "🌙";

  const nav = document.querySelector(".main-navigation");
  if (nav) nav.appendChild(toggleButton);

  const savedTheme = localStorage.getItem("theme");
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;

  if (savedTheme === "dark" || (!savedTheme && prefersDark)) {
    document.body.classList.add("dark-mode");
    document.body.classList.remove("light-mode");
    toggleButton.textContent = "☀️";
  } else {
    document.body.classList.add("light-mode");
    document.body.classList.remove("dark-mode");
    toggleButton.textContent = "🌙";
  }

  toggleButton.addEventListener("click", function () {
    const isDark = document.body.classList.toggle("dark-mode");
    document.body.classList.toggle("light-mode", !isDark);
    toggleButton.textContent = isDark ? "☀️" : "🌙";
    localStorage.setItem("theme", isDark ? "dark" : "light");
  });
});
