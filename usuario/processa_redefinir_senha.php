<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE . '/usuario/esqueci_senha.php');
    exit;
}

if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    redirecionarComMensagem(BASE . '/usuario/esqueci_senha.php', 'Token inválido. Tente novamente.', 'danger');
}

$idToken    = trim($_POST['id'] ?? '');
$tokenPlain = trim($_POST['t']  ?? '');
$senha      = $_POST['senha']      ?? '';
$senhaConf  = $_POST['senha_conf'] ?? '';

$idUsuario = validarTokenResetSenha($pdo, $idToken, $tokenPlain);
if (!$idUsuario) {
    redirecionarComMensagem(BASE . '/usuario/esqueci_senha.php', 'Esse link é inválido ou já expirou. Solicite um novo.', 'warning');
}

$linkVoltar = BASE . '/usuario/redefinir_senha.php?id=' . urlencode($idToken) . '&t=' . urlencode($tokenPlain);

if (strlen($senha) < 4) {
    redirecionarComMensagem($linkVoltar, 'A senha deve ter pelo menos 4 caracteres.', 'warning');
}
if ($senha !== $senhaConf) {
    redirecionarComMensagem($linkVoltar, 'As senhas não coincidem.', 'warning');
}

try {
    $pdo->prepare('UPDATE Usuarios SET Senha = :senha WHERE IDUsuario = :id')
        ->execute([':senha' => password_hash($senha, PASSWORD_DEFAULT), ':id' => $idUsuario]);

    // Invalida qualquer link de reset e sessão "lembrar-me" ativa —
    // quem redefiniu a senha precisa logar de novo em todo lugar.
    $pdo->prepare('DELETE FROM TokensResetSenha WHERE FKUsuario = :id')->execute([':id' => $idUsuario]);
    $pdo->prepare('DELETE FROM TokensLembrarMe WHERE FKUsuario = :id')->execute([':id' => $idUsuario]);
} catch (PDOException $e) {
    error_log('[RedefinirSenha] ' . $e->getMessage());
    redirecionarComMensagem($linkVoltar, 'Erro ao salvar a nova senha. Tente novamente.', 'danger');
}

redirecionarComMensagem(BASE . '/usuario/login.php', 'Senha redefinida com sucesso! Faça login com a nova senha.', 'info');
