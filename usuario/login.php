<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

$paginaTitulo = 'Entrar';
$areaAtual    = 'publico';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card p-4 mt-2">
            <div class="text-center mb-4">
                <i class="bi bi-heart-pulse-fill" style="font-size:2.5rem;color:var(--accent);"></i>
                <h4 class="fw-bold mt-2 mb-0">Entrar na sua conta</h4>
            </div>

            <form action="<?= BASE ?>/usuario/processa_login.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">

                <div class="mb-3">
                    <label class="form-label" for="identificador">E-mail ou WhatsApp</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" id="identificador" name="identificador" class="form-control"
                               required autocomplete="username"
                               value="<?= h($_GET['identificador'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1" for="senha">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" id="senha" name="senha" class="form-control"
                               required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleSenha">
                            <i class="bi bi-eye" id="iconeSenha"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-3 mb-1">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="lembrar_me" id="lembrarMe" value="1">
                        <label class="form-check-label small text-secondary" for="lembrarMe">
                            Lembrar-me por 30 dias
                        </label>
                    </div>
                    <a href="<?= BASE ?>/usuario/esqueci_senha.php" class="small">Esqueci minha senha</a>
                </div>

                <div class="d-grid mt-3">
                    <button type="submit" class="btn btn-accent btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Entrar
                    </button>
                </div>
            </form>

            <hr class="my-3">
            <p class="text-center text-secondary small mb-0">
                Ainda não tem conta?
                <a href="<?= BASE ?>/usuario/cadastro.php" class="fw-medium">Cadastre-se</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('toggleSenha')?.addEventListener('click', function () {
    const input = document.getElementById('senha');
    const icone = document.getElementById('iconeSenha');
    if (input.type === 'password') {
        input.type = 'text';
        icone.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icone.className = 'bi bi-eye';
    }
});
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
