<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE . '/usuario/login.php');
    exit;
}

if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    redirecionarComMensagem(BASE . '/usuario/login.php', 'Token inválido. Tente novamente.', 'danger');
}

// Anti brute-force simples via sessão
$tentativas      = $_SESSION['login_tentativas'] ?? 0;
$ultimaTentativa = $_SESSION['login_ultima']      ?? 0;

if ($tentativas >= 5 && (time() - $ultimaTentativa) < 300) {
    redirecionarComMensagem(BASE . '/usuario/login.php', 'Muitas tentativas. Aguarde 5 minutos.', 'warning');
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// login.php já sabe reler ?email= pra não fazer a pessoa redigitar o
// e-mail de novo depois de um erro — só falta usar isso aqui.
$voltarLogin = BASE . '/usuario/login.php?email=' . urlencode($email);

if ($email === '' || $senha === '') {
    redirecionarComMensagem($voltarLogin, 'Preencha e-mail e senha.', 'warning');
}

try {
    $stmt = $pdo->prepare(
        'SELECT IDUsuario, Nome, Email, Senha, NivelAcesso, Ativo FROM Usuarios WHERE Email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[Login] ' . $e->getMessage());
    redirecionarComMensagem($voltarLogin, 'Erro interno. Tente novamente.', 'danger');
}

if (!$usuario || !password_verify($senha, $usuario['Senha'])) {
    $_SESSION['login_tentativas'] = $tentativas + 1;
    $_SESSION['login_ultima']     = time();
    redirecionarComMensagem($voltarLogin, 'E-mail ou senha incorretos.', 'danger');
}

if (!$usuario['Ativo']) {
    redirecionarComMensagem($voltarLogin, 'Conta desativada. Entre em contato com a clínica.', 'warning');
}

// Login bem-sucedido
session_regenerate_id(true);
unset($_SESSION['login_tentativas'], $_SESSION['login_ultima']);

$_SESSION['usuario_id']   = $usuario['IDUsuario'];
$_SESSION['usuario_nome'] = $usuario['Nome'];
$_SESSION['nivel_acesso'] = $usuario['NivelAcesso'];

if (!empty($_POST['lembrar_me'])) {
    criarTokenLembrarMe($pdo, $usuario['IDUsuario']);
}

// Se a sessão expirou no meio de uma página específica (ex: link de
// WhatsApp/e-mail pra um animal), volta direto pra lá em vez do dashboard
// genérico — exigirLogin() grava isso antes de mandar pro login.
$next = $_SESSION['login_next'] ?? null;
unset($_SESSION['login_next']);

if ($next) {
    header('Location: ' . $next);
} elseif (in_array($usuario['NivelAcesso'], ['admin', 'veterinario'], true)) {
    header('Location: ' . BASE . '/painel/index.php');
} else {
    header('Location: ' . BASE . '/usuario/meus_animais.php');
}
exit;
