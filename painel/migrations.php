<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

// Gap conhecido desde o início do projeto (PADROES_DESENVOLVIMENTO.md, seção
// 5): não existia tabela de controle de migration aplicada, então subir uma
// mudança de schema em produção dependia de lembrar manualmente quais já
// rodaram. Essa tabela é criada aqui mesmo (não só via migration 031) de
// propósito — senão essa própria ferramenta dependia de alguém já ter
// rodado a migration que ela deveria estar aplicando.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS SchemaMigrations (
        Nome       VARCHAR(150) NOT NULL,
        AplicadaEm TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Modo       ENUM('executada', 'marcada_manualmente') NOT NULL DEFAULT 'executada',
        FKUsuario  VARCHAR(36)  NULL,
        PRIMARY KEY (Nome),
        CONSTRAINT fk_schemamigrations_usuario FOREIGN KEY (FKUsuario) REFERENCES Usuarios(IDUsuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
// Se a tabela acabou de ser criada agora, a migration 031 (que É essa
// tabela) já está, por definição, aplicada — evita ela aparecer como
// "pendente" logo na primeira vez que a ferramenta roda.
$pdo->prepare(
    "INSERT IGNORE INTO SchemaMigrations (Nome, Modo) VALUES ('031_schema_migrations_tracking.sql', 'executada')"
)->execute();

// Divide um arquivo .sql em statements individuais — PDO::exec() só roda um
// statement por vez de forma confiável entre ambientes (multi-statement
// depende de config do driver, não é garantido). Remove linha de
// comentário inteira antes de dividir por ";", senão um ";" dentro de um
// comentário quebrava a divisão.
function statementsDoArquivoSql(string $conteudo): array
{
    $linhas = explode("\n", $conteudo);
    $semComentarios = array_filter($linhas, fn($l) => !preg_match('/^\s*--/', $l));
    $limpo = implode("\n", $semComentarios);

    $statements = array_map('trim', explode(';', $limpo));
    return array_values(array_filter($statements, fn($s) => $s !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/migrations.php', 'Token inválido.', 'danger');
    }
    exigirAdmin(BASE . '/painel/migrations.php');

    $acao    = $_POST['acao'] ?? '';
    $arquivo = basename(trim($_POST['arquivo'] ?? '')); // basename() evita path traversal
    $caminho = __DIR__ . '/../migrations/' . $arquivo;

    // Só valida arquivo pras ações que operam em UM arquivo específico —
    // "marcar_todas_pendentes" não manda "arquivo" (opera em todos de uma
    // vez), então cair nessa checagem pra ela sempre dava "inválido".
    if (in_array($acao, ['executar', 'marcar'], true)) {
        // Só aceita nome de arquivo no formato real de migration deste
        // projeto e que exista de fato na pasta — nunca executa algo fora
        // disso.
        if (!preg_match('/^\d{3}[a-z]?_[a-z0-9_]+\.sql$/', $arquivo) || !is_file($caminho)) {
            redirecionarComMensagem(BASE . '/painel/migrations.php', 'Arquivo de migration inválido.', 'danger');
        }
    }

    if ($acao === 'executar') {
        $stmt = $pdo->prepare('SELECT 1 FROM SchemaMigrations WHERE Nome = :n');
        $stmt->execute([':n' => $arquivo]);
        if ($stmt->fetchColumn()) {
            redirecionarComMensagem(BASE . '/painel/migrations.php', "{$arquivo} já está marcada como aplicada — nada foi executado.", 'warning');
        }

        try {
            foreach (statementsDoArquivoSql(file_get_contents($caminho)) as $sql) {
                $pdo->exec($sql);
            }
            $pdo->prepare("INSERT INTO SchemaMigrations (Nome, Modo, FKUsuario) VALUES (:n, 'executada', :u)")
                ->execute([':n' => $arquivo, ':u' => $_SESSION['usuario_id']]);
            redirecionarComMensagem(BASE . '/painel/migrations.php', "{$arquivo} executada com sucesso.", 'success');
        } catch (PDOException $e) {
            error_log('[Migrations] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/migrations.php', "Erro ao executar {$arquivo}: " . $e->getMessage(), 'danger');
        }
    } elseif ($acao === 'marcar') {
        $pdo->prepare("INSERT IGNORE INTO SchemaMigrations (Nome, Modo, FKUsuario) VALUES (:n, 'marcada_manualmente', :u)")
            ->execute([':n' => $arquivo, ':u' => $_SESSION['usuario_id']]);
        redirecionarComMensagem(BASE . '/painel/migrations.php', "{$arquivo} marcada como já aplicada (não foi executada agora).", 'success');
    } elseif ($acao === 'marcar_todas_pendentes') {
        $existentes = array_map('basename', glob(__DIR__ . '/../migrations/*.sql'));
        $jaAplicadas = array_column($pdo->query('SELECT Nome FROM SchemaMigrations')->fetchAll(), 'Nome');
        $pendentes = array_diff($existentes, $jaAplicadas);
        $ins = $pdo->prepare("INSERT IGNORE INTO SchemaMigrations (Nome, Modo, FKUsuario) VALUES (:n, 'marcada_manualmente', :u)");
        foreach ($pendentes as $nome) {
            $ins->execute([':n' => $nome, ':u' => $_SESSION['usuario_id']]);
        }
        redirecionarComMensagem(BASE . '/painel/migrations.php', count($pendentes) . ' migration(s) marcada(s) como já aplicada(s).', 'success');
    } else {
        redirecionarComMensagem(BASE . '/painel/migrations.php', 'Ação inválida.', 'warning');
    }
}

$arquivos = array_map('basename', glob(__DIR__ . '/../migrations/*.sql'));
natsort($arquivos);
$arquivos = array_values($arquivos);

$aplicadas = [];
foreach ($pdo->query(
    'SELECT sm.Nome, sm.AplicadaEm, sm.Modo, u.Nome AS NomeUsuario
     FROM SchemaMigrations sm
     LEFT JOIN Usuarios u ON u.IDUsuario = sm.FKUsuario'
)->fetchAll() as $row) {
    $aplicadas[$row['Nome']] = $row;
}
$totalPendentes = count(array_diff($arquivos, array_keys($aplicadas)));

$paginaTitulo = 'Migrations';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
    <h4 class="fw-bold mb-0"><i class="bi bi-database-gear me-2 text-accent"></i>Migrations</h4>
    <?php if ($totalPendentes > 0): ?>
        <form method="POST" data-confirm="Marca TODAS as pendentes como já aplicadas SEM executar nada — só use se tiver certeza que elas já rodaram manualmente antes (ex.: pela primeira vez que essa ferramenta é usada num ambiente que já tinha o banco atualizado). Continuar?">
            <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
            <input type="hidden" name="acao" value="marcar_todas_pendentes">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Marcar todas pendentes como já aplicadas</button>
        </form>
    <?php endif ?>
</div>
<p class="text-secondary small mb-4">
    Cada mudança de estrutura do banco vira um arquivo aqui — essa tela mostra quais já rodaram nesse ambiente e deixa aplicar as que faltam, sem depender de phpMyAdmin nem de lembrar de cabeça.
</p>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background:var(--bg-hover);">
                    <tr>
                        <th class="px-4 py-3">Arquivo</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arquivos as $arq): $info = $aplicadas[$arq] ?? null; ?>
                        <tr>
                            <td class="px-4"><code class="small"><?= h($arq) ?></code></td>
                            <td>
                                <?php if ($info): ?>
                                    <span class="badge bg-success">Aplicada</span>
                                    <span class="small text-secondary ms-1">
                                        <?= formatarDataHora($info['AplicadaEm']) ?>
                                        <?= $info['Modo'] === 'marcada_manualmente' ? ' · marcada manualmente' : '' ?>
                                        <?= $info['NomeUsuario'] ? ' · ' . h($info['NomeUsuario']) : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--cor-atencao-bg);color:var(--cor-atencao);">Pendente</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <?php if (!$info): ?>
                                    <form method="POST" class="d-inline" data-confirm="Executar <?= h($arq) ?> agora, direto no banco?">
                                        <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                                        <input type="hidden" name="acao" value="executar">
                                        <input type="hidden" name="arquivo" value="<?= h($arq) ?>">
                                        <button type="submit" class="btn btn-sm btn-accent">Executar</button>
                                    </form>
                                    <form method="POST" class="d-inline" data-confirm="Marca <?= h($arq) ?> como já aplicada SEM executar nada — só use se tiver certeza que já rodou manualmente antes. Continuar?">
                                        <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                                        <input type="hidden" name="acao" value="marcar">
                                        <input type="hidden" name="arquivo" value="<?= h($arq) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Marcar como já aplicada</button>
                                    </form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
