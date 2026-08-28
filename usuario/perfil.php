<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin();

$uid = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare('SELECT * FROM Usuarios WHERE IDUsuario = :id LIMIT 1');
    $stmt->execute([':id' => $uid]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[Perfil] ' . $e->getMessage());
    $usuario = null;
}

if (!$usuario) {
    redirecionarComMensagem(BASE . '/usuario/logout.php', 'Sessão inválida.', 'danger');
}

$paginaTitulo = 'Meu Perfil';
$areaAtual    = $usuario['NivelAcesso'] !== 'cliente' ? 'painel' : 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="row g-4 justify-content-center">
    <div class="col-md-8 col-lg-6">

        <div class="card p-4 mb-4 d-flex flex-row align-items-center gap-3">
            <div class="avatar-circle"><?= h(mb_strtoupper(mb_substr($usuario['Nome'], 0, 1))) ?></div>
            <div>
                <h5 class="fw-bold mb-0"><?= h($usuario['Nome']) ?></h5>
                <p class="text-secondary small mb-0"><?= $usuario['NivelAcesso'] !== 'cliente' ? 'Equipe da clínica' : 'Dono de animal' ?></p>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill me-2 text-accent"></i>Meus dados</h6>
            <form action="<?= BASE ?>/usuario/processa_perfil.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="dados">

                <div class="mb-3">
                    <label class="form-label">Nome completo</label>
                    <input type="text" name="nome" class="form-control" required maxlength="100"
                           value="<?= h($usuario['Nome']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" value="<?= h($usuario['Email']) ?>" disabled>
                    <div class="form-text">Para alterar seu e-mail, entre em contato com a clínica.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">WhatsApp</label>
                    <input type="tel" name="telefone" class="form-control" data-mask="tel" maxlength="15"
                           value="<?= h(formatarTelefoneExibicao($usuario['Telefone'])) ?>">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check2 me-1"></i> Salvar dados</button>
                </div>
            </form>
        </div>

        <div class="card p-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-shield-lock me-2 text-accent"></i>Alterar senha</h6>
            <form action="<?= BASE ?>/usuario/processa_perfil.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="senha">

                <div class="mb-3">
                    <label class="form-label">Senha atual</label>
                    <input type="password" name="senha_atual" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="senha_nova" class="form-control" required minlength="4" maxlength="72">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="senha_nova_conf" class="form-control" required maxlength="72">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-accent"><i class="bi bi-key me-1"></i> Alterar senha</button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
