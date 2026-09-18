<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';

if (!estaLogado() && !empty($_COOKIE['vs_lembrar'])) {
    tentarLoginLembrado($pdo);
}

if (estaLogado()) {
    if (in_array($_SESSION['nivel_acesso'] ?? '', ['admin', 'funcionario'], true)) {
        header('Location: ' . BASE . '/painel/index.php');
    } else {
        header('Location: ' . BASE . '/usuario/meus_animais.php');
    }
    exit;
}

// Veterinários da home — perfil individual (foto grande, nome, CRMV, bio).
// 'foto' fica null até a foto de verdade chegar ("vou pedir depois") — usa
// o ícone grande como espaço reservado nesse meio tempo, já pronto pra
// virar <img> assim que o caminho do arquivo existir. 'crmv' também fica
// null até ser informado — some da tela sozinho enquanto isso (nunca
// mostra "CRMV: " vazio).
$veterinariosHome = [
    [
        'nome'   => 'Dr. José Afonso Parro',
        'cargo'  => 'CEO & Médico Veterinário',
        'crmv'   => null,
        'foto'   => 'b5e397f2-601b-4504-ab7a-3dc0ef0a51e4.jpg',
        'bio'    => 'Consultas, exames e acompanhamento clínico do seu animal.',
        'icone'  => 'bi-person-badge',
    ],
    [
        'nome'   => 'Dr. Deyvid Alota',
        // TODO: "Sócio" é um termo genérico até o Gustavo confirmar o
        // título exato que ele usa (Diretor, Sócio-fundador, etc.).
        'cargo'  => 'Sócio & Médico Veterinário',
        'crmv'   => null,
        'foto'   => '29034acf-139d-4d8d-a3ad-b9b83e839ae8.png',
        'bio'    => 'Consultas, cirurgias e procedimentos com acompanhamento completo.',
        'icone'  => 'bi-person-badge',
    ],
];

// Equipe de apoio — coletivo, sem CRMV nem foto individual, por isso fica
// fora do bloco de perfil (formato pensado pra profissional específico com
// credencial, não combina com "um time" genérico).
$equipeApoioHome = [
    'titulo' => 'Equipe de Atendimento',
    'cargo'  => 'Suporte e Cuidado',
    'bio'    => 'Time atuante que acompanha de perto cada visita, cuidando do conforto e bem-estar do seu animal.',
    'icone'  => 'bi-people-fill',
];

$servicosHome = [
    ['icone' => 'bi-heart-pulse',     'titulo' => 'Consultas',              'texto' => 'Consultas de rotina, avaliações e retornos, com histórico sempre à mão.'],
    ['icone' => 'bi-bandaid',         'titulo' => 'Cirurgias',              'texto' => 'Procedimentos cirúrgicos com acompanhamento pré e pós-operatório.'],
    ['icone' => 'bi-clipboard2-pulse', 'titulo' => 'Exames',                'texto' => 'Exames laboratoriais e de imagem, com resultado registrado no prontuário.'],
    ['icone' => 'bi-shield-plus',     'titulo' => 'Vacinas e Medicamentos', 'texto' => 'Protocolo por espécie, com lembrete automático de reforço.'],
    ['icone' => 'bi-capsule',         'titulo' => 'Procedimentos',          'texto' => 'Curativos, limpeza dentária, aplicações e outros cuidados periódicos.'],
    ['icone' => 'bi-journal-medical', 'titulo' => 'Acompanhamento',         'texto' => 'Registro clínico completo — nada se perde entre uma consulta e outra.'],
    ['icone' => 'bi-basket2-fill',    'titulo' => 'Produtos Agropecuários', 'texto' => 'Ração, medicamentos e itens pro dia a dia do seu animal ou da sua produção.'],
];

$especiesHome = [];
try {
    $especiesHome = $pdo->query(
        "SELECT Nome, Icone FROM Especies WHERE Nome != 'Outro' ORDER BY Ordem ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[HomeEspecies] ' . $e->getMessage());
}

// Contato da home — vem de Configurações (mesma fonte que o rodapé já usa,
// ver geral/footer.php), não mais escrito à mão aqui. Antes eram duas
// cópias do mesmo dado (rodapé dinâmico, home hardcoded) que podiam ficar
// desatualizadas uma em relação à outra.
$telClinicaHome   = getConfig($pdo, 'telefone_clinica', '');
$emailClinicaHome = getConfig($pdo, 'email_clinica', '');
$instaClinicaHome = getConfig($pdo, 'instagram_clinica', '');
$enderecoHome     = enderecoClinicaFormatado($pdo);
$horarioResumoHome = trim(
    (getConfig($pdo, 'horario_segunda', '') !== '' ? 'Segunda a sexta: ' . getConfig($pdo, 'horario_segunda', '') : '')
    . (getConfig($pdo, 'horario_sabado', '') !== '' ? ' · Sábado: ' . getConfig($pdo, 'horario_sabado', '') : '')
);

$paginaTitulo       = 'Cuidado veterinário completo para o seu animal';
$areaAtual          = 'publico';
$paginaSemContainer = true;
$paginaCssExtra     = ['home.css'];
$metaRobots         = 'index, follow';
$metaDescricao      = 'Agro Life — clínica veterinária com atendimento humanizado, equipe qualificada e acompanhamento completo da saúde do seu animal, do pet à produção.';
require_once __DIR__ . '/geral/header.php';
?>

<section class="home-hero">
    <div class="container-lg">
        <div class="home-hero-grid">
            <div>
                <span class="home-eyebrow">Agro Life · Clínica Veterinária</span>
                <h1>Cuidado veterinário próximo, atento e profissional</h1>
                <p class="lead">
                    Da consulta de rotina ao pós-operatório, acompanhamos cada etapa da saúde
                    do seu animal com atenção e transparência — pra você e pra quem você cuida.
                </p>
                <div class="home-hero-cta">
                    <a href="<?= BASE ?>/usuario/cadastro.php" class="btn btn-accent btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Criar minha conta
                    </a>
                    <a href="<?= BASE ?>/usuario/login.php" class="btn btn-outline-accent btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Já sou cliente
                    </a>
                </div>
                <div class="home-trust-row">
                    <span class="home-trust-item"><i class="bi bi-heart-pulse"></i>Atendimento humanizado</span>
                    <span class="home-trust-item"><i class="bi bi-journal-medical"></i>Prontuário digital completo</span>
                    <span class="home-trust-item"><i class="bi bi-patch-check"></i>Equipe qualificada</span>
                </div>
            </div>
            <div class="home-hero-mark">
                <img src="<?= BASE ?>/assets/img/logo.png" alt="<?= h(APP_NOME) ?>">
            </div>
        </div>
    </div>
</section>

<?php if (!empty($especiesHome)): ?>
<section class="home-section home-especies">
    <div class="container-lg">
        <div class="home-section-head">
            <span class="home-eyebrow">Quem cuidamos</span>
            <h2>Cuidamos de quem você ama</h2>
            <p>Do seu cão ou gato aos animais de produção — atendemos diferentes espécies com o mesmo padrão de cuidado.</p>
        </div>
        <div class="home-especies-row">
            <?php foreach ($especiesHome as $esp): ?>
                <div class="home-especie-item">
                    <span class="home-especie-badge"><?= especieIconeHtml($esp['Icone'], '2.1rem') ?></span>
                    <span><?= h($esp['Nome']) ?></span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<section class="home-section home-servicos">
    <div class="container-lg">
        <div class="home-section-head">
            <span class="home-eyebrow">O que fazemos</span>
            <h2>Serviços</h2>
            <p>Toda a jornada de saúde do seu animal, organizada num só lugar — do agendamento ao histórico completo.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($servicosHome as $s): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card home-servico-card">
                        <div class="home-servico-icone"><i class="bi <?= h($s['icone']) ?>"></i></div>
                        <h3><?= h($s['titulo']) ?></h3>
                        <p><?= h($s['texto']) ?></p>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section class="home-section home-equipe">
    <div class="container-lg">
        <div class="home-section-head">
            <span class="home-eyebrow">Equipe</span>
            <h2>Profissionais que cuidam com atenção</h2>
            <p>Uma equipe qualificada e dedicada, pronta pra acompanhar a saúde do seu animal de perto.</p>
        </div>
        <div class="home-vets">
            <?php foreach ($veterinariosHome as $vet): ?>
                <div class="home-vet-linha">
                    <div class="home-vet-foto">
                        <?php if ($vet['foto']): ?>
                            <img src="<?= BASE ?>/uploads/equipe/<?= h($vet['foto']) ?>" alt="<?= h($vet['nome']) ?>">
                        <?php else: ?>
                            <i class="bi <?= h($vet['icone']) ?>"></i>
                        <?php endif ?>
                    </div>
                    <div class="home-vet-info">
                        <h3><?= h($vet['nome']) ?></h3>
                        <div class="home-vet-tags">
                            <span class="home-equipe-cargo"><?= h($vet['cargo']) ?></span>
                            <?php if ($vet['crmv']): ?><span class="home-vet-crmv">CRMV <?= h($vet['crmv']) ?></span><?php endif ?>
                        </div>
                        <p><?= h($vet['bio']) ?></p>
                        <?php if ($telClinicaHome !== ''): ?>
                            <a href="<?= h(waLink($telClinicaHome, "Olá! Gostaria de agendar com {$vet['nome']}.")) ?>" target="_blank" rel="noopener" class="home-vet-contato">
                                <i class="bi bi-whatsapp me-1"></i>Falar com a clínica
                            </a>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="home-equipe-apoio">
            <div class="home-equipe-avatar"><i class="bi <?= h($equipeApoioHome['icone']) ?>"></i></div>
            <div>
                <h3><?= h($equipeApoioHome['titulo']) ?></h3>
                <span class="home-equipe-cargo"><?= h($equipeApoioHome['cargo']) ?></span>
                <p><?= h($equipeApoioHome['bio']) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="home-contato">
    <div class="container-lg py-2">
        <div class="home-contato-grid">
            <div>
                <span class="home-eyebrow" style="color:var(--accent-text);opacity:.85;">Fale com a gente</span>
                <h2>Vamos cuidar do seu animal juntos</h2>
                <ul class="home-contato-lista mt-3">
                    <?php if ($enderecoHome !== ''): ?>
                        <li><i class="bi bi-geo-alt"></i><?= h($enderecoHome) ?></li>
                    <?php endif ?>
                    <?php if ($telClinicaHome !== ''): ?>
                        <li>
                            <i class="bi bi-whatsapp"></i>
                            <a href="<?= h(waLink($telClinicaHome)) ?>" target="_blank" rel="noopener" class="text-decoration-none" style="color:inherit;">
                                <?= h(formatarTelefoneExibicao($telClinicaHome)) ?>
                            </a>
                        </li>
                    <?php endif ?>
                    <?php if ($emailClinicaHome !== ''): ?>
                        <li><i class="bi bi-envelope"></i><?= h($emailClinicaHome) ?></li>
                    <?php endif ?>
                    <?php if ($horarioResumoHome !== ''): ?>
                        <li><i class="bi bi-clock"></i><?= h($horarioResumoHome) ?></li>
                    <?php endif ?>
                    <?php if ($instaClinicaHome !== ''): ?>
                        <li>
                            <i class="bi bi-instagram"></i>
                            <a href="https://www.instagram.com/<?= h($instaClinicaHome) ?>" target="_blank" rel="noopener" class="text-decoration-none" style="color:inherit;">
                                @<?= h($instaClinicaHome) ?>
                            </a>
                        </li>
                    <?php endif ?>
                </ul>
            </div>
            <a href="<?= BASE ?>/usuario/cadastro.php" class="btn btn-light btn-lg">
                <i class="bi bi-person-plus me-2"></i>Criar minha conta
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/geral/footer.php' ?>
