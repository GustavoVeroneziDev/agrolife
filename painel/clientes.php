<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'funcionario');

// Cadastro rápido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/clientes.php', 'Token inválido.', 'danger');
    }
    exigirAdmin(BASE . '/painel/clientes.php');
    $nome  = trim($_POST['nome']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel   = trim($_POST['tel']   ?? '');
    $senha = bin2hex(random_bytes(8)); // senha aleatória — o dono pode redefinir depois

    if ($nome === '' || $email === '') {
        redirecionarComMensagem(BASE . '/painel/clientes.php', 'Nome e e-mail são obrigatórios.', 'warning');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirecionarComMensagem(BASE . '/painel/clientes.php', 'E-mail inválido.', 'warning');
    }

    try {
        $chk = $pdo->prepare('SELECT IDUsuario FROM Usuarios WHERE Email = :e LIMIT 1');
        $chk->execute([':e' => $email]);
        if ($chk->fetch()) {
            redirecionarComMensagem(BASE . '/painel/clientes.php', 'E-mail já cadastrado.', 'warning');
        }
        $novoId = gerarUuid();
        $stmt = $pdo->prepare(
            'INSERT INTO Usuarios (IDUsuario, Nome, Email, Telefone, Senha, NivelAcesso)
             VALUES (:id,:nome,:email,:tel,:senha,\'cliente\')'
        );
        $stmt->execute([
            ':id'    => $novoId,
            ':nome'  => $nome,
            ':email' => $email,
            ':tel'   => $tel !== '' ? sanitizarTelefone($tel) : null,
            ':senha' => password_hash($senha, PASSWORD_DEFAULT),
        ]);

        $token = criarTokenResetSenha($pdo, $novoId);
        $link  = urlAbsoluta('/usuario/redefinir_senha.php?id=' . $token['id'] . '&t=' . $token['token']);
        $corpo = '<p>Olá, ' . h($nome) . '!</p>'
               . '<p>Uma conta foi criada para você em ' . h(APP_NOME) . '. Clique no botão abaixo para definir sua senha de acesso:</p>'
               . '<p style="text-align:center;margin:24px 0;">'
               . '<a href="' . h($link) . '" style="background:#0d9488;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">Definir senha</a>'
               . '</p>'
               . '<p style="font-size:13px;color:#6b7c78;">Esse link expira em 24 horas.</p>';
        $enviou = enviarEmail($email, 'Defina sua senha — ' . APP_NOME, emailHtml('Defina sua senha', $corpo));

        $msg = $enviou
            ? 'Dono cadastrado com sucesso! Enviamos um e-mail para ele definir a senha.'
            : 'Dono cadastrado, mas não conseguimos enviar o e-mail de definição de senha — confira o endereço.';
        redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $novoId, $msg, $enviou ? 'success' : 'warning');
    } catch (PDOException $e) {
        error_log('[CadastroDono] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/painel/clientes.php', 'Erro ao cadastrar.', 'danger');
    }
}

$busca = trim($_GET['q'] ?? '');
$pag   = max(1, (int) ($_GET['pag'] ?? 1));
$por   = 20;
$off   = ($pag - 1) * $por;

try {
    $where  = "WHERE u.NivelAcesso = 'cliente' AND u.Ativo = 1";
    $params = [];
    if ($busca !== '') {
        $where .= ' AND (u.Nome LIKE :q OR u.Email LIKE :q OR u.Telefone LIKE :q)';
        $params[':q'] = '%' . $busca . '%';
    }

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM Usuarios u {$where}");
    $cntStmt->execute($params);
    $total = (int) $cntStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT u.IDUsuario, u.Nome, u.Email, u.Telefone, u.MomentoRegistro,
                COUNT(a.IDAnimal) AS TotalAnimais
         FROM Usuarios u
         LEFT JOIN Animais a ON a.FKDono = u.IDUsuario AND a.Ativo = 1
         {$where}
         GROUP BY u.IDUsuario
         ORDER BY u.Nome ASC
         LIMIT :lim OFFSET :off"
    );
    $params[':lim'] = $por;
    $params[':off'] = $off;
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, in_array($k, [':lim', ':off']) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $donos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[Clientes] ' . $e->getMessage());
    $donos = [];
    $total = 0;
}

$totalPag = max(1, (int) ceil($total / $por));

$souAdmin     = ($_SESSION['nivel_acesso'] ?? '') === 'admin';
$paginaTitulo = 'Donos';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0">Donos <span class="text-secondary small">(<?= number_format($total) ?>)</span></h4>
    <?php if ($souAdmin): ?>
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoDono">
            <i class="bi bi-person-plus me-1"></i> Novo dono
        </button>
    <?php endif ?>
</div>

<form class="mb-4" method="GET">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Buscar por nome, e-mail ou telefone..."
            value="<?= h($busca) ?>">
        <button class="btn btn-accent" type="submit">Buscar</button>
        <?php if ($busca): ?>
            <a href="<?= BASE ?>/painel/clientes.php" class="btn btn-outline-secondary">Limpar</a>
        <?php endif ?>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($donos)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                <p>Nenhum dono encontrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--bg-hover);">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="d-none d-md-table-cell email-cell">E-mail</th>
                            <th class="d-none d-md-table-cell">WhatsApp</th>
                            <th class="text-center">Animais</th>
                            <th class="d-none d-md-table-cell">Cadastro</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donos as $d): ?>
                            <tr>
                                <td class="px-4 fw-medium"><?= h($d['Nome']) ?></td>
                                <td class="d-none d-md-table-cell text-secondary small email-cell">
                                    <span title="<?= h($d['Email']) ?>"><?= h($d['Email']) ?></span>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($d['Telefone']): ?>
                                        <a href="<?= h(waLink($d['Telefone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-whatsapp"></i> <?= h(formatarTelefoneExibicao($d['Telefone'])) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= (int) $d['TotalAnimais'] ?></span>
                                </td>
                                <td class="d-none d-md-table-cell small text-secondary"><?= formatarData($d['MomentoRegistro']) ?></td>
                                <td>
                                    <a href="<?= BASE ?>/painel/cliente_detalhe.php?id=<?= h($d['IDUsuario']) ?>"
                                        class="btn btn-sm btn-outline-accent">
                                        <i class="bi bi-eye"></i><span class="d-none d-md-inline ms-1">Ver</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPag > 1): ?>
                <div class="d-flex justify-content-center py-3">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($p = 1; $p <= $totalPag; $p++): ?>
                                <li class="page-item <?= $p === $pag ? 'active' : '' ?>">
                                    <a class="page-link" href="?pag=<?= $p ?>&q=<?= urlencode($busca) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor ?>
                        </ul>
                    </nav>
                </div>
            <?php endif ?>
        <?php endif ?>
    </div>
</div>

<div class="modal fade" id="modalNovoDono" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="cadastrar">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Cadastrar dono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome completo *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WhatsApp</label>
                        <input type="tel" name="tel" class="form-control" data-mask="tel" placeholder="(11) 99999-9999">
                    </div>
                    <p class="small text-secondary mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Uma senha temporária será gerada automaticamente.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent">
                        <i class="bi bi-person-plus me-1"></i> Cadastrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($souAdmin && ($_GET['acao'] ?? '') === 'novo'): ?>
<script>new bootstrap.Modal(document.getElementById('modalNovoDono')).show();</script>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
