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
    $proximaData = trim($dados['proxima_data'] ?? '');
    $ciclica     = !empty($dados['ciclica']);

    if ($proximaData === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximaData)) {
        echo json_encode(['ok' => false, 'msg' => 'Data inválida.']);
        exit;
    }

    try {
        // Cíclica exige que a vacina tenha intervalo de reforço cadastrado —
        // sem isso não tem por quanto tempo avançar a data sozinha depois.
        $tipoStmt = $pdo->prepare(
            'SELECT tv.IntervaloMeses FROM RegistrosVacinas rv
             JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
             WHERE rv.IDRegistro = :id LIMIT 1'
        );
        $tipoStmt->execute([':id' => $id]);
        $tipo = $tipoStmt->fetch();
        if (!$tipo) {
            echo json_encode(['ok' => false, 'msg' => 'Registro não encontrado.']);
            exit;
        }
        $ciclica = $ciclica && $tipo['IntervaloMeses'];

        $pdo->prepare(
            "UPDATE RegistrosVacinas
             SET ProximaData = :proxima, Ciclica = :ciclica, NotificacaoSemanaEnviada = 0, NotificacaoDiaEnviada = 0
             WHERE IDRegistro = :id"
        )->execute([
            ':proxima' => $proximaData,
            ':ciclica' => $ciclica ? 1 : 0,
            ':id'      => $id,
        ]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        error_log('[ApiVacina] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
