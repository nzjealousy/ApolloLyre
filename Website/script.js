/* Apollo Lyre landing page script */
document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector("header");
  const menuBtn = document.getElementById("menuBtn");
  const nav = document.getElementById("nav");
  const navLinks = nav ? Array.from(nav.querySelectorAll('a[href^="#"]')) : [];

  /* ---------- mobile menu ---------- */
  function setMenu(open) {
    if (!nav || !menuBtn) return;
    nav.classList.toggle("open", open);
    menuBtn.setAttribute("aria-expanded", String(open));
    menuBtn.textContent = open ? "✕" : "☰";
  }

  if (menuBtn && nav) {
    menuBtn.setAttribute("aria-expanded", "false");
    menuBtn.setAttribute("aria-controls", "nav");

    // open / close with the button
    menuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      setMenu(!nav.classList.contains("open"));
    });

    // close after choosing a link
    navLinks.forEach((a) => a.addEventListener("click", () => setMenu(false)));

    // close when clicking outside the menu
    document.addEventListener("click", (e) => {
      if (!nav.contains(e.target) && !menuBtn.contains(e.target))
        setMenu(false);
    });

    // close with the esc key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") setMenu(false);
    });

    // reset when the window grows past the mobile breakpoint
    window.addEventListener("resize", () => {
      if (window.innerWidth > 720) setMenu(false);
    });
  }

  /* ---------- Header shadow  ---------- */
  function updateHeader() {
    if (!header) return;
    header.style.boxShadow =
      window.scrollY > 10 ? "0 6px 24px rgba(0, 0, 0, 0.35)" : "none";
  }
  updateHeader();
  window.addEventListener("scroll", updateHeader, { passive: true });

  /* ---------- Highlight the nav link of the section in view ---------- */
  const sections = navLinks
    .map((a) => document.querySelector(a.getAttribute("href")))
    .filter(Boolean);

  if ("IntersectionObserver" in window && sections.length) {
    const spy = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          navLinks.forEach((a) => {
            const active = a.getAttribute("href") === "#" + entry.target.id;
            a.style.color = active ? "var(--text)" : "";
            if (active) a.setAttribute("aria-current", "true");
            else a.removeAttribute("aria-current");
          });
        });
      },
      { rootMargin: "-45% 0px -50% 0px" },
    );

    sections.forEach((s) => spy.observe(s));
  }
});

/* App and auth pages: mark the current page and run the mobile sidebar drawer */
(function () {
  const page = document.body.dataset.page;
  if (!page) return;
  const narrow = matchMedia("(max-width: 900px)");

  function setupDrawer() {
    const toggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    if (!toggle || !sidebar) return;

    function setOpen(open) {
      document.body.classList.toggle("nav-open", open);
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      // Keeps hidden links out of the tab order while the drawer is closed.
      sidebar.inert = narrow.matches && !open;
    }
    /* nav cancel using esc key*/
    toggle.addEventListener("click", () => setOpen(toggle.getAttribute("aria-expanded") !== "true"));

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && document.body.classList.contains("nav-open")) {
        setOpen(false);
        toggle.focus();
      }
    });

    document.addEventListener("click", (e) => {
      if (document.body.classList.contains("nav-open") && !sidebar.contains(e.target) && !toggle.contains(e.target))
        setOpen(false);
    });

    narrow.addEventListener("change", () => setOpen(false));
    setOpen(false);
  }

  document.querySelector(`[data-nav="${page}"]`)?.setAttribute("aria-current", "page");
  setupDrawer();
})();
