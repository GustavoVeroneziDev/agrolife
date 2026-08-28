<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

$tiposAgenda = [
    'cirurgia'     => 'Cirurgia',
    'consulta'     => 'Consulta',
    'exame'        => 'Exame',
    'procedimento' => 'Procedimento',
    'observacao'   => 'Observação',
    'outro'        => 'Outro',
];

// Verifica sobreposição de horário pro mesmo veterinário — mesma checagem
// de verdade tanto no cadastro quanto (se precisar) numa futura remarcação.
function agendamentoConflita(PDO $pdo, string $fkVet, string $inicio, string $fim, string $ignorarId = ''): bool
{
    $sql = "SELECT COUNT(*) FROM Agendamentos
            WHERE FKVeterinario = :vet AND Status != 'cancelado'
              AND DataHoraInicio < :fim AND DataHoraFim > :inicio";
    $params = [':vet' => $fkVet, ':inicio' => $inicio, ':fim' => $fim];
    if ($ignorarId !== '') {
        $sql .= ' AND IDAgendamento != :ignorar';
        $params[':ignorar'] = $ignorarId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ((int) $stmt->fetchColumn()) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/agenda.php', 'Token inválido.', 'danger');
    }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'novo_agendamento') {
        $fkAnimal = trim($_POST['animal'] ?? '');
        $fkVet    = trim($_POST['veterinario'] ?? '');
        $tipo     = trim($_POST['tipo'] ?? '');
        $titulo   = trim($_POST['titulo'] ?? '');
        $data     = trim($_POST['data'] ?? '');
        $hora     = trim($_POST['hora'] ?? '');
        $duracao  = (int) ($_POST['duracao'] ?? 30);
        $obs      = trim($_POST['observacoes'] ?? '');

        if ($fkAnimal === '' || $titulo === '' || $data === '' || $hora === '' || !isset($tiposAgenda[$tipo])) {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Animal, tipo, título, data e hora são obrigatórios.', 'warning');
        }

        $inicio = $data . ' ' . $hora . ':00';
        $ts     = strtotime($inicio);
        if (!$ts) {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Data/hora inválida.', 'warning');
        }
        $fim = date('Y-m-d H:i:s', $ts + $duracao * 60);

        if ($fkVet !== '' && agendamentoConflita($pdo, $fkVet, $inicio, $fim)) {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Esse veterinário já tem outro agendamento nesse horário.', 'warning');
        }

        try {
            $pdo->prepare(
                'INSERT INTO Agendamentos (IDAgendamento, FKAnimal, FKVeterinario, Tipo, Titulo, DataHoraInicio, DataHoraFim, Observacoes)
                 VALUES (:id, :animal, :vet, :tipo, :titulo, :inicio, :fim, :obs)'
            )->execute([
                ':id'     => gerarUuid(),
                ':animal' => $fkAnimal,
                ':vet'    => $fkVet ?: null,
                ':tipo'   => $tipo,
                ':titulo' => $titulo,
                ':inicio' => $inicio,
                ':fim'    => $fim,
                ':obs'    => $obs ?: null,
            ]);
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento criado com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log('[NovoAgendamento] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Erro ao criar agendamento.', 'danger');
        }
    }

    if ($acao === 'concluir') {
        $id      = trim($_POST['id'] ?? '');
        $obsPos  = trim($_POST['observacoes_pos'] ?? '');
        $criarRc = !empty($_POST['criar_clinico']);

        if ($id === '') {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento não encontrado.', 'warning');
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM Agendamentos WHERE IDAgendamento = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $ag = $stmt->fetch();
            if (!$ag) {
                redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento não encontrado.', 'warning');
            }

            $fkRegistroClinico = null;
            if ($criarRc) {
                $fkRegistroClinico = gerarUuid();
                $pdo->prepare(
                    'INSERT INTO RegistrosClinicos (IDRegistro, FKAnimal, FKVeterinario, Tipo, Titulo, Anotacoes, DataRegistro)
                     VALUES (:id, :animal, :vet, :tipo, :titulo, :anot, :data)'
                )->execute([
                    ':id'     => $fkRegistroClinico,
                    ':animal' => $ag['FKAnimal'],
                    ':vet'    => $ag['FKVeterinario'],
                    ':tipo'   => $ag['Tipo'],
                    ':titulo' => $ag['Titulo'],
                    ':anot'   => $obsPos ?: null,
                    ':data'   => date('Y-m-d'),
                ]);

                foreach ($_FILES['imagens']['tmp_name'] ?? [] as $i => $tmp) {
                    $arquivo = [
                        'tmp_name' => $tmp,
                        'error'    => $_FILES['imagens']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $_FILES['imagens']['size'][$i] ?? 0,
                    ];
                    $caminho = salvarImagemEnviada($arquivo, 'clinico');
                    if ($caminho !== null) {
                        $pdo->prepare(
                            'INSERT INTO AnexosClinicos (IDAnexo, FKRegistro, CaminhoArquivo, NomeOriginal)
                             VALUES (:id, :reg, :caminho, :nome)'
                        )->execute([
                            ':id'      => gerarUuid(),
                            ':reg'     => $fkRegistroClinico,
                            ':caminho' => $caminho,
                            ':nome'    => $_FILES['imagens']['name'][$i] ?? null,
                        ]);
                    }
                }
            }

            $pdo->prepare(
                "UPDATE Agendamentos SET Status = 'concluido', ObservacoesPos = :obs, FKRegistroClinico = :rc WHERE IDAgendamento = :id"
            )->execute([':obs' => $obsPos ?: null, ':rc' => $fkRegistroClinico, ':id' => $id]);

            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento concluído com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log('[ConcluirAgendamento] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Erro ao concluir agendamento.', 'danger');
        }
    }
}

// Vista salva em cookie — igual padrão da referência (Belos Cílios): lembra a
// última visão escolhida entre visitas, só re-grava quando vem explícita na URL.
if (isset($_GET['vista'])) {
    $vista = $_GET['vista'] === 'mes' ? 'mes' : 'semana';
    setcookie('agenda_vista', $vista, time() + 60 * 60 * 24 * 365, '/');
} else {
    $vista = ($_COOKIE['agenda_vista'] ?? '') === 'mes' ? 'mes' : 'semana';
}

$filtroStatus = trim($_GET['status'] ?? '');
$animalPreId  = trim($_GET['animal'] ?? '');

$mesFiltro = trim($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mesFiltro)) {
    $mesFiltro = date('Y-m');
}

$statusValidos = ['pendente' => 1, 'confirmado' => 1, 'concluido' => 1, 'cancelado' => 1, 'faltou' => 1];

// Se veio de um clique num dia da vista mensal, pula pra semana que contém esse dia
$semanaOffset = (int) ($_GET['semana'] ?? 0);
if ($vista === 'semana' && !empty($_GET['dia']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'])) {
    $segAlvo  = strtotime('monday this week', strtotime($_GET['dia']));
    $segAtual = strtotime('monday this week');
    $semanaOffset = (int) round(($segAlvo - $segAtual) / (7 * 86400));
}

try {
    $procedimentos = $pdo->query(
        "SELECT IDTipo, Categoria, Nome, DuracaoPadraoMinutos FROM TiposProcedimento
         WHERE Ativo = 1 ORDER BY Ordem ASC, Nome ASC"
    )->fetchAll();

    $animais = $pdo->query(
        "SELECT a.IDAnimal, a.Nome, a.FKEspecie, u.Nome AS NomeDono, e.Icone AS IconeEspecie
         FROM Animais a
         JOIN Usuarios u ON u.IDUsuario = a.FKDono
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.Ativo = 1 ORDER BY a.Nome ASC"
    )->fetchAll();

    $vets = $pdo->query(
        "SELECT IDUsuario, Nome FROM Usuarios WHERE NivelAcesso = 'veterinario' AND Ativo = 1 ORDER BY Nome ASC"
    )->fetchAll();

    $porDia   = [];
    $mesGrade = [];

    if ($vista === 'semana') {
        // ── Vista semanal: sempre os 7 dias, de segunda a domingo ──
        $inicioPeriodo = strtotime("monday this week +{$semanaOffset} week");
        $fimPeriodo    = strtotime("sunday this week +{$semanaOffset} week");
        $iniSQL        = date('Y-m-d', $inicioPeriodo);
        $fimSQL        = date('Y-m-d', $fimPeriodo);
        $fimSQLNext    = date('Y-m-d', strtotime($fimSQL . ' +1 day'));

        $where  = 'WHERE ag.DataHoraInicio >= :ini AND ag.DataHoraInicio < :fim';
        $params = [':ini' => $iniSQL, ':fim' => $fimSQLNext];
        if (isset($statusValidos[$filtroStatus])) {
            $where .= ' AND ag.Status = :status';
            $params[':status'] = $filtroStatus;
        } else {
            $where .= " AND ag.Status != 'cancelado'";
        }

        $stmt = $pdo->prepare(
            "SELECT ag.*, a.Nome AS NomeAnimal, e.Icone AS IconeEspecie,
                    u.Nome AS NomeDono, v.Nome AS NomeVeterinario
             FROM Agendamentos ag
             JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
             JOIN Especies e ON e.IDEspecie = a.FKEspecie
             JOIN Usuarios u ON u.IDUsuario = a.FKDono
             LEFT JOIN Usuarios v ON v.IDUsuario = ag.FKVeterinario
             {$where}
             ORDER BY ag.DataHoraInicio ASC"
        );
        $stmt->execute($params);
        $agendamentos = $stmt->fetchAll();

        foreach ($agendamentos as $ag) {
            $dia = substr($ag['DataHoraInicio'], 0, 10);
            $porDia[$dia][] = $ag;
        }
    } else {
        // ── Vista mensal: grade de calendário, cada dia com seus agendamentos
        // de verdade (não só contagem) — alimenta os pontinhos de status e o
        // painel de detalhe que abre inline ao clicar num dia.
        $inicioMes = $mesFiltro . '-01';
        $fimMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $stmt = $pdo->prepare(
            "SELECT ag.IDAgendamento, ag.Tipo, ag.Titulo, ag.DataHoraInicio, ag.Status,
                    a.Nome AS NomeAnimal, e.Icone AS IconeEspecie,
                    u.Nome AS NomeDono, v.Nome AS NomeVeterinario
             FROM Agendamentos ag
             JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
             JOIN Especies e ON e.IDEspecie = a.FKEspecie
             JOIN Usuarios u ON u.IDUsuario = a.FKDono
             LEFT JOIN Usuarios v ON v.IDUsuario = ag.FKVeterinario
             WHERE ag.DataHoraInicio >= :inicio AND ag.DataHoraInicio < :fim
               AND ag.Status != 'cancelado'
             ORDER BY ag.DataHoraInicio ASC"
        );
        $stmt->execute([':inicio' => $inicioMes, ':fim' => $fimMes]);

        $porDiaMes = [];
        $mesJson   = [];
        foreach ($stmt->fetchAll() as $ag) {
            $dia = substr($ag['DataHoraInicio'], 0, 10);
            $porDiaMes[$dia][] = $ag;
            $mesJson[$dia][] = [
                'id'     => $ag['IDAgendamento'],
                'hora'   => date('H:i', strtotime($ag['DataHoraInicio'])),
                'tipo'   => $tiposAgenda[$ag['Tipo']] ?? $ag['Tipo'],
                'titulo' => $ag['Titulo'],
                'animal' => $ag['NomeAnimal'],
                'icone'  => $ag['IconeEspecie'],
                'dono'   => $ag['NomeDono'],
                'vet'    => $ag['NomeVeterinario'],
                'status' => $ag['Status'],
            ];
        }

        $primeiroDiaSemana = (int) date('w', strtotime($inicioMes)); // 0 = domingo
        $diasNoMes         = (int) date('t', strtotime($inicioMes));

        $celulas = array_fill(0, $primeiroDiaSemana, null);
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $dataStr   = sprintf('%s-%02d', $mesFiltro, $d);
            $celulas[] = ['data' => $dataStr, 'dia' => $d, 'ags' => $porDiaMes[$dataStr] ?? []];
        }
        while (count($celulas) % 7 !== 0) {
            $celulas[] = null;
        }
        $mesGrade = array_chunk($celulas, 7);
    }

    $animalPre = null;
    if ($animalPreId) {
        $stmt = $pdo->prepare(
            'SELECT a.IDAnimal, a.Nome, u.Nome AS NomeDono
             FROM Animais a JOIN Usuarios u ON u.IDUsuario = a.FKDono
             WHERE a.IDAnimal = :id AND a.Ativo = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $animalPreId]);
        $animalPre = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log('[Agenda] ' . $e->getMessage());
    $animais = $vets = $agendamentos = $porDia = $mesGrade = $mesJson = $procedimentos = [];
    $animalPre = null;
    // Garante que a vista semanal sempre tem um período pra renderizar,
    // mesmo se a query tiver falhado antes de calculá-lo.
    $inicioPeriodo = $inicioPeriodo ?? strtotime("monday this week +{$semanaOffset} week");
    $fimPeriodo    = $fimPeriodo    ?? strtotime("sunday this week +{$semanaOffset} week");
}

$paginaTitulo = 'Agenda';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<?php
    $mesesPt = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    if ($vista === 'mes') {
        $periodoAnteriorHref = '?vista=mes&mes=' . date('Y-m', strtotime($mesFiltro . '-01 -1 month'));
        $periodoProximoHref  = '?vista=mes&mes=' . date('Y-m', strtotime($mesFiltro . '-01 +1 month'));
        $periodoLabel        = $mesesPt[(int) date('n', strtotime($mesFiltro . '-01'))] . ' de ' . date('Y', strtotime($mesFiltro . '-01'));
        $noPeriodoAtual       = $mesFiltro === date('Y-m');
        $hojeHref             = '?vista=mes&mes=' . date('Y-m');
    } else {
        $periodoAnteriorHref = '?vista=semana&semana=' . ($semanaOffset - 1);
        $periodoProximoHref  = '?vista=semana&semana=' . ($semanaOffset + 1);
        $periodoLabel        = date('d/m', $inicioPeriodo) . ' – ' . date('d/m', $fimPeriodo);
        $noPeriodoAtual       = $semanaOffset === 0;
        $hojeHref             = '?vista=semana&semana=0';
    }
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-calendar-week" style="color:var(--accent);"></i> Agenda
    </h4>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="vista-switch" role="group" aria-label="Visão da agenda">
            <a href="?vista=semana" class="vista-btn <?= $vista === 'semana' ? 'ativo' : '' ?>">
                <i class="bi bi-list-ul me-1"></i> Semana
            </a>
            <a href="?vista=mes&mes=<?= h($mesFiltro) ?>" class="vista-btn <?= $vista === 'mes' ? 'ativo' : '' ?>">
                <i class="bi bi-grid-3x3-gap me-1"></i> Mês
            </a>
        </div>

        <div class="nav-periodo">
            <a href="<?= $periodoAnteriorHref ?>" class="nav-btn" title="Anterior"><i class="bi bi-chevron-left"></i></a>
            <span class="nav-label"><?= h($periodoLabel) ?></span>
            <a href="<?= $periodoProximoHref ?>" class="nav-btn" title="Próximo"><i class="bi bi-chevron-right"></i></a>
        </div>

        <?php if (!$noPeriodoAtual): ?>
            <a href="<?= $hojeHref ?>" class="btn btn-outline-accent btn-sm">Hoje</a>
        <?php endif ?>

        <?php if ($vista === 'semana'): ?>
            <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='?vista=semana&semana=<?= $semanaOffset ?>&status='+this.value">
                <option value="">Sem cancelados</option>
                <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                <option value="confirmado" <?= $filtroStatus === 'confirmado' ? 'selected' : '' ?>>Confirmados</option>
                <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluídos</option>
                <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                <option value="faltou" <?= $filtroStatus === 'faltou' ? 'selected' : '' ?>>Faltas</option>
            </select>
        <?php endif ?>

        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
            <i class="bi bi-calendar-plus me-1"></i> Novo agendamento
        </button>
    </div>
</div>

<?php if ($vista === 'mes'): ?>
    <?php
        $nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $hojeStr   = date('Y-m-d');
    ?>
    <div class="card p-3 mb-3">
        <div class="d-flex flex-wrap gap-3 mb-3">
            <?php foreach (['pendente', 'confirmado', 'concluido', 'faltou'] as $st): ?>
                <span class="small d-flex align-items-center gap-1">
                    <span class="cal-dot cal-dot-<?= $st ?>"></span> <?= h(ucfirst($st === 'concluido' ? 'Concluído' : $st)) ?>
                </span>
            <?php endforeach ?>
        </div>

        <div class="calendario-grade mb-1">
            <?php foreach ($nomesDias as $nd): ?>
                <div class="calendario-cabecalho"><?= $nd ?></div>
            <?php endforeach ?>
        </div>
        <div class="calendario-grade">
            <?php foreach ($mesGrade as $semana): foreach ($semana as $cel): ?>
                <?php if ($cel === null): ?>
                    <div class="calendario-dia calendario-dia-vazio"></div>
                <?php else: ?>
                    <?php $temAgs = !empty($cel['ags']); ?>
                    <div class="calendario-dia <?= $cel['data'] === $hojeStr ? 'calendario-dia-hoje' : '' ?> <?= !$temAgs ? 'calendario-dia-sem-ag' : '' ?>"
                         role="button" tabindex="0"
                         onclick="mostrarDiaMes('<?= $cel['data'] ?>', <?= $cel['dia'] ?>)"
                         onkeydown="if(event.key==='Enter')mostrarDiaMes('<?= $cel['data'] ?>', <?= $cel['dia'] ?>)">
                        <span class="calendario-dia-numero"><?= $cel['dia'] ?></span>
                        <?php if ($temAgs): ?>
                            <div class="cal-dots">
                                <?php foreach (array_slice($cel['ags'], 0, 4) as $ag): ?>
                                    <span class="cal-dot cal-dot-<?= h($ag['Status']) ?>"></span>
                                <?php endforeach ?>
                            </div>
                            <span class="calendario-dia-badge"><?= count($cel['ags']) ?></span>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            <?php endforeach; endforeach ?>
        </div>
    </div>

    <div id="painelDiaMes" class="card mb-4" style="display:none;border-color:var(--accent) !important;">
        <div class="card-header d-flex align-items-center justify-content-between gap-2 px-3 py-2">
            <h6 class="fw-bold mb-0" id="painelDiaMesTitulo"></h6>
            <div class="d-flex gap-2">
                <a href="#" id="painelDiaMesNovo" class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
                    <i class="bi bi-plus-lg me-1"></i> Novo
                </a>
                <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('painelDiaMes').style.display='none';">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div id="painelDiaMesConteudo"></div>
    </div>
<?php else: ?>
    <?php
        $diasSemanaPt = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
        for ($d = 0; $d < 7; $d++):
            $ts    = strtotime("+{$d} days", $inicioPeriodo);
            $dia   = date('Y-m-d', $ts);
            $lista = $porDia[$dia] ?? [];
            $eHoje = $dia === date('Y-m-d');
    ?>
        <div class="card mb-3<?= $eHoje ? ' agenda-dia-hoje' : '' ?>">
            <div class="card-header d-flex flex-wrap align-items-center gap-2 px-3 py-2<?= $eHoje ? ' agenda-dia-hoje-header' : '' ?>">
                <span class="fw-semibold<?= $eHoje ? ' text-accent' : '' ?>"><?= $diasSemanaPt[$d] ?></span>
                <span class="text-secondary small"><?= date('d/m', $ts) ?></span>
                <?php if ($eHoje): ?><span class="badge" style="background:var(--accent);">Hoje</span><?php endif ?>
                <span class="badge bg-secondary ms-auto"><?= count($lista) ?> ag.</span>
            </div>
            <?php if (empty($lista)): ?>
                <div class="text-center py-3 text-secondary small">Sem agendamentos</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($lista as $ag): ?>
                        <li class="list-group-item px-3 py-2" data-id-agendamento="<?= h($ag['IDAgendamento']) ?>">
                            <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap">
                                <span class="fw-bold text-accent" style="min-width:42px;"><?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($tiposAgenda[$ag['Tipo']] ?? $ag['Tipo']) ?></span>
                                        <?= labelStatusAgendamento($ag['Status']) ?>
                                        <span class="fw-medium"><?= especieIconeHtml($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?></span>
                                        <span class="text-secondary small">— <?= h($ag['NomeDono']) ?></span>
                                    </div>
                                    <span class="text-secondary small d-block">
                                        <?= h($ag['Titulo']) ?><?= $ag['NomeVeterinario'] ? ' · ' . h($ag['NomeVeterinario']) : ' · sem veterinário definido' ?>
                                    </span>
                                    <?php if ($ag['Status'] === 'concluido' && $ag['ObservacoesPos']): ?>
                                        <span class="text-secondary small d-block mt-1"><strong>Pós-consulta:</strong> <?= nl2br(h($ag['ObservacoesPos'])) ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="d-flex gap-1 flex-wrap flex-shrink-0">
                                    <?php if ($ag['Status'] === 'pendente'): ?>
                                        <button class="btn btn-sm btn-outline-info btn-acao-agendamento" data-acao="confirmar" data-id="<?= h($ag['IDAgendamento']) ?>">Confirmar</button>
                                        <button class="btn btn-sm btn-outline-danger btn-acao-agendamento" data-acao="cancelar" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Cancelar esse agendamento?">Cancelar</button>
                                    <?php elseif ($ag['Status'] === 'confirmado'): ?>
                                        <button class="btn btn-sm btn-accent btn-concluir"
                                            data-id="<?= h($ag['IDAgendamento']) ?>" data-tipo="<?= h($ag['Tipo']) ?>" data-titulo="<?= h($ag['Titulo']) ?>">
                                            Concluir
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning btn-acao-agendamento" data-acao="marcar_falta" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Marcar falta nesse agendamento?">Faltou</button>
                                        <button class="btn btn-sm btn-outline-danger btn-acao-agendamento" data-acao="cancelar" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Cancelar esse agendamento?">Cancelar</button>
                                    <?php elseif (in_array($ag['Status'], ['concluido', 'cancelado', 'faltou'], true)): ?>
                                        <button class="btn btn-sm btn-outline-secondary btn-acao-agendamento" data-acao="reabrir" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Reabrir esse agendamento?">Reabrir</button>
                                    <?php endif ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    <?php endfor ?>
<?php endif ?>

<div class="modal fade" id="modalNovoAgendamento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="novo_agendamento">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Novo agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Animal *</label>
                        <input type="hidden" name="animal" id="inpAnimalId" required value="<?= $animalPre ? h($animalPre['IDAnimal']) : '' ?>">
                        <div class="picker" id="animalPicker">
                            <div class="picker-trigger" id="animalTrigger" tabindex="0">
                                <span id="animalLabel" class="<?= $animalPre ? 'picker-selected' : 'picker-placeholder' ?>">
                                    <?= $animalPre ? h($animalPre['Nome']) . ' — ' . h($animalPre['NomeDono']) : 'Buscar animal ou dono…' ?>
                                </span>
                                <span class="picker-caret"><i class="bi bi-chevron-down"></i></span>
                            </div>
                            <div class="picker-dropdown d-none" id="animalDropdown">
                                <div class="picker-search-wrap">
                                    <i class="bi bi-search picker-search-icon"></i>
                                    <input type="text" class="picker-search" id="animalSearch" placeholder="Nome do animal ou do dono…" autocomplete="off">
                                </div>
                                <div class="picker-list" id="animalList"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Tipo *</label>
                            <?= campoPicker('agTipo', 'tipo', '—', '', 'consulta', 'Consulta', obrigatorio: true, comBusca: false) ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Procedimento</label>
                            <?= campoPicker('agProc', 'procedimento_ref', 'Personalizado', '', obrigatorio: false, comBusca: false) ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" id="inpTituloAgendamento" class="form-control" placeholder="Ex: Consulta de rotina, Castração…" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duração</label>
                        <select name="duracao" id="selDuracaoAgendamento" class="form-select">
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1 hora</option>
                            <option value="90">1h30</option>
                            <option value="120">2 horas</option>
                        </select>
                        <div class="form-text">Escolher um procedimento acima já preenche isso — pode ajustar se precisar. <a href="<?= BASE ?>/painel/tipos_procedimento.php">Gerenciar procedimentos</a></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Data *</label>
                            <input type="date" name="data" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Hora *</label>
                            <input type="time" name="hora" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Veterinário responsável</label>
                        <?= campoPicker('vetResp', 'veterinario', 'Selecione…', 'Buscar veterinário…') ?>
                        <?php if (empty($vets)): ?>
                            <div class="form-text">Nenhum veterinário cadastrado — <a href="<?= BASE ?>/painel/equipe.php">cadastre um primeiro</a>.</div>
                        <?php endif ?>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-calendar-plus me-1"></i> Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConcluir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="concluir">
                <input type="hidden" name="id" id="concluirId">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Concluir: <span id="concluirTitulo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Observações pós-consulta</label>
                        <textarea name="observacoes_pos" class="form-control" rows="3" placeholder="Como foi, conduta, retorno…"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="criar_clinico" id="concluirCriarClinico" value="1" checked>
                        <label class="form-check-label" for="concluirCriarClinico">
                            Criar registro no histórico clínico do animal com essas observações
                        </label>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Imagens <span class="text-secondary">(opcional, vai junto no registro clínico)</span></label>
                        <input type="file" name="imagens[]" class="form-control" accept="image/png,image/jpeg,image/webp" capture="environment" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check2 me-1"></i> Concluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var ANIMAIS = <?= json_encode(array_map(fn($a) => [
    'id' => $a['IDAnimal'], 'nome' => $a['Nome'], 'dono' => $a['NomeDono'],
    'especie' => $a['FKEspecie'], 'icone' => $a['IconeEspecie'],
], $animais), JSON_UNESCAPED_UNICODE) ?>;
var VETS = <?= json_encode(array_map(fn($v) => [
    'id' => $v['IDUsuario'], 'nome' => $v['Nome'],
], $vets), JSON_UNESCAPED_UNICODE) ?>;
var PROCEDIMENTOS = <?= json_encode(array_map(fn($p) => [
    'id' => $p['IDTipo'], 'categoria' => $p['Categoria'], 'nome' => $p['Nome'], 'duracao' => (int) $p['DuracaoPadraoMinutos'],
], $procedimentos), JSON_UNESCAPED_UNICODE) ?>;

// Tipo -> filtra os procedimentos disponíveis; escolher um procedimento
// preenche duração e título automaticamente (mas continuam editáveis).
var TIPOS_AGENDA = <?= json_encode(array_map(fn($valor, $label) => [
    'id' => $valor, 'nome' => $label,
], array_keys($tiposAgenda), $tiposAgenda), JSON_UNESCAPED_UNICODE) ?>;

var selDuracaoAgendamento = document.getElementById('selDuracaoAgendamento');
var inpTituloAgendamento  = document.getElementById('inpTituloAgendamento');

function selecionarProcedimento(item) {
    if (!item) return;
    // Duração do procedimento pode não bater com nenhuma das opções fixas
    // (ex: 20min) — cria a opção na hora se precisar, em vez de falhar
    // silenciosamente ao tentar selecionar um valor que não existe.
    var existe = Array.prototype.some.call(selDuracaoAgendamento.options, function (o) {
        return Number(o.value) === item.duracao;
    });
    if (!existe) {
        var op = document.createElement('option');
        op.value = item.duracao;
        op.textContent = item.duracao + ' min';
        selDuracaoAgendamento.appendChild(op);
    }
    selDuracaoAgendamento.value = item.duracao;
    inpTituloAgendamento.value = item.nome;
}

var agProcPk = initPicker({
    pickerId: 'agProcPicker', triggerId: 'agProcTrigger', dropdownId: 'agProcDropdown',
    searchId: 'agProcSearch', listId: 'agProcList', hiddenId: 'inpagProcId', labelId: 'agProcLabel',
    items: PROCEDIMENTOS.filter(function (p) { return p.categoria === 'consulta'; }),
    chave: function (p) { return p.id; },
    renderItem: function (p) { return { title: p.nome + ' (' + p.duracao + ' min)' }; },
    matches: function (p, q) { return p.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhum procedimento cadastrado nesse tipo.',
    onSelect: selecionarProcedimento,
});

var agTipoPk = initPicker({
    pickerId: 'agTipoPicker', triggerId: 'agTipoTrigger', dropdownId: 'agTipoDropdown',
    searchId: 'agTipoSearch', listId: 'agTipoList', hiddenId: 'inpagTipoId', labelId: 'agTipoLabel',
    items: TIPOS_AGENDA,
    chave: function (t) { return t.id; },
    renderItem: function (t) { return { title: t.nome }; },
    matches: function (t, q) { return t.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nada encontrado.',
    onSelect: function (t) {
        var itens = PROCEDIMENTOS.filter(function (p) { return p.categoria === t.id; });
        agProcPk.setItems(itens, 'Personalizado');
        // Abre o próximo picker sozinho, pra fluir direto sem precisar clicar
        // de novo. Precisa do setTimeout: o clique que selecionou o Tipo ainda
        // vai disparar um "click" nativo (mousedown já rodou, click vem na
        // sequência) — abrir na hora faria o listener de "clique fora" do
        // Procedimento fechar ele de novo imediatamente.
        if (itens.length) {
            setTimeout(function () { agProcPk.abrir(); }, 50);
        }
    },
});

initPicker({
    pickerId: 'animalPicker', triggerId: 'animalTrigger', dropdownId: 'animalDropdown',
    searchId: 'animalSearch', listId: 'animalList', hiddenId: 'inpAnimalId', labelId: 'animalLabel',
    items: ANIMAIS,
    chave: function (a) { return a.id; },
    renderItem: function (a) { return { title: a.nome, icon: a.icone, sub: a.dono }; },
    matches: function (a, q) {
        return a.nome.toLowerCase().indexOf(q) !== -1 || a.dono.toLowerCase().indexOf(q) !== -1;
    },
    vazioMsg: 'Nenhum animal encontrado.',
});

initPicker({
    pickerId: 'vetRespPicker', triggerId: 'vetRespTrigger', dropdownId: 'vetRespDropdown',
    searchId: 'vetRespSearch', listId: 'vetRespList', hiddenId: 'inpvetRespId', labelId: 'vetRespLabel',
    items: VETS,
    chave: function (v) { return v.id; },
    renderItem: function (v) { return { title: v.nome }; },
    matches: function (v, q) { return v.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhum veterinário encontrado.',
});

// Delegação no document (em vez de listener por botão) — assim funciona tanto
// pros botões já na página quanto pros que o painel de dia da vista mensal
// injeta dinamicamente via mostrarDiaMes().
document.addEventListener('click', function (e) {
    var btnConcluir = e.target.closest('.btn-concluir');
    if (btnConcluir) {
        document.getElementById('concluirId').value = btnConcluir.dataset.id;
        document.getElementById('concluirTitulo').textContent = btnConcluir.dataset.titulo;
        new bootstrap.Modal(document.getElementById('modalConcluir')).show();
        return;
    }

    var btnAcao = e.target.closest('.btn-acao-agendamento');
    if (btnAcao) {
        e.preventDefault();
        e.stopPropagation();
        function executar() {
            fetch(BASE + '/painel/api_agendamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: btnAcao.dataset.acao, id: btnAcao.dataset.id, csrf_token: '<?= gerarTokenCSRF() ?>' }),
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    location.reload();
                } else {
                    vsToast(d.msg || 'Erro ao atualizar.', 'danger');
                }
            })
            .catch(function () { vsToast('Falha na conexão.', 'danger'); });
        }
        if (btnAcao.dataset.confirm) {
            vsConfirm(btnAcao.dataset.confirm, executar);
        } else {
            executar();
        }
    }
});

// ── Painel de detalhe do dia (vista mensal) ─────────────────────
var MES_DADOS = <?= json_encode($mesJson ?? [], JSON_UNESCAPED_UNICODE) ?>;

var STATUS_LABEL = {
    pendente: 'Pendente', confirmado: 'Confirmado', concluido: 'Concluído',
    cancelado: 'Cancelado', faltou: 'Faltou',
};
var STATUS_COR = {
    pendente: 'secondary', confirmado: 'info', concluido: 'success',
    cancelado: 'danger', faltou: 'warning',
};

function mostrarDiaMes(data, diaNum) {
    var itens = MES_DADOS[data] || [];
    document.getElementById('painelDiaMesTitulo').textContent =
        'Dia ' + diaNum + ' — ' + itens.length + ' agendamento' + (itens.length === 1 ? '' : 's');
    document.getElementById('painelDiaMesNovo').addEventListener('click', function () {
        var campoData = document.querySelector('#modalNovoAgendamento input[name="data"]');
        if (campoData) campoData.value = data;
    }, { once: true });

    var html;
    if (!itens.length) {
        html = '<div class="text-center py-4 text-secondary small">Nenhum agendamento nesse dia.</div>';
    } else {
        html = '<ul class="list-group list-group-flush">' + itens.map(function (ag) {
            var acoes = '';
            if (ag.status === 'pendente') {
                acoes = '<button class="btn btn-sm btn-outline-info btn-acao-agendamento" data-acao="confirmar" data-id="' + ag.id + '">Confirmar</button>'
                      + '<button class="btn btn-sm btn-outline-danger btn-acao-agendamento" data-acao="cancelar" data-id="' + ag.id + '" data-confirm="Cancelar esse agendamento?">Cancelar</button>';
            } else if (ag.status === 'confirmado') {
                acoes = '<button class="btn btn-sm btn-accent btn-concluir" data-id="' + ag.id + '" data-titulo="' + escHtmlPicker(ag.titulo) + '">Concluir</button>'
                      + '<button class="btn btn-sm btn-outline-warning btn-acao-agendamento" data-acao="marcar_falta" data-id="' + ag.id + '" data-confirm="Marcar falta nesse agendamento?">Faltou</button>'
                      + '<button class="btn btn-sm btn-outline-danger btn-acao-agendamento" data-acao="cancelar" data-id="' + ag.id + '" data-confirm="Cancelar esse agendamento?">Cancelar</button>';
            } else {
                acoes = '<button class="btn btn-sm btn-outline-secondary btn-acao-agendamento" data-acao="reabrir" data-id="' + ag.id + '" data-confirm="Reabrir esse agendamento?">Reabrir</button>';
            }
            return '<li class="list-group-item px-3 py-2">'
                 + '<div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap">'
                 + '<span class="fw-bold text-accent" style="min-width:42px;">' + ag.hora + '</span>'
                 + '<div class="flex-grow-1 min-w-0">'
                 + '<div class="d-flex align-items-center gap-1 flex-wrap">'
                 + '<span class="badge" style="background:var(--accent-light);color:var(--accent);">' + escHtmlPicker(ag.tipo) + '</span>'
                 + '<span class="badge bg-' + STATUS_COR[ag.status] + '">' + STATUS_LABEL[ag.status] + '</span>'
                 + '<span class="fw-medium">' + (ag.icone || '') + ' ' + escHtmlPicker(ag.animal) + '</span>'
                 + '<span class="text-secondary small">— ' + escHtmlPicker(ag.dono) + '</span>'
                 + '</div>'
                 + '<span class="text-secondary small d-block">' + escHtmlPicker(ag.titulo) + (ag.vet ? ' · ' + escHtmlPicker(ag.vet) : ' · sem veterinário definido') + '</span>'
                 + '</div>'
                 + '<div class="d-flex gap-1 flex-wrap flex-shrink-0">' + acoes + '</div>'
                 + '</div></li>';
        }).join('') + '</ul>';
    }
    document.getElementById('painelDiaMesConteudo').innerHTML = html;

    var painel = document.getElementById('painelDiaMes');
    painel.style.display = '';
    painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php if (($_GET['acao'] ?? '') === 'novo' || $animalPre): ?>
<script>new bootstrap.Modal(document.getElementById('modalNovoAgendamento')).show();</script>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
