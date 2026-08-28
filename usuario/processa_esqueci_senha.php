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

$email = trim($_POST['email'] ?? '');

// Mensagem sempre igual, exista o e-mail ou não — não dá pra alguém
// usar esse formulário pra descobrir quem tem conta no sistema.
$msgGenerica = 'Se esse e-mail estiver cadastrado, enviamos um link para redefinir a senha.';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirecionarComMensagem(BASE . '/usuario/login.php', $msgGenerica, 'info');
}

try {
    $stmt = $pdo->prepare('SELECT IDUsuario, Nome, Ativo FROM Usuarios WHERE Email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    if ($usuario && $usuario['Ativo']) {
        $token = criarTokenResetSenha($pdo, $usuario['IDUsuario']);
        $link  = urlAbsoluta('/usuario/redefinir_senha.php?id=' . $token['id'] . '&t=' . $token['token']);

        $corpo = '<p>Olá, ' . htmlspecialchars($usuario['Nome'], ENT_QUOTES, 'UTF-8') . '!</p>'
               . '<p>Recebemos um pedido para redefinir sua senha. Clique no botão abaixo para escolher uma nova senha:</p>'
               . '<p style="text-align:center;margin:24px 0;">'
               . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="background:#0d9488;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">Redefinir senha</a>'
               . '</p>'
               . '<p style="font-size:13px;color:#6b7c78;">Esse link expira em 24 horas. Se você não pediu essa alteração, pode ignorar este e-mail.</p>';

        enviarEmail($email, 'Redefinir sua senha — ' . APP_NOME, emailHtml('Redefinir senha', $corpo));
    }
} catch (PDOException $e) {
    error_log('[EsqueciSenha] ' . $e->getMessage());
}

redirecionarComMensagem(BASE . '/usuario/login.php', $msgGenerica, 'info');
