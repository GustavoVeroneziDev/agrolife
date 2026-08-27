<?php if ($ehPainel ?? false): ?>
</div><!-- /painel-content -->
<?php else: ?>
</main>

<footer class="border-top py-4 mt-auto" style="background:var(--bg-card);">
    <div class="container-lg text-center text-secondary small">
        <span style="color:var(--accent);font-weight:600;">VetSul</span> &copy; <?= date('Y') ?>
        &nbsp;·&nbsp; Todos os direitos reservados
        &nbsp;·&nbsp; <span style="opacity:.5;font-size:.8em;" title="<?= h(APP_AMBIENTE . ' — ' . APP_BUILD_DATE) ?>">build <?= h(APP_VERSAO) ?></span>
    </div>
</footer>
<?php endif ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal de confirmação global -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
            <div class="modal-body text-center px-4 pt-4 pb-2">
                <i class="bi bi-exclamation-circle mb-3 d-block" style="font-size:2.4rem;color:var(--accent);"></i>
                <p class="fw-semibold mb-0" id="modalConfirmMsg" style="color:var(--text-main);"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-2 pb-4 gap-2">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger px-4" id="modalConfirmOk">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast global -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="vsToastEl" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-medium" id="vsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
function abrirSidebar() {
    document.getElementById('sidebar').classList.add('aberta');
    document.getElementById('sidebarOverlay').classList.add('ativo');
}
function fecharSidebar() {
    document.getElementById('sidebar')?.classList.remove('aberta');
    document.getElementById('sidebarOverlay')?.classList.remove('ativo');
}

// Auto-dismiss alerts após 5s
document.querySelectorAll('.alert.fade').forEach(el => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el)?.close(), 5000);
});

// ── Toast global ─────────────────────────────────────────────
function vsToast(msg, tipo) {
    var el = document.getElementById('vsToastEl');
    el.className = 'toast align-items-center border-0 text-bg-' + (tipo || 'warning');
    document.getElementById('vsToastMsg').textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 4500 }).show();
}

// ── Modal de confirmação global ───────────────────────────────
function vsConfirm(msg, onOk, label) {
    document.getElementById('modalConfirmMsg').textContent = msg;
    var okBtn = document.getElementById('modalConfirmOk');
    okBtn.textContent = label || 'Confirmar';
    var novo = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(novo, okBtn);
    novo.addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('modalConfirm')).hide();
        onOk();
    });
    new bootstrap.Modal(document.getElementById('modalConfirm')).show();
}

// ── Máscara de telefone ───────────────────────────────────────
function vsMascaraTel(input) {
    function fmt() {
        var d = input.value.replace(/\D/g, '').slice(0, 11);
        if (!d)             { input.value = ''; return; }
        if (d.length <= 2)  { input.value = '(' + d; return; }
        if (d.length <= 6)  { input.value = '(' + d.slice(0,2) + ') ' + d.slice(2); return; }
        if (d.length <= 10) { input.value = '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6); return; }
        input.value = '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
    }
    input.addEventListener('input', fmt);
    input.setAttribute('inputmode', 'numeric');
}
document.querySelectorAll('[data-mask="tel"]').forEach(vsMascaraTel);

// ── Picker de busca (dropdown com campo de busca) ──────────────
// Uso: initPicker({ pickerId, triggerId, dropdownId, searchId, listId,
//   hiddenId, labelId, items, chave(item), renderItem(item) -> {title, sub},
//   matches(item, queryLower) -> bool, vazioMsg, onSelect(item) })
function escHtmlPicker(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function initPicker(opts) {
    var aberto = false;
    var selecionado = null;
    var picker   = document.getElementById(opts.pickerId);
    var trigger  = document.getElementById(opts.triggerId);
    var dropdown = document.getElementById(opts.dropdownId);
    var search   = document.getElementById(opts.searchId);
    var list     = document.getElementById(opts.listId);
    var hidden   = document.getElementById(opts.hiddenId);
    var label    = document.getElementById(opts.labelId);
    if (!picker || !trigger) return null;

    function abrir() {
        aberto = true;
        trigger.classList.add('open');
        dropdown.classList.remove('d-none');
        search.value = '';
        renderizar(opts.items);
        setTimeout(function () { search.focus(); }, 40);
        document.addEventListener('click', clickFora, true);
    }
    function fechar() {
        aberto = false;
        trigger.classList.remove('open');
        dropdown.classList.add('d-none');
        document.removeEventListener('click', clickFora, true);
    }
    function clickFora(e) { if (!picker.contains(e.target)) fechar(); }
    function toggle() { aberto ? fechar() : abrir(); }
    function filtrar(q) {
        q = q.toLowerCase();
        renderizar(opts.items.filter(function (it) { return opts.matches(it, q); }));
    }
    function renderizar(lista) {
        list.innerHTML = '';
        if (!lista.length) {
            list.innerHTML = '<div class="picker-empty">' + escHtmlPicker(opts.vazioMsg || 'Nada encontrado.') + '</div>';
            return;
        }
        lista.forEach(function (it) {
            var r = opts.renderItem(it);
            var div = document.createElement('div');
            div.className = 'picker-item' + (selecionado && opts.chave(selecionado) === opts.chave(it) ? ' picker-active' : '');
            div.innerHTML = '<div class="picker-item-titulo">' + escHtmlPicker(r.title) + '</div>'
                + (r.sub ? '<div class="picker-item-sub">' + escHtmlPicker(r.sub) + '</div>' : '');
            div.addEventListener('mousedown', function (e) { e.preventDefault(); selecionar(it); });
            list.appendChild(div);
        });
    }
    function selecionar(it) {
        selecionado = it;
        hidden.value = opts.chave(it);
        var r = opts.renderItem(it);
        label.textContent = r.title + (r.sub ? ' — ' + r.sub : '');
        label.className = 'picker-selected';
        fechar();
        if (opts.onSelect) opts.onSelect(it);
    }

    trigger.addEventListener('click', toggle);
    trigger.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
    search.addEventListener('input', function () { filtrar(this.value); });

    return {
        selecionar: selecionar,
        limpar: function () {
            selecionado = null;
            hidden.value = '';
            label.textContent = opts.placeholder || '';
            label.className = 'picker-placeholder';
        },
        getSelecionado: function () { return selecionado; },
    };
}

// ── data-confirm em forms e botões ────────────────────────────
document.addEventListener('submit', function (e) {
    var form = e.target, msg = form.dataset.confirm;
    if (msg && !form.dataset.confirmed) {
        e.preventDefault();
        vsConfirm(msg, function () { form.dataset.confirmed = '1'; form.submit(); }, form.dataset.confirmLabel);
    }
});
document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el || el.tagName === 'FORM') return;
    e.preventDefault();
    vsConfirm(el.dataset.confirm, function () {
        var form = el.closest('form');
        if (form) { form.dataset.confirmed = '1'; form.submit(); }
        else if (el.href) { location.href = el.href; }
    }, el.dataset.confirmLabel);
});
</script>
</body>
</html>
