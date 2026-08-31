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

// Equipe da home — nomes ainda placeholder até preencher com os dados
// reais (só trocar o 'nome' de cada um). Estrutura real: 2 médicos
// veterinários + a equipe de atendimento (atuante na área, mas sem
// formação de veterinário — por isso sem "Dr./Dra." e sem se passar por
// profissional formado). Sem foto de verdade ainda, por isso o avatar é
// um ícone — evita usar foto de banco de imagens só pra preencher.
$equipeHome = [
    [
        'nome'   => 'Dra. [nome da veterinária]',
        'cargo'  => 'Médica Veterinária',
        'bio'    => 'Consultas, exames e acompanhamento clínico do seu animal.',
        'icone'  => 'bi-person-heart',
    ],
    [
        'nome'   => 'Dr. [nome do veterinário]',
        'cargo'  => 'Médico Veterinário',
        'bio'    => 'Consultas, cirurgias e procedimentos com acompanhamento completo.',
        'icone'  => 'bi-person-badge',
    ],
    [
        'nome'   => 'Equipe de Atendimento',
        'cargo'  => 'Suporte e Cuidado',
        'bio'    => 'Time atuante que acompanha de perto cada visita, cuidando do conforto e bem-estar do seu animal.',
        'icone'  => 'bi-people-fill',
    ],
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
        <div class="row g-4 justify-content-center">
            <?php foreach ($equipeHome as $prof): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="card home-equipe-card">
                        <div class="home-equipe-avatar"><i class="bi <?= h($prof['icone']) ?>"></i></div>
                        <h3><?= h($prof['nome']) ?></h3>
                        <span class="home-equipe-cargo"><?= h($prof['cargo']) ?></span>
                        <p><?= h($prof['bio']) ?></p>
                    </div>
                </div>
            <?php endforeach ?>
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
                    <li><i class="bi bi-geo-alt"></i>R. Elías Chibeb, 580 - Centro, Sebastianópolis do Sul - SP</li>
                    <li>
                        <i class="bi bi-whatsapp"></i>
                        <a href="<?= h(waLink('17997806050')) ?>" target="_blank" rel="noopener" class="text-decoration-none" style="color:inherit;">
                            (17) 99780-6050
                        </a>
                    </li>
                    <li><i class="bi bi-envelope"></i>contato@agrolife.com</li>
                    <li><i class="bi bi-clock"></i>Segunda a sexta: 7h30 às 18h · Sábado: 7h30 às 12h</li>
                    <li>
                        <i class="bi bi-instagram"></i>
                        <a href="https://www.instagram.com/agrolife_sebas" target="_blank" rel="noopener" class="text-decoration-none" style="color:inherit;">
                            @agrolife_sebas
                        </a>
                    </li>
                </ul>
            </div>
            <a href="<?= BASE ?>/usuario/cadastro.php" class="btn btn-light btn-lg">
                <i class="bi bi-person-plus me-2"></i>Criar minha conta
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/geral/footer.php' ?>
