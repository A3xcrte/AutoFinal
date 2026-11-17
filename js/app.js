(() => {
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const money = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN", maximumFractionDigits: 0 });
  const say = (el, ok, msg) => { el.className = "status " + (ok ? "ok" : "err"); el.textContent = msg; };

  async function api(r, { method = "GET", json, form } = {}) {
    const o = { method, credentials: "same-origin", headers: { "X-Requested-With": "AutoElite" } };
    if (json) { o.headers["Content-Type"] = "application/json"; o.body = JSON.stringify(json); }
    if (form) o.body = form;
    let res;
    try { res = await fetch(`${CONFIG.api}?r=${r}`, o); }
    catch (e) { throw new Error("No se pudo conectar. Enciende Apache y abre la página con http://localhost/autoelite/"); }
    const txt = await res.text();
    let d;
    try { d = JSON.parse(txt); }
    catch (e) { throw new Error(`El servidor respondió con un error (código ${res.status}): ${txt.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim().slice(0, 220) || "respuesta vacía"}`); }
    if (!res.ok) throw new Error((d.error || "Error inesperado") + (d.detalle ? ` (${d.detalle})` : ""));
    return d;
  }

  const card = (v, extra, actions) => `
    <article class="sticker">
      <div class="pic">${v.imagenes[0] ? `<img src="uploads/${esc(v.imagenes[0])}" alt="${esc(v.marca)} ${esc(v.modelo)}">` : ""}
        <span class="tag ${v.estado === "aprobado" ? "ok" : v.estado === "pendiente" ? "" : "new"}">${esc(v.estado)}</span></div>
      <div class="info"><div><h3>${esc(v.marca)} ${esc(v.modelo)} ${v.anio}</h3><p>${esc(v.tipo)} · ${v.km.toLocaleString("es-MX")} km</p></div>${extra}</div>
      <div class="price"><strong>${money.format(v.precio)}</strong>${actions}</div>
    </article>`;

  (async () => {
    let user = null, err0 = "";
    try { user = (await api("auth/me")).user; } catch (e) { err0 = e.message; }

    /* ---------- Mi cuenta ---------- */
    if ($("auth-box")) {
      const show = () => { $("auth-box").hidden = !!user; $("panel").hidden = !user; if (user) panel(); };

      async function panel() {
        $("hello").textContent = `Hola, ${user.nombre}`;
        $("admin-link").hidden = user.role !== "admin";
        const [ns, mine] = await Promise.all([api("notifications"), api("vehicles/mine")]);
        $("notifs").innerHTML = ns.length
          ? ns.map((n) => `<li class="${+n.leida ? "" : "new"}"><strong>${esc(n.titulo)}</strong><span>${esc(n.cuerpo)}</span></li>`).join("")
          : "<li><span>Aún no tienes notificaciones.</span></li>";
        if (ns.some((n) => !+n.leida)) api("notifications/read", { method: "POST", json: {} }).catch(() => {});
        $("mine").innerHTML = mine.length
          ? mine.map((v) => card(v, "", `<button class="btn btn-line" data-del="${v.id}" type="button">Eliminar</button>`)).join("")
          : "<p>Todavía no has publicado autos.</p>";
      }

      $("login-form").addEventListener("submit", async (e) => {
        e.preventDefault();
        try { user = (await api("auth/login", { method: "POST", json: { email: $("l-email").value, password: $("l-pass").value } })).user; show(); }
        catch (err) { say($("l-status"), false, err.message); }
      });
      $("register-form").addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
          user = (await api("auth/register", { method: "POST", json: { nombre: $("r-nombre").value, telefono: $("r-tel").value, email: $("r-email").value, password: $("r-pass").value } })).user;
          show();
        } catch (err) { say($("r-status"), false, err.message); }
      });
      $("logout").addEventListener("click", async () => { await api("auth/logout", { method: "POST", json: {} }); user = null; show(); });
      $("mine").addEventListener("click", async (e) => {
        const id = e.target.dataset.del;
        if (!id || !confirm("¿Eliminar este anuncio?")) return;
        await api("vehicles/delete", { method: "POST", json: { id: +id } });
        panel();
      });
      show();
      if (err0) say($("l-status"), false, err0);
    }

    /* ---------- Publicar ---------- */
    if ($("pub-form")) {
      $("pub-form").hidden = !user; $("pub-gate").hidden = !!user;
      $("pub-form").addEventListener("submit", async (e) => {
        e.preventDefault();
        const btn = e.submitter; btn.disabled = true;
        try {
          await api("vehicles/create", { method: "POST", form: new FormData(e.target) });
          e.target.reset();
          say($("pub-status"), true, "Listo. Tu anuncio quedó en revisión; te avisaremos cuando se publique.");
        } catch (err) { say($("pub-status"), false, err.message); }
        btn.disabled = false;
      });
    }

    /* ---------- Administración ---------- */
    if ($("tab-autos")) {
      if (!user || user.role !== "admin") {
        $("tab-autos").innerHTML = "<p>Esta sección es solo para administradores. <a href=\"cuenta.html\"><strong>Inicia sesión</strong></a>.</p>";
        $("stats").hidden = true; document.querySelector(".tabs").hidden = true; return;
      }
      const ESTADOS = ["pendiente", "aprobado", "rechazado", "vendido"];
      const opts = (list, sel) => list.map((x) => `<option${x === sel ? " selected" : ""}>${x}</option>`).join("");
      const fields = (v = {}) => `
        <div class="row"><div class="field"><label>Marca</label><input name="marca" maxlength="40" required value="${esc(v.marca || "")}"></div>
          <div class="field"><label>Modelo</label><input name="modelo" maxlength="60" required value="${esc(v.modelo || "")}"></div></div>
        <div class="row"><div class="field"><label>Año</label><input name="anio" type="number" min="1980" max="2100" required value="${esc(v.anio || "")}"></div>
          <div class="field"><label>Kilometraje</label><input name="km" type="number" min="0" required value="${esc(v.km ?? "")}"></div></div>
        <div class="row"><div class="field"><label>Precio (MXN)</label><input name="precio" type="number" min="1" required value="${esc(v.precio || "")}"></div>
          <div class="field"><label>Condición</label><select name="condicion">${opts(["Nuevo", "Seminuevo"], v.condicion)}</select></div></div>
        <div class="row"><div class="field"><label>Tipo</label><select name="tipo">${opts(["Sedán", "SUV", "Pickup", "Coupé", "Hatchback"], v.tipo)}</select></div>
          <div class="field"><label>Combustible</label><select name="combustible">${opts(["Gasolina", "Diésel", "Híbrido", "Eléctrico"], v.combustible)}</select></div></div>
        <div class="field"><label>Transmisión</label><select name="transmision">${opts(["Manual", "Automática"], v.transmision)}</select></div>
        <div class="field"><label>Descripción</label><textarea name="descripcion" rows="3" maxlength="2000">${esc(v.descripcion || "")}</textarea></div>`;

      let cars = [];
      const stats = async () => {
        const s = await api("admin/stats");
        $("stats").innerHTML = [["publicados", "Publicados"], ["pendientes", "Por revisar"], ["vendidos", "Vendidos"], ["usuarios", "Usuarios"]]
          .map(([k, t]) => `<div class="stat"><strong>${s[k]}</strong><span>${t}</span></div>`).join("");
      };
      const loadCars = async () => {
        const f = $("f-estado").value;
        cars = await api("admin/vehicles" + (f ? `&estado=${f}` : ""));
        $("rows-autos").innerHTML = cars.length ? cars.map((v) => `<tr>
          <td><strong>${esc(v.marca)} ${esc(v.modelo)} ${v.anio}</strong><small>${esc(v.condicion)} · ${esc(v.tipo)} · ${v.km.toLocaleString("es-MX")} km</small></td>
          <td>${esc(v.vendedor)}${v.rol === "admin" ? ' <span class="badge">Concesionaria</span>' : ""}<small>${esc(v.email)} · ${esc(v.telefono)}</small></td>
          <td>${money.format(v.precio)}</td>
          <td><select data-st="${v.id}" aria-label="Estado">${opts(ESTADOS, v.estado)}</select></td>
          <td class="acts"><button class="btn btn-line" data-edit="${v.id}" type="button">Editar</button><button class="btn btn-line" data-del="${v.id}" type="button">Eliminar</button></td></tr>`).join("")
          : '<tr><td colspan="5">No hay autos con ese filtro.</td></tr>';
      };
      const loadUsers = async () => {
        const us = await api("admin/users");
        $("rows-users").innerHTML = us.map((u) => { const me = u.id === user.id; return `<tr>
          <td><strong>${esc(u.nombre)}${me ? " (tú)" : ""}</strong><small>${esc(u.email)} · ${esc(u.telefono)}</small></td>
          <td><select data-role="${u.id}" aria-label="Rol"${me ? " disabled" : ""}>${opts(["user", "admin"], u.role)}</select></td>
          <td>${u.autos}</td>
          <td>${u.activo ? "Activa" : '<span class="badge off">Suspendida</span>'}</td>
          <td class="acts">${me ? "" : `<button class="btn btn-line" data-toggle="${u.id}" data-activo="${u.activo}" type="button">${u.activo ? "Suspender" : "Activar"}</button><button class="btn btn-line" data-udel="${u.id}" type="button">Eliminar</button>`}</td></tr>`; }).join("");
      };
      const refresh = () => Promise.all([stats(), loadCars(), loadUsers()]);
      const guard = (fn) => async (e) => { try { await fn(e); } catch (err) { alert(err.message); } };

      $("new-fields").innerHTML = fields();
      document.querySelectorAll(".tab").forEach((t) => t.addEventListener("click", () => {
        document.querySelectorAll(".tab").forEach((x) => x.classList.toggle("on", x === t));
        ["autos", "users", "nuevo"].forEach((k) => ($("tab-" + k).hidden = k !== t.dataset.tab));
      }));
      $("f-estado").addEventListener("change", guard(loadCars));

      $("rows-autos").addEventListener("change", guard(async (e) => {
        if (!e.target.dataset.st) return;
        await api("vehicles/estado", { method: "POST", json: { id: +e.target.dataset.st, estado: e.target.value } });
        refresh();
      }));
      $("rows-autos").addEventListener("click", guard(async (e) => {
        const del = e.target.dataset.del, ed = e.target.dataset.edit;
        if (del && confirm("¿Eliminar este auto y sus fotos?")) { await api("vehicles/delete", { method: "POST", json: { id: +del } }); refresh(); }
        if (ed) {
          const v = cars.find((c) => c.id === +ed), dlg = $("edit");
          dlg.innerHTML = `<form class="form" id="edit-form" style="border:0;box-shadow:none"><h2 style="font-size:1.4rem">Editar auto</h2>${fields(v)}
            <div class="d-actions"><button class="btn btn-red" type="submit">Guardar cambios</button><button class="btn btn-line" id="edit-cancel" type="button">Cancelar</button></div><p class="status" id="edit-status" role="status"></p></form>`;
          $("edit-cancel").onclick = () => dlg.close();
          $("edit-form").onsubmit = async (ev) => {
            ev.preventDefault();
            try { await api("admin/vehicles/update", { method: "POST", json: { ...Object.fromEntries(new FormData(ev.target)), id: v.id } }); dlg.close(); refresh(); }
            catch (err) { say($("edit-status"), false, err.message); }
          };
          dlg.showModal();
        }
      }));

      $("rows-users").addEventListener("change", guard(async (e) => {
        if (!e.target.dataset.role) return;
        await api("admin/users/update", { method: "POST", json: { id: +e.target.dataset.role, role: e.target.value } });
        refresh();
      }));
      $("rows-users").addEventListener("click", guard(async (e) => {
        const t = e.target.dataset.toggle, d = e.target.dataset.udel;
        if (t) { await api("admin/users/update", { method: "POST", json: { id: +t, activo: e.target.dataset.activo === "1" ? 0 : 1 } }); refresh(); }
        if (d && confirm("Se eliminará la cuenta y también todos sus anuncios. ¿Continuar?")) { await api("admin/users/delete", { method: "POST", json: { id: +d } }); refresh(); }
      }));

      $("new-form").addEventListener("submit", async (e) => {
        e.preventDefault();
        const btn = e.submitter; btn.disabled = true;
        try { await api("vehicles/create", { method: "POST", form: new FormData(e.target) }); e.target.reset(); say($("new-status"), true, "Auto publicado en el inventario."); refresh(); }
        catch (err) { say($("new-status"), false, err.message); }
        btn.disabled = false;
      });
      refresh();
    }
  })();
})();
