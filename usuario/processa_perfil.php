<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE . '/usuario/perfil.php');
    exit;
}

if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Token inválido. Tente novamente.', 'danger');
}

$uid  = $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'dados') {
    $nome     = trim($_POST['nome']     ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if ($nome === '') {
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Nome é obrigatório.', 'warning');
    }

    $telefoneFmt = $telefone !== '' ? sanitizarTelefone($telefone) : null;
    if ($telefone !== '' && $telefoneFmt === null) {
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Número de WhatsApp inválido.', 'warning');
    }

    try {
        // E-mail não é editável por aqui — só a clínica pode alterar
        $pdo->prepare('UPDATE Usuarios SET Nome = :nome, Telefone = :tel WHERE IDUsuario = :id')
            ->execute([':nome' => $nome, ':tel' => $telefoneFmt, ':id' => $uid]);

        $_SESSION['usuario_nome'] = $nome;
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Dados atualizados com sucesso!', 'success');
    } catch (PDOException $e) {
        error_log('[Perfil] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Erro ao atualizar dados.', 'danger');
    }
}

if ($acao === 'senha') {
    $atual = $_POST['senha_atual']      ?? '';
    $nova  = $_POST['senha_nova']       ?? '';
    $conf  = $_POST['senha_nova_conf']  ?? '';

    if ($atual === '' || $nova === '' || $conf === '') {
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Preencha todos os campos de senha.', 'warning');
    }
    if (strlen($nova) < 4) {
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'A nova senha deve ter pelo menos 4 caracteres.', 'warning');
    }
    if ($nova !== $conf) {
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'As senhas não coincidem.', 'warning');
    }

    try {
        $stmt = $pdo->prepare('SELECT Senha FROM Usuarios WHERE IDUsuario = :id LIMIT 1');
        $stmt->execute([':id' => $uid]);
        $hashAtual = $stmt->fetchColumn();

        if (!$hashAtual || !password_verify($atual, $hashAtual)) {
            redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Senha atual incorreta.', 'danger');
        }

        $pdo->prepare('UPDATE Usuarios SET Senha = :senha WHERE IDUsuario = :id')
            ->execute([':senha' => password_hash($nova, PASSWORD_DEFAULT), ':id' => $uid]);

        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Senha alterada com sucesso!', 'success');
    } catch (PDOException $e) {
        error_log('[Perfil][Senha] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/usuario/perfil.php', 'Erro ao alterar senha.', 'danger');
    }
}

header('Location: ' . BASE . '/usuario/perfil.php');
exit;
