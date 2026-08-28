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

$transicoes = [
    'confirmar'    => ['de' => ['pendente'],                         'para' => 'confirmado'],
    'cancelar'     => ['de' => ['pendente', 'confirmado'],           'para' => 'cancelado'],
    'marcar_falta' => ['de' => ['confirmado'],                       'para' => 'faltou'],
    'reabrir'      => ['de' => ['concluido', 'cancelado', 'faltou'], 'para' => 'confirmado'],
];

if (!$id || !isset($transicoes[$acao])) {
    echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT Status FROM Agendamentos WHERE IDAgendamento = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $statusAtual = $stmt->fetchColumn();

    if ($statusAtual === false) {
        echo json_encode(['ok' => false, 'msg' => 'Agendamento não encontrado.']);
        exit;
    }
    if (!in_array($statusAtual, $transicoes[$acao]['de'], true)) {
        echo json_encode(['ok' => false, 'msg' => 'Esse agendamento não está num estado que permite essa ação.']);
        exit;
    }

    $pdo->prepare('UPDATE Agendamentos SET Status = :status WHERE IDAgendamento = :id')
        ->execute([':status' => $transicoes[$acao]['para'], ':id' => $id]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    error_log('[ApiAgendamento] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Erro ao atualizar agendamento.']);
}
