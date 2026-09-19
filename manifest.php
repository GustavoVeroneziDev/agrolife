<?php
header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/conexao.php';
$b = defined('BASE') ? BASE : '';

echo json_encode([
    'id'               => $b . '/painel',
    'name'             => APP_NOME,
    'short_name'       => APP_NOME,
    'description'      => 'Gestão de clínica veterinária — animais, vacinas e histórico clínico',
    'start_url'        => $b . '/painel',
    'scope'            => $b . '/',
    'display'          => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone'],
    'orientation'      => 'portrait-primary',
    // Cor de fundo da splash screen nativa (some antes do CSS carregar) —
    // usa o mesmo verde vivo do ícone, não o branco/pálido do app por
    // dentro, pra virar um momento de marca de verdade na abertura em vez
    // de um flash de cor entre a splash e a primeira tela.
    'background_color' => '#03a851',
    'theme_color'      => '#0d7a5c',
    'lang'             => 'pt-BR',
    'dir'              => 'ltr',
    'categories'       => ['medical', 'business', 'productivity'],
    'shortcuts'        => [
        [
            'name'        => 'Agenda',
            'short_name'  => 'Agenda',
            'description' => 'Ver a agenda do dia',
            'url'         => $b . '/agenda',
            'icons'       => [['src' => $b . '/assets/img/icon-192.png', 'sizes' => '192x192']],
        ],
        [
            'name'        => 'Animais',
            'short_name'  => 'Animais',
            'description' => 'Buscar um animal cadastrado',
            'url'         => $b . '/animais',
            'icons'       => [['src' => $b . '/assets/img/icon-192.png', 'sizes' => '192x192']],
        ],
    ],
    'icons'            => [
        // 'any maskable' combinados numa entrada é inválido — entradas separadas.
        // Cada arquivo tem as dimensões reais que o "sizes" declara (logo.png
        // original é 499x500 — usá-lo direto aqui fazia o tamanho declarado
        // não bater com o arquivo de verdade, o que barra a instalação em
        // vários navegadores).
        [
            'src'     => $b . '/assets/img/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $b . '/assets/img/icon-384.png',
            'sizes'   => '384x384',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $b . '/assets/img/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $b . '/assets/img/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
        [
            'src'     => $b . '/assets/img/icon-512-maskable.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
