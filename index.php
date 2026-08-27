<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';

if (!estaLogado() && !empty($_COOKIE['vs_lembrar'])) {
    tentarLoginLembrado($pdo);
}

if (estaLogado()) {
    if (($_SESSION['nivel_acesso'] ?? '') === 'admin') {
        header('Location: ' . BASE . '/painel/index.php');
    } else {
        header('Location: ' . BASE . '/usuario/meus_animais.php');
    }
    exit;
}

header('Location: ' . BASE . '/usuario/login.php');
exit;
