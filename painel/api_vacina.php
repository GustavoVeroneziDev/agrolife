<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acesso não permitido.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true) ?? [];

if (!validarTokenCSRF($dados['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'msg' => 'Token inválido.']);
    exit;
}

$acao = $dados['acao'] ?? '';
$id   = trim($dados['id'] ?? '');

if ($acao === 'excluir' && $id) {
    try {
        $pdo->prepare('DELETE FROM RegistrosVacinas WHERE IDRegistro = :id')->execute([':id' => $id]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        error_log('[ApiVacina] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Erro ao excluir registro.']);
    }
    exit;
}

// Define/agenda manualmente a próxima aplicação de uma vacina já registrada
// — tanto pra ajustar uma data única quanto pra ligar o modo cíclico (repete
// sozinha a cada N meses sem precisar reaplicar de verdade a cada ciclo).
if ($acao === 'editar_proxima' && $id) {
    $proximaData      = trim($dados['proxima_data'] ?? '');
    $ciclica          = !empty($dados['ciclica']);
    $intervaloValor   = (int) ($dados['intervalo_valor'] ?? 0);
    $intervaloUnidade = trim($dados['intervalo_unidade'] ?? '');
    $unidadesValidas  = ['semana', 'mes', 'ano'];

    if ($proximaData === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximaData)) {
        echo json_encode(['ok' => false, 'msg' => 'Data inválida.']);
        exit;
    }

    // Cíclica precisa de um intervalo válido pra ter por quanto tempo
    // avançar a data sozinha depois — a pessoa escolhe livremente, não
    // depende mais do intervalo do catálogo da vacina.
    $ciclica = $ciclica && $intervaloValor > 0 && in_array($intervaloUnidade, $unidadesValidas, true);

    try {
        $existe = $pdo->prepare('SELECT 1 FROM RegistrosVacinas WHERE IDRegistro = :id LIMIT 1');
        $existe->execute([':id' => $id]);
        if (!$existe->fetchColumn()) {
            echo json_encode(['ok' => false, 'msg' => 'Registro não encontrado.']);
            exit;
        }

        $pdo->prepare(
            "UPDATE RegistrosVacinas
             SET ProximaData = :proxima, Ciclica = :ciclica,
                 IntervaloCiclicoValor = :intvalor, IntervaloCiclicoUnidade = :intunidade,
                 NotificacaoSemanaEnviada = 0, NotificacaoDiaEnviada = 0
             WHERE IDRegistro = :id"
        )->execute([
            ':proxima'    => $proximaData,
            ':ciclica'    => $ciclica ? 1 : 0,
            ':intvalor'   => $ciclica ? $intervaloValor : null,
            ':intunidade' => $ciclica ? $intervaloUnidade : null,
            ':id'         => $id,
        ]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        error_log('[ApiVacina] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
