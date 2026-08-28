<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';

$idToken    = trim($_GET['id'] ?? '');
$tokenPlain = trim($_GET['t']  ?? '');
$idUsuario  = validarTokenResetSenha($pdo, $idToken, $tokenPlain);

if (!$idUsuario) {
    redirecionarComMensagem(BASE . '/usuario/esqueci_senha.php', 'Esse link é inválido ou já expirou. Solicite um novo.', 'warning');
}

$paginaTitulo = 'Definir nova senha';
$areaAtual    = 'publico';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card p-4 mt-2">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill" style="font-size:2.5rem;color:var(--accent);"></i>
                <h4 class="fw-bold mt-2 mb-0">Definir nova senha</h4>
            </div>

            <form action="<?= BASE ?>/usuario/processa_redefinir_senha.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="id" value="<?= h($idToken) ?>">
                <input type="hidden" name="t" value="<?= h($tokenPlain) ?>">

                <div class="mb-3">
                    <label class="form-label" for="senha">Nova senha</label>
                    <input type="password" id="senha" name="senha" class="form-control"
                           placeholder="••••••••" required minlength="4" autocomplete="new-password">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="senha_conf">Confirmar nova senha</label>
                    <input type="password" id="senha_conf" name="senha_conf" class="form-control"
                           placeholder="••••••••" required minlength="4" autocomplete="new-password">
                </div>

                <div class="d-grid mt-3">
                    <button type="submit" class="btn btn-accent btn-lg">
                        <i class="bi bi-check2 me-2"></i> Salvar nova senha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
