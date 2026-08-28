<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

$cargos = [
    'veterinario' => 'Veterinário',
    'vendedor'    => 'Vendedor',
    'atendente'   => 'Atendente',
    'auxiliar'    => 'Auxiliar',
    'outro'       => 'Outro',
];

// Cadastro de funcionário via POST — sempre nível "funcionario" (só vê,
// não escreve/edita/exclui). Nível "admin" é reservado pros donos, setado
// direto no banco, não por aqui.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/equipe.php', 'Token inválido.', 'danger');
    }
    $nome  = trim($_POST['nome']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel   = trim($_POST['tel']   ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $senha = bin2hex(random_bytes(8)); // senha aleatória — o funcionário pode redefinir depois

    if ($nome === '' || $email === '') {
        redirecionarComMensagem(BASE . '/painel/equipe.php', 'Nome e e-mail são obrigatórios.', 'warning');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirecionarComMensagem(BASE . '/painel/equipe.php', 'E-mail inválido.', 'warning');
    }
    if ($cargo !== '' && !isset($cargos[$cargo])) {
        $cargo = '';
    }

    try {
        $chk = $pdo->prepare('SELECT IDUsuario FROM Usuarios WHERE Email = :e LIMIT 1');
        $chk->execute([':e' => $email]);
        if ($chk->fetch()) {
            redirecionarComMensagem(BASE . '/painel/equipe.php', 'E-mail já cadastrado.', 'warning');
        }
        $novoId = gerarUuid();
        $stmt = $pdo->prepare(
            'INSERT INTO Usuarios (IDUsuario, Nome, Email, Telefone, Senha, NivelAcesso, Cargo)
             VALUES (:id,:nome,:email,:tel,:senha,\'funcionario\',:cargo)'
        );
        $stmt->execute([
            ':id'    => $novoId,
            ':nome'  => $nome,
            ':email' => $email,
            ':tel'   => $tel !== '' ? sanitizarTelefone($tel) : null,
            ':senha' => password_hash($senha, PASSWORD_DEFAULT),
            ':cargo' => $cargo !== '' ? $cargo : null,
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
            ? 'Funcionário cadastrado com sucesso! Enviamos um e-mail para ele definir a senha.'
            : 'Funcionário cadastrado, mas não conseguimos enviar o e-mail de definição de senha — confira o endereço.';
        redirecionarComMensagem(BASE . '/painel/equipe.php', $msg, $enviou ? 'success' : 'warning');
    } catch (PDOException $e) {
        error_log('[CadastroFuncionario] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/painel/equipe.php', 'Erro ao cadastrar.', 'danger');
    }
}

try {
    // Não filtra por Cargo — a lista aqui é toda a equipe de acesso
    // "funcionario", independente da função de cada um.
    $vets = $pdo->query(
        "SELECT IDUsuario, Nome, Email, Telefone, Cargo, MomentoRegistro
         FROM Usuarios
         WHERE NivelAcesso = 'funcionario' AND Ativo = 1
         ORDER BY Nome ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[Equipe] ' . $e->getMessage());
    $vets = [];
}

$paginaTitulo = 'Equipe';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0">Equipe <span class="text-secondary small">(<?= count($vets) ?>)</span></h4>
    <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoFuncionario">
        <i class="bi bi-person-plus me-1"></i> Novo funcionário
    </button>
</div>
<p class="text-secondary small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    Funcionário só visualiza — agenda, animais, clientes, catálogo. Criar, editar e excluir é só pra administrador.
</p>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($vets)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-person-badge fs-1 d-block mb-2 opacity-25"></i>
                <p>Nenhum funcionário cadastrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--bg-hover);">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th>Cargo</th>
                            <th class="d-none d-md-table-cell email-cell">E-mail</th>
                            <th class="d-none d-md-table-cell">WhatsApp</th>
                            <th class="d-none d-md-table-cell">Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vets as $v): ?>
                            <tr>
                                <td class="px-4 fw-medium"><?= h($v['Nome']) ?></td>
                                <td class="small">
                                    <?php if ($v['Cargo'] && isset($cargos[$v['Cargo']])): ?>
                                        <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($cargos[$v['Cargo']]) ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif ?>
                                </td>
                                <td class="d-none d-md-table-cell text-secondary small email-cell">
                                    <span title="<?= h($v['Email']) ?>"><?= h($v['Email']) ?></span>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($v['Telefone']): ?>
                                        <a href="<?= h(waLink($v['Telefone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-whatsapp"></i> <?= h(formatarTelefoneExibicao($v['Telefone'])) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif ?>
                                </td>
                                <td class="d-none d-md-table-cell small text-secondary"><?= formatarData($v['MomentoRegistro']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="modal fade" id="modalNovoFuncionario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="cadastrar">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Cadastrar funcionário</h5>
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
                    <div class="mb-3">
                        <label class="form-label">Cargo</label>
                        <?= campoPicker('funcCargo', 'cargo', 'Selecione…', '', obrigatorio: false, comBusca: false) ?>
                        <div class="form-text">Só usado pra mostrar esse funcionário como opção de "veterinário responsável" num agendamento — não muda o que ele pode fazer no sistema.</div>
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

<script>
var CARGOS = <?= json_encode(array_map(fn($valor, $label) => [
    'id' => $valor, 'nome' => $label,
], array_keys($cargos), $cargos), JSON_UNESCAPED_UNICODE) ?>;

initPicker({
    pickerId: 'funcCargoPicker', triggerId: 'funcCargoTrigger', dropdownId: 'funcCargoDropdown',
    searchId: 'funcCargoSearch', listId: 'funcCargoList', hiddenId: 'inpfuncCargoId', labelId: 'funcCargoLabel',
    items: CARGOS,
    chave: function (c) { return c.id; },
    renderItem: function (c) { return { title: c.nome }; },
    matches: function (c, q) { return c.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nada encontrado.',
});
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
