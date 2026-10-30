(function () {
  document.addEventListener("click", function (e) {
    const reveal = e.target.closest("[data-cp-reveal]");
    if (reveal) {
      const input = document.getElementById(reveal.getAttribute("data-cp-reveal"));
      if (!input) return;
      const hidden = input.type === "password";
      input.type = hidden ? "text" : "password";
      reveal.textContent = hidden ? "Ocultar" : "Mostrar";
      return;
    }

    const copy = e.target.closest("[data-cp-copy]");
    if (copy) {
      const code = copy.parentElement.querySelector("code");
      if (!code || !navigator.clipboard) return;
      navigator.clipboard.writeText(code.textContent).then(function () {
        copy.textContent = "Copiado";
        setTimeout(function () {
          copy.textContent = "Copiar";
        }, 1500);
      });
    }
  });
})();
