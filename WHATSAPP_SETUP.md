# WhatsApp (Evolution API) — setup e modo de teste

Documenta a integração de WhatsApp do AgroLife: onde ela roda, como testar
sem risco de mandar mensagem pra cliente de verdade, e o que ficou pendente.

## Infraestrutura

- **VPS**: `143.95.219.124`, mesma máquina que já hospeda o Evolution API
  de outros dois sistemas do usuário (Belos Cílios, Auralis) — Docker,
  container `evolution-api` (`evoapicloud/evolution-api:v2.3.7`) +
  `evolution-postgres` + `evolution-redis`.
- **Instância do AgroLife**: `AgroLife` — **isolada** das outras duas
  (instância própria, token próprio). Conectada ao WhatsApp pessoal do
  usuário (17996660665) só pra testes; pode trocar de número depois sem
  afetar Belos Cílios ou Auralis.
- **Painel visual**: http://143.95.219.124:8080/manager/ — dá pra ver o
  QR code se atualizando ao vivo direto no navegador (mais confiável que
  gerar e repassar QR por chat — o QR do WhatsApp expira em segundos).
- **Segredos**: `config/evolution_keys.php` (gitignored, nunca commitado).
  Guarda o **token da própria instância AgroLife** — não a chave global
  do servidor Evolution (essa também administra Belos Cílios e Auralis;
  de propósito não fica no código do AgroLife, pra um vazamento aqui não
  dar acesso aos outros dois sistemas). Precisa ser copiado manualmente
  pra produção (FTP/cPanel), igual todo arquivo de segredo do projeto.

## Modo de teste

Em **Configurações → Modo de teste do WhatsApp** (`painel/configuracoes.php`):
um checkbox + um número. Enquanto ligado, **toda** chamada de
`enviarWhatsApp()` no sistema — não importa pra quem o código mandaria —
é redirecionada pro número de teste. Implementado num ponto único dentro
da própria função (`config/funcoes.php`), então nenhum dos pontos que
disparam mensagem precisa saber que esse modo existe.

Serve pra validar o sistema inteiro em produção, com dados e fluxos
reais, sem risco de mensagem cair em cliente de verdade enquanto ainda
em teste. Pra ir ao ar de vez: desliga o checkbox.

## O que já dispara WhatsApp

Testado de ponta a ponta (mensagem chegando de verdade no celular):

- **`painel/agenda.php`** — clínica cria agendamento → avisa o cliente.
- **`painel/api_agendamento.php`** — clínica cancela → avisa o cliente.
- **`usuario/processa_agendamento.php`** — cliente cancela o próprio
  agendamento (novidade: antes só a clínica podia cancelar) → avisa a
  clínica, no número de `telefone_clinica` (Configurações).
- **`cron/whatsapp_vacinas.php`** — lembrete de vacina (já existia).

**De propósito sem notificação automática ainda**: confirmar, marcar
falta, concluir, reabrir agendamento. São ajustes mais internos do dia
a dia da clínica — não decidi mandar mensagem sozinho pra esses sem
confirmar antes.

## Pendente

- **Nome do aparelho no celular do cliente aparece "Google Chrome
  Belos Cílios"** em vez de algo específico do AgroLife. Causa: variável
  de ambiente `CONFIG_SESSION_PHONE_CLIENT=BelosCilios` no container —
  é **global**, não existe campo por-instância no Evolution API pra
  sobrescrever isso (confirmado no código-fonte oficial). Corrigir exige
  trocar a variável e reiniciar o container `evolution-api`, o que
  afeta as sessões de Belos Cílios e Auralis também (ficam salvas no
  Postgres, então devem reconectar sozinhas — risco baixo, mas não
  zero). **Adiado a pedido do usuário** — só o nome, não muda
  funcionamento de nada. Retomar quando quiser resolver.
