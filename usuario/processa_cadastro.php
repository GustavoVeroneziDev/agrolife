<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE . '/usuario/cadastro.php');
    exit;
}

if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    redirecionarComMensagem(BASE . '/usuario/cadastro.php', 'Token inválido. Tente novamente.', 'danger');
}

$nome     = trim($_POST['nome']     ?? '');
$email    = trim($_POST['email']    ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$senha    = $_POST['senha']         ?? '';
$senhaCf  = $_POST['senha_conf']    ?? '';

// cadastro.php já sabe reler ?nome=/?email= pra não fazer a pessoa
// redigitar tudo de novo depois de um erro — só falta usar isso aqui.
$voltarCadastro = BASE . '/usuario/cadastro.php?nome=' . urlencode($nome) . '&email=' . urlencode($email);

if ($nome === '' || $email === '' || $telefone === '' || $senha === '') {
    redirecionarComMensagem($voltarCadastro, 'Preencha todos os campos obrigatórios.', 'warning');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirecionarComMensagem($voltarCadastro, 'E-mail inválido.', 'warning');
}

if (strlen($senha) < 4) {
    redirecionarComMensagem($voltarCadastro, 'A senha deve ter pelo menos 4 caracteres.', 'warning');
}

if ($senha !== $senhaCf) {
    redirecionarComMensagem($voltarCadastro, 'As senhas não coincidem.', 'warning');
}

$telefoneFmt = sanitizarTelefone($telefone);
if ($telefoneFmt === null) {
    redirecionarComMensagem($voltarCadastro, 'Número de WhatsApp inválido. Use o formato (11) 99999-9999.', 'warning');
}

try {
    $check = $pdo->prepare('SELECT IDUsuario FROM Usuarios WHERE Email = :email LIMIT 1');
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        redirecionarComMensagem($voltarCadastro, 'E-mail já cadastrado.', 'warning');
    }

    $id   = gerarUuid();
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO Usuarios (IDUsuario, Nome, Email, Telefone, Senha, NivelAcesso)
         VALUES (:id, :nome, :email, :tel, :senha, \'cliente\')'
    );
    $stmt->execute([
        ':id'    => $id,
        ':nome'  => $nome,
        ':email' => $email,
        ':tel'   => $telefoneFmt,
        ':senha' => $hash,
    ]);
} catch (PDOException $e) {
    error_log('[Cadastro] ' . $e->getMessage());
    redirecionarComMensagem($voltarCadastro, 'Erro ao criar conta. Tente novamente.', 'danger');
}

session_regenerate_id(true);
$_SESSION['usuario_id']   = $id;
$_SESSION['usuario_nome'] = $nome;
$_SESSION['nivel_acesso'] = 'cliente';

redirecionarComMensagem(BASE . '/usuario/meus_animais.php', 'Conta criada com sucesso! Bem-vindo(a).', 'success');
