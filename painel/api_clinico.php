<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['nivel_acesso'] ?? '', ['admin', 'veterinario'], true)) {
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
        $anexos = $pdo->prepare('SELECT CaminhoArquivo FROM AnexosClinicos WHERE FKRegistro = :id');
        $anexos->execute([':id' => $id]);
        $caminhos = $anexos->fetchAll(PDO::FETCH_COLUMN);

        // ON DELETE CASCADE cuida das linhas de AnexosClinicos; os arquivos
        // físicos precisam ser apagados à parte, senão ficam órfãos no disco.
        $pdo->prepare('DELETE FROM RegistrosClinicos WHERE IDRegistro = :id')->execute([':id' => $id]);

        foreach ($caminhos as $caminho) {
            $caminhoFisico = __DIR__ . '/..' . $caminho;
            if (is_file($caminhoFisico)) {
                @unlink($caminhoFisico);
            }
        }

        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        error_log('[ApiClinico] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Erro ao excluir registro.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
