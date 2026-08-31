<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'funcionario');

$id = trim($_GET['id'] ?? '');
if (!$id) {
    redirecionarComMensagem(BASE . '/painel/clientes.php', 'Cliente não encontrado.', 'warning');
}

// Cadastro rápido de animal via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'novo_animal') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $id, 'Token inválido.', 'danger');
    }
    exigirAdmin(BASE . '/painel/cliente_detalhe.php?id=' . $id);
    $nome     = trim($_POST['nome']      ?? '');
    $especie  = trim($_POST['especie']   ?? '');
    $raca     = trim($_POST['raca']      ?? '');
    $nasc     = trim($_POST['nascimento'] ?? '');
    $sexo     = trim($_POST['sexo']      ?? '');
    $cor      = trim($_POST['cor']       ?? '');
    $peso     = trim($_POST['peso']      ?? '');
    $obs      = trim($_POST['observacoes'] ?? '');

    if ($nome === '' || $especie === '' || $raca === '' || $sexo === '') {
        redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $id, 'Nome, espécie, raça e sexo são obrigatórios.', 'warning');
    }
    if (!dataNascimentoValida($nasc)) {
        redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $id, 'Data de nascimento inválida — não pode ser no futuro nem passar de 100 anos atrás.', 'warning');
    }

    $foto = !empty($_FILES['foto']['tmp_name']) ? salvarImagemEnviada($_FILES['foto'], 'animais') : null;
    if (!empty($_FILES['foto']['tmp_name']) && $foto === null) {
        redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $id, 'Foto inválida — envie um JPG, PNG ou WEBP de até 5 MB.', 'warning');
    }

    try {
        $novoId = gerarUuid();
        $pdo->prepare(
            'INSERT INTO Animais (IDAnimal, FKDono, FKEspecie, Nome, Raca, DataNascimento, Sexo, Pelagem, PesoKg, Observacoes, FotoUrl)
             VALUES (:id, :dono, :esp, :nome, :raca, :nasc, :sexo, :cor, :peso, :obs, :foto)'
        )->execute([
            ':id'   => $novoId,
            ':dono' => $id,
            ':esp'  => $especie,
            ':nome' => $nome,
            ':raca' => $raca,
            ':nasc' => $nasc ?: null,
            ':sexo' => $sexo,
            ':cor'  => $cor ?: null,
            ':peso' => $peso !== '' ? $peso : null,
            ':obs'  => $obs ?: null,
            ':foto' => $foto,
        ]);
        redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $novoId, 'Animal cadastrado com sucesso!', 'success');
    } catch (PDOException $e) {
        error_log('[NovoAnimal] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $id, 'Erro ao cadastrar animal.', 'danger');
    }
}

try {
    // Sem filtrar por NivelAcesso: um admin pode ser dono de animal também,
    // e essa página precisa abrir certo quando alguém clicar no dono dele.
    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE IDUsuario = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $dono = $stmt->fetch();
    if (!$dono) {
        redirecionarComMensagem(BASE . '/painel/clientes.php', 'Cliente não encontrado.', 'warning');
    }

    // Além do que tá vindo (próxima vacina, próximo agendamento), busca
    // também o que já aconteceu — sem isso, um animal com anos de
    // histórico mas nada agendado no momento parecia idêntico a um
    // cadastro novo em folha, sem nenhuma pista de que já teve vida por
    // aqui.
    $animaisStmt = $pdo->prepare(
        'SELECT a.*, e.Nome AS NomeEspecie, e.Icone AS IconeEspecie,
                (SELECT MIN(rv.ProximaData) FROM RegistrosVacinas rv
                  WHERE rv.FKAnimal = a.IDAnimal AND rv.ProximaData IS NOT NULL) AS ProximaVacina,
                (SELECT MAX(rv2.DataAplicacao) FROM RegistrosVacinas rv2
                  WHERE rv2.FKAnimal = a.IDAnimal) AS UltimaVacinaAplicada,
                (SELECT ag.DataHoraInicio FROM Agendamentos ag
                  WHERE ag.FKAnimal = a.IDAnimal AND ag.Status IN (\'concluido\', \'faltou\', \'cancelado\')
                  ORDER BY ag.DataHoraInicio DESC LIMIT 1) AS UltimoAtendimentoData,
                (SELECT ag.Tipo FROM Agendamentos ag
                  WHERE ag.FKAnimal = a.IDAnimal AND ag.Status IN (\'concluido\', \'faltou\', \'cancelado\')
                  ORDER BY ag.DataHoraInicio DESC LIMIT 1) AS UltimoAtendimentoTipo
         FROM Animais a
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.FKDono = :id AND a.Ativo = 1
         ORDER BY a.Nome ASC'
    );
    $animaisStmt->execute([':id' => $id]);
    $animais = $animaisStmt->fetchAll();

    // Agendamentos ativos dos animais desse dono — pra quem atende o telefone
    // ver na hora se ele tem algo hoje ou chegando, sem precisar abrir a
    // agenda e procurar (mesmo padrão de usuario/meus_animais.php).
    $tiposAgenda = [
        'cirurgia' => 'Cirurgia', 'consulta' => 'Consulta', 'exame' => 'Exame',
        'procedimento' => 'Procedimento', 'observacao' => 'Observação', 'outro' => 'Outro',
    ];
    $agStmt = $pdo->prepare(
        "SELECT ag.*, a.Nome AS NomeAnimal, e.Icone AS IconeEspecie, v.Nome AS NomeVeterinario
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         LEFT JOIN Usuarios v ON v.IDUsuario = ag.FKVeterinario
         WHERE a.FKDono = :id AND ag.Status IN ('pendente', 'confirmado')
         ORDER BY ag.DataHoraInicio ASC"
    );
    $agStmt->execute([':id' => $id]);
    $agendamentosAtivos = $agStmt->fetchAll();

    $hojeStr = date('Y-m-d');
    $agora   = date('Y-m-d H:i:s');
    $agendamentosHoje = array_values(array_filter(
        $agendamentosAtivos,
        fn($ag) => substr($ag['DataHoraInicio'], 0, 10) === $hojeStr
    ));
    $proximoPorAnimal = [];
    foreach ($agendamentosAtivos as $ag) {
        if ($ag['DataHoraInicio'] >= $agora && !isset($proximoPorAnimal[$ag['FKAnimal']])) {
            $proximoPorAnimal[$ag['FKAnimal']] = $ag;
        }
    }

    $especies = $pdo->query('SELECT * FROM Especies ORDER BY Ordem ASC')->fetchAll();
    $racas    = $pdo->query('SELECT IDRaca, FKEspecie, Nome FROM Racas ORDER BY Ordem ASC')->fetchAll();
} catch (PDOException $e) {
    error_log('[ClienteDetalhe] ' . $e->getMessage());
    $animais  = [];
    $especies = [];
    $racas    = [];
    $tiposAgenda = [];
    $agendamentosHoje = [];
    $proximoPorAnimal = [];
}

$souAdmin     = ($_SESSION['nivel_acesso'] ?? '') === 'admin';
$paginaTitulo = h($dono['Nome']);
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE ?>/painel/clientes.php" onclick="voltarInteligente(event)" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0"><?= h($dono['Nome']) ?></h4>
</div>

<?php if (!empty($agendamentosHoje)): ?>
    <h6 class="fw-semibold text-secondary mb-2"><i class="bi bi-calendar-event me-1"></i>Hoje</h6>
    <div class="mb-4">
        <?php foreach ($agendamentosHoje as $ag) renderCardAgendamento($ag, $tiposAgenda) ?>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card p-4">
            <div class="text-center mb-3">
                <div class="avatar-circle mx-auto"><?= h(mb_strtoupper(mb_substr($dono['Nome'], 0, 1))) ?></div>
                <h5 class="fw-bold mt-2 mb-0"><?= h($dono['Nome']) ?></h5>
                <p class="small text-secondary mb-0">Cliente desde <?= formatarData($dono['MomentoRegistro']) ?></p>
            </div>
            <dl class="mb-3">
                <dt class="small text-secondary">E-mail</dt>
                <dd><?= h($dono['Email']) ?></dd>
                <dt class="small text-secondary">WhatsApp</dt>
                <dd>
                    <?php if ($dono['Telefone']): ?>
                        <?= h(formatarTelefoneExibicao($dono['Telefone'])) ?>
                    <?php else: ?>
                        <span class="text-secondary">Não informado</span>
                    <?php endif ?>
                </dd>
                <dt class="small text-secondary">Total de animais</dt>
                <dd><?= count($animais) ?></dd>
            </dl>
            <?php if ($dono['Telefone']): ?>
                <a href="<?= h(waLink($dono['Telefone'])) ?>" target="_blank" class="btn btn-outline-accent w-100">
                    <i class="bi bi-whatsapp me-1"></i> Conversar no WhatsApp
                </a>
            <?php endif ?>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between px-4 py-3">
                <span><i class="bi bi-clipboard2-pulse me-2 text-accent"></i>Animais</span>
                <?php if ($souAdmin): ?>
                    <button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#modalNovoAnimal">
                        <i class="bi bi-plus-lg me-1"></i> Novo animal
                    </button>
                <?php endif ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($animais)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-emoji-smile fs-1 d-block mb-2 opacity-25"></i>
                        <p>Nenhum animal cadastrado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background:var(--bg-hover);">
                                <tr>
                                    <th class="px-4 py-3">Animal</th>
                                    <th class="d-none d-md-table-cell">Espécie</th>
                                    <th>Próximo agendamento</th>
                                    <th>Próxima vacina</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($animais as $a): $prox = $proximoPorAnimal[$a['IDAnimal']] ?? null; ?>
                                    <tr>
                                        <td class="px-4 fw-medium"><?= especieIconeHtml($a['IconeEspecie']) ?> <?= h($a['Nome']) ?></td>
                                        <td class="d-none d-md-table-cell small"><?= h($a['NomeEspecie']) ?><?= $a['Raca'] ? ' · ' . h($a['Raca']) : '' ?></td>
                                        <td class="small">
                                            <?php if ($prox): ?>
                                                <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($tiposAgenda[$prox['Tipo']] ?? $prox['Tipo']) ?></span>
                                                <?= substr($prox['DataHoraInicio'], 0, 10) === date('Y-m-d') ? 'Hoje' : formatarData($prox['DataHoraInicio']) ?>
                                                às <?= date('H:i', strtotime($prox['DataHoraInicio'])) ?>
                                            <?php elseif ($a['UltimoAtendimentoData']): ?>
                                                <span class="text-secondary">
                                                    Último: <?= h($tiposAgenda[$a['UltimoAtendimentoTipo']] ?? $a['UltimoAtendimentoTipo']) ?>
                                                    · <?= formatarData($a['UltimoAtendimentoData']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-secondary">Nenhum atendimento</span>
                                            <?php endif ?>
                                        </td>
                                        <td>
                                            <?php if ($a['ProximaVacina']): ?>
                                                <?= labelSituacaoVacina($a['ProximaVacina']) ?>
                                                <span class="small text-secondary ms-1"><?= formatarData($a['ProximaVacina']) ?></span>
                                            <?php elseif ($a['UltimaVacinaAplicada']): ?>
                                                <span class="badge" style="background:var(--accent-light);color:var(--accent);">
                                                    Última: <?= formatarData($a['UltimaVacinaAplicada']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Nenhuma vacina</span>
                                            <?php endif ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE ?>/painel/animal_detalhe.php?id=<?= h($a['IDAnimal']) ?>" class="btn btn-sm btn-outline-accent">
                                                <i class="bi bi-eye"></i><span class="d-none d-md-inline ms-1">Ver</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovoAnimal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="novo_animal">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Cadastrar animal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Foto</label>
                        <input type="file" name="foto" class="form-control" accept="image/png,image/jpeg,image/webp" capture="environment">
                        <div class="form-text">JPG, PNG ou WEBP — até 5 MB. No celular, dá pra tirar a foto na hora.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome do animal *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Espécie *</label>
                            <?= campoPicker('naEspecie', 'especie', 'Selecione…', 'Buscar espécie…', obrigatorio: true) ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sexo *</label>
                            <?= campoPicker('naSexo', 'sexo', '—', '', obrigatorio: true, comBusca: false) ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Raça *</label>
                        <?= campoPicker('naRaca', 'raca', 'Selecione a espécie primeiro', 'Buscar raça…', obrigatorio: true) ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cor</label>
                        <input type="text" name="cor" class="form-control" placeholder="Ex: Caramelo, Preto e branco…">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Nascimento</label>
                            <input type="date" name="nascimento" class="form-control" data-validar="nascimento" min="<?= date('Y-m-d', strtotime('-100 years')) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Peso (kg)</label>
                            <input type="text" id="naPesoVisivel" class="form-control" data-mask="peso" data-target="naPesoReal" placeholder="0,000" inputmode="numeric">
                            <input type="hidden" name="peso" id="naPesoReal">
                        </div>
                    </div>
                    <div class="mb-1 mt-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-plus-lg me-1"></i> Cadastrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var NA_ESPECIES = <?= json_encode(array_map(fn($e) => [
    'id' => $e['IDEspecie'], 'nome' => $e['Nome'], 'icone' => $e['Icone'],
], $especies), JSON_UNESCAPED_UNICODE) ?>;
var NA_RACAS = <?= json_encode(array_map(fn($r) => [
    'especie' => $r['FKEspecie'], 'nome' => $r['Nome'],
], $racas), JSON_UNESCAPED_UNICODE) ?>;

initAnimalPickers('na', NA_ESPECIES, NA_RACAS);
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
