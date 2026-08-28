<?php
header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/conexao.php';
$b = defined('BASE') ? BASE : '';

echo json_encode([
    'name'             => APP_NOME,
    'short_name'       => APP_NOME,
    'description'      => 'Gestão de clínica veterinária — animais, vacinas e histórico clínico',
    'start_url'        => $b . '/painel/index.php',
    'scope'            => $b . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#f7fafa',
    'theme_color'      => '#0d9488',
    'lang'             => 'pt-BR',
    'icons'            => [
        // 'any maskable' combinados numa entrada é inválido — entradas separadas
        [
            'src'     => $b . '/assets/img/logo.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $b . '/assets/img/logo.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $b . '/assets/img/logo.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
