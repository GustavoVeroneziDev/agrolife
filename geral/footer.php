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
    bootstrap.Toast.getOrCreateInstance(el, { delay: 2000 }).show();
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

// ── Máscara de peso (estilo dinheiro — digita da direita pra esquerda,
// "1500" vira "1,500") — 3 casas decimais (precisão de grama) ──────
// input: campo visível (texto). data-target aponta pro id do campo
// hidden que recebe o valor real, com ponto, pro backend.
function vsMascaraPeso(input) {
    var alvo = input.dataset.target ? document.getElementById(input.dataset.target) : null;
    function fmt() {
        var d = input.value.replace(/\D/g, '').slice(0, 6); // máx 999,999 (cabe no DECIMAL(6,3))
        if (!d) { input.value = ''; if (alvo) alvo.value = ''; return; }
        while (d.length < 4) d = '0' + d;
        var inteiro = d.slice(0, -3).replace(/^0+(?=\d)/, '') || '0';
        var decimal = d.slice(-3);
        input.value = inteiro + ',' + decimal;
        if (alvo) alvo.value = inteiro + '.' + decimal;
    }
    input.addEventListener('input', fmt);
    input.setAttribute('inputmode', 'numeric');
}
document.querySelectorAll('[data-mask="peso"]').forEach(vsMascaraPeso);

// ── Validação de data de nascimento — avisa e bloqueia o envio ANTES
// de mandar pro servidor, não só depois de voltar com flash message.
// O PHP (dataNascimentoValida() em funcoes.php) continua validando de
// novo no backend — isso aqui é só pra dar feedback mais rápido.
function vsValidarNascimento(input) {
    var erro = document.createElement('div');
    erro.className = 'small mt-1';
    erro.style.color = 'var(--cor-perigo)';
    erro.style.display = 'none';
    input.insertAdjacentElement('afterend', erro);

    function checar() {
        if (!input.value) { erro.style.display = 'none'; return true; }
        var hoje = new Date().toISOString().slice(0, 10);
        var d = new Date();
        d.setFullYear(d.getFullYear() - 100);
        var limite = d.toISOString().slice(0, 10);
        var invalido = input.value > hoje || input.value < limite;
        erro.textContent = invalido ? 'Data inválida — não pode ser no futuro nem passar de 100 anos atrás.' : '';
        erro.style.display = invalido ? '' : 'none';
        return !invalido;
    }
    input.addEventListener('input', checar);
    input.addEventListener('change', checar);

    var form = input.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!checar()) { e.preventDefault(); input.focus(); }
        });
    }
}
document.querySelectorAll('[data-validar="nascimento"]').forEach(vsValidarNascimento);
// initPicker()/escHtmlPicker() ficam no <head> de geral/header.php — precisam
// existir antes do footer.php ser incluído (páginas chamam initPicker no
// próprio <script>, que roda antes deste aqui).

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
