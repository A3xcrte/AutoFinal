(async () => {
  /* Carga el inventario desde la base de datos; si no hay servidor, usa data.js */
  const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  try {
    const r = await fetch(`${CONFIG.api}?r=vehicles`, { signal: AbortSignal.timeout(3000) });
    if (r.ok) {
      const l = await r.json();
      if (Array.isArray(l)) l.forEach((v) => AUTOS.push({
        id: 1000 + v.id, nombre: esc(`${v.marca} ${v.modelo}`), tipo: v.tipo, condicion: v.condicion, particular: v.rol !== "admin",
        precio: v.precio, anio: v.anio, km: v.km, combustible: v.combustible, transmision: v.transmision,
        descripcion: esc(v.descripcion || ""), image: v.imagenes[0] ? `uploads/${v.imagenes[0]}` : "",
        whatsapp: v.rol === "admin" ? undefined : (v.telefono.length === 10 ? "52" + v.telefono : v.telefono)
      }));
    }
  } catch {}


  const $ = (id) => document.getElementById(id);
  const money = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN", maximumFractionDigits: 0 });
  const waUrl = (t, n = CONFIG.whatsapp) => `https://wa.me/${n}?text=${encodeURIComponent(t)}`;
  const WA_ICON = `<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15L2 22l5.1-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.200Zm-3-12.700c-.3 0-.6.200-.8.500-.4.600-.5 1.400 0 2.400a9 9 0 0 0 4.200 3.800c1.300.5 2 .4 2.600 0 .3-.2.5-.5.5-.9l-1.600-.9-.7.700a5.500 5.500 0 0 1-2.300-2.100l.6-.7L9 7.500Z"/></svg>`;
  const kmFmt = (n) => (n === 0 ? "0 km" : n.toLocaleString("es-MX") + " km");

  /* ---------- Siluetas por tipo de carrocería ---------- */
  const BODY = {
    "Sedán": "M12 68Q12 56 26 54L58 48Q76 28 108 28H148Q170 28 186 46L214 52Q228 56 228 68V74H12Z",
    "SUV": "M12 68Q12 54 26 52L52 46Q60 22 92 22H176Q192 22 198 44L216 50Q228 54 228 68V74H12Z",
    "Coupé": "M12 70Q12 60 28 57L70 50Q92 34 120 34H140Q164 34 180 50L214 56Q228 60 228 70V74H12Z",
    "Pickup": "M12 68Q12 54 26 52L50 46Q58 26 84 26H120L132 46H228V74H12Z",
    "Hatchback": "M12 68Q12 56 26 54L56 48Q70 28 100 28H170Q190 28 200 50L222 54Q228 58 228 68V74H12Z"
  };
  const silhouette = (tipo) =>
    `<svg viewBox="0 0 240 96" role="img" aria-label="${tipo}"><path d="${BODY[tipo] || BODY["Sedán"]}" fill="currentColor"/>` +
    `<circle cx="62" cy="76" r="14" fill="#16191c"/><circle cx="178" cy="76" r="14" fill="#16191c"/>` +
    `<circle cx="62" cy="76" r="6" fill="#e3e8e8"/><circle cx="178" cy="76" r="6" fill="#e3e8e8"/></svg>`;
  const picture = (a) => (a.image ? `<img src="${a.image}" alt="${a.nombre}" loading="lazy">` : silhouette(a.tipo));
  const tag = (a) => `<span class="tag${a.condicion === "Nuevo" ? " new" : ""}">${a.particular ? "Particular" : a.condicion}</span>`;

  /* ---------- Listado de autos (inicio e inventario) ---------- */
  const grid = $("grid");
  if (grid) {
    const filters = $("filters");
    const f = filters ? { q: $("f-q"), cond: $("f-cond"), tipo: $("f-tipo"), max: $("f-max"), sort: $("f-sort") } : null;

    if (f) {
      ["Sedán", "SUV", "Pickup", "Coupé", "Hatchback"].forEach((t) => f.tipo.add(new Option(t, t)));
      const qs = new URLSearchParams(location.search);
      f.tipo.value = qs.get("tipo") || "";
      f.max.value = qs.get("max") || "";
      Object.values(f).forEach((el) => el.addEventListener(el === f.q ? "input" : "change", render));
      filters.addEventListener("submit", (e) => e.preventDefault());
      $("reset").addEventListener("click", () => { Object.values(f).forEach((el) => (el.value = "")); render(); });
    }

    function render() {
      let list = [...AUTOS];
      if (f) {
        list = list.filter((a) =>
          (!f.q.value || a.nombre.toLowerCase().includes(f.q.value.trim().toLowerCase())) &&
          (!f.cond.value || a.condicion === f.cond.value) &&
          (!f.tipo.value || a.tipo === f.tipo.value) &&
          (!f.max.value || a.precio <= +f.max.value));
        if (f.sort.value === "asc") list.sort((a, b) => a.precio - b.precio);
        if (f.sort.value === "desc") list.sort((a, b) => b.precio - a.precio);
        if (f.sort.value === "km") list.sort((a, b) => a.km - b.km);
      } else {
        list = list.slice(0, 3);
      }

      grid.innerHTML = list.map((a) => `
        <article class="sticker">
          <div class="pic">${picture(a)}${tag(a)}</div>
          <div class="info">
            <div><h3>${a.nombre}</h3><p>${a.tipo} · ${a.transmision}</p></div>
            <dl class="specs">
              <div><dt>Año</dt><dd>${a.anio}</dd></div>
              <div><dt>Km</dt><dd>${kmFmt(a.km)}</dd></div>
              <div><dt>Motor</dt><dd>${a.combustible}</dd></div>
            </dl>
          </div>
          <div class="price"><strong>${money.format(a.precio)}</strong>
            <button class="btn btn-dark" data-id="${a.id}" type="button">Ver detalles</button></div>
        </article>`).join("");

      if ($("count")) $("count").textContent = list.length === 1 ? "1 auto disponible" : `${list.length} autos disponibles`;
      if ($("empty")) {
        const none = AUTOS.length === 0;
        $("empty").hidden = list.length > 0;
        $("empty").querySelector("p").textContent = none ? "Aún no hay autos publicados. Vuelve pronto o escríbenos por WhatsApp." : "No hay autos con esos filtros. Quita alguno para ver más opciones.";
        $("reset").hidden = none;
      } else if (!list.length) {
        grid.innerHTML = '<p class="lead" style="grid-column:1/-1">Aún no hay autos publicados. Vuelve pronto o escríbenos por WhatsApp.</p>';
      }
    }

    /* Detalle con calculadora de mensualidad */
    const dlg = $("detail");
    grid.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-id]");
      if (!btn || !dlg) return;
      const a = AUTOS.find((x) => x.id === +btn.dataset.id);
      dlg.innerHTML = `
        <div class="pic">${picture(a)}${tag(a)}</div>
        <div class="d-body">
          <h3 id="d-title">${a.nombre} ${a.anio}</h3>
          <p>${a.descripcion}</p>
          <dl class="specs">
            <div><dt>Tipo</dt><dd>${a.tipo}</dd></div>
            <div><dt>Transmisión</dt><dd>${a.transmision}</dd></div>
            <div><dt>Kilometraje</dt><dd>${kmFmt(a.km)}</dd></div>
            <div><dt>Motor</dt><dd>${a.combustible}</dd></div>
          </dl>
          <div class="d-price"><span>Precio de lista</span><strong>${money.format(a.precio)}</strong></div>
          <div class="calc">
            <div class="row">
              <div class="field"><label for="c-eng">Enganche</label><select id="c-eng"><option value="10">10%</option><option value="20" selected>20%</option><option value="30">30%</option><option value="40">40%</option></select></div>
              <div class="field"><label for="c-plazo">Plazo</label><select id="c-plazo"><option value="24">24 meses</option><option value="36">36 meses</option><option value="48" selected>48 meses</option><option value="60">60 meses</option></select></div>
            </div>
            <p class="pay">Mensualidad estimada <strong id="c-out"></strong></p>
            <small>Cálculo ilustrativo con tasa anual de ${CONFIG.tasa}%. Sujeto a aprobación de crédito.</small>
          </div>
          <div class="d-actions">
            <a class="btn btn-wa" id="d-wa" target="_blank" rel="noopener">${WA_ICON}Me interesa por WhatsApp</a>
            <button class="btn btn-line" id="close" type="button">Cerrar</button>
          </div>
        </div>`;
      const upd = () => {
        const eng = +$("c-eng").value, n = +$("c-plazo").value, r = CONFIG.tasa / 1200;
        const m = (a.precio * (1 - eng / 100) * r) / (1 - Math.pow(1 + r, -n));
        $("c-out").textContent = money.format(m) + " al mes";
        $("d-wa").href = waUrl(`Hola, me interesa el ${a.nombre} ${a.anio} (${money.format(a.precio)}). Con ${eng}% de enganche a ${n} meses me salen aprox. ${money.format(m)} al mes. ¿Sigue disponible?`, a.whatsapp || CONFIG.whatsapp);
      };
      $("c-eng").onchange = $("c-plazo").onchange = upd;
      upd();
      $("close").onclick = () => dlg.close();
      dlg.showModal();
    });
    if (dlg) dlg.addEventListener("click", (e) => { if (e.target === dlg) dlg.close(); });

    render();
  }

  /* ---------- Menú móvil ---------- */
  const menuBtn = $("menu-btn"), nav = $("nav");
  const setMenu = (open) => { nav.classList.toggle("open", open); menuBtn.setAttribute("aria-expanded", open); };
  menuBtn.addEventListener("click", () => setMenu(!nav.classList.contains("open")));
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") setMenu(false); });

  /* ---------- WhatsApp ---------- */
  if ($("wa-float")) $("wa-float").href = waUrl("Hola, quisiera información sobre sus autos.");
  document.querySelectorAll("[data-msg]").forEach((el) => { el.href = waUrl(el.dataset.msg); el.target = "_blank"; el.rel = "noopener"; });

  const form = $("form");
  if (form) {
    const status = $("status"), sel = $("auto"), nombre = $("nombre");
    AUTOS.forEach((a) => sel.add(new Option(`${a.nombre} ${a.anio} · ${money.format(a.precio)}`, a.id)));
    const pre = new URLSearchParams(location.search).get("auto");
    if (pre) sel.value = pre;
    nombre.addEventListener("input", () => nombre.classList.remove("invalid"));

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      if (!nombre.value.trim()) {
        nombre.classList.add("invalid");
        status.className = "status err";
        status.textContent = "Escribe tu nombre para que el asesor sepa con quién habla.";
        nombre.focus();
        return;
      }
      const a = AUTOS.find((x) => x.id === +sel.value);
      const msg = $("mensaje").value.trim();
      const text = `Hola, soy ${nombre.value.trim()}.` + (a ? ` Me interesa el ${a.nombre} ${a.anio} (${money.format(a.precio)}).` : "") + (msg ? ` ${msg}` : "");
      window.open(waUrl(text), "_blank", "noopener");
      form.reset();
      status.className = "status ok";
      status.textContent = "Se abrió WhatsApp con tu mensaje. Solo falta presionar enviar.";
    });
  }

  $("year").textContent = new Date().getFullYear();
})();
