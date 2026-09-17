<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('cliente');
header('Content-Type: application/json; charset=utf-8');

$data     = trim($_GET['data'] ?? '');
$fkVet    = trim($_GET['veterinario'] ?? '');
$duracao  = max(15, (int) ($_GET['duracao'] ?? 30));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d')) {
    http_response_code(400);
    echo json_encode(['erro' => 'Data inválida.']);
    exit;
}

try {
    $horarios = listarHorariosDisponiveis($pdo, $data, $fkVet ?: null, $duracao);
    echo json_encode(['horarios' => $horarios]);
} catch (PDOException $e) {
    error_log('[HorariosDisponiveis] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar horários.']);
}
