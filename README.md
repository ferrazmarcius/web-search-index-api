Guia de Implementação da API de Indexação do Google para Novos Domínios
Este documento serve como um checklist completo para configurar a submissão de URLs via API de Indexação do Google em qualquer site que não seja WordPress. O processo está dividido em três fases: Configuração Google, Configuração no Servidor e Automação.

Fase 1: Configuração nas Plataformas Google
Para cada novo domínio, é necessário criar um conjunto de credenciais exclusivo.

Criar Projeto no Google Cloud:

Aceda ao Google Cloud Platform.

Crie um projeto novo e dedicado para o domínio (ex: meunovosite-api).

Ativar a Indexing API:

Dentro do novo projeto, utilize o link direto para ativar a API: Ativar a Indexing API.

Criar Conta de Serviço (Service Account):

No menu "IAM e Admin" > "Contas de serviço", crie uma nova conta de serviço (ex: sa-meunovosite).

Não é necessário atribuir-lhe uma função ("role") durante a criação.

Gerar Chave JSON:

Para a conta de serviço recém-criada, aceda ao separador "Chaves", clique em "Adicionar Chave" > "Criar nova chave".

Escolha o formato JSON. O download de um ficheiro (ex: meunovosite-api-xxxxxxxx.json) irá começar. Este ficheiro é a sua senha.

Conceder Permissão no Google Search Console:

Aceda ao Google Search Console.

Adicione e verifique o novo domínio. Recomendação forte: Use a opção "Propriedade do Domínio" e verifique via DNS. Isto evita todos os problemas futuros com www vs não-www.

Vá para "Configurações" > "Usuários e permissões" e adicione o e-mail da sua nova Conta de Serviço como "Proprietário".

Fase 2: Configuração no Servidor
Nesta fase, colocamos as nossas ferramentas no servidor do novo domínio.

Criar Estrutura de Ficheiros:

Aceda ao servidor do novo domínio via FTP ou Gestor de Ficheiros.

Na raiz do domínio, crie uma pasta com um nome seguro e difícil de adivinhar (ex: api-google-manager-c4t8b2/).

Faça o upload dos seguintes ficheiros para dentro desta pasta:

O ficheiro index.php (a nossa ferramenta manual).

O ficheiro cron_submit.php (o nosso robô automático).

O novo ficheiro JSON que você descarregou na Fase 1.

Configurar os Scripts PHP:

Edite os ficheiros index.php e cron_submit.php diretamente no servidor para os ajustar ao novo domínio.

No ficheiro index.php:

PHP

// ----------- CONFIGURAÇÃO -----------
// Opcional: Defina uma nova senha para esta ferramenta.
$senha_de_acesso = 'SenhaForteParaNovoSite456!';

// OBRIGATÓRIO: Coloque o nome do novo ficheiro JSON.
$caminho_chave_json = 'meunovosite-api-xxxxxxxx.json'; 
No ficheiro cron_submit.php:

PHP

// ----------- CONFIGURAÇÃO -----------
// OBRIGATÓRIO: Altere para o URL do sitemap do NOVO domínio.
define('SITEMAP_URL', 'https://novodominio.com/sitemap.xml'); 

// OBRIGATÓRIO: Coloque o nome do novo ficheiro JSON.
define('SERVICE_ACCOUNT_KEY_FILE', 'meunovosite-api-xxxxxxxx.json');
Fase 3: Automação com Cron Job
O último passo é criar o "despertador" que irá executar o nosso robô automaticamente.

Agendar a Tarefa:

Aceda ao seu painel de hospedagem (cPanel, Plesk, etc.) e encontre a ferramenta "Cron Jobs".

Crie uma nova tarefa para ser executada uma vez por dia, de madrugada (ex: às 4:05 da manhã).

Configuração de tempo: 5 4 * * *

Inserir o Comando:

No campo "Comando", insira o caminho absoluto para o seu novo script. Use o template abaixo, substituindo os valores entre [].

Bash

/usr/bin/php /home/[SEU_USERNAME]/[NOVODOMINIO.COM]/[PASTA_SECRETA]/cron_submit.php > /dev/null 2>&1
Verificar a Execução:

No dia seguinte, verifique se um ficheiro chamado submission_history.log foi criado dentro da sua pasta secreta. A presença deste ficheiro confirma que a automação está a funcionar.

Fase 4: Lembrete Estratégico de SEO
API é uma Ferramenta de Notificação: Lembre-se que esta API apenas acelera a descoberta de URLs. Ela não garante a indexação.

A Qualidade é Rei: A decisão de indexar uma página continua a depender da avaliação que o Google faz da qualidade, originalidade e autoridade (E-E-A-T) do seu conteúdo. A parte técnica está agora resolvida e automatizada, o seu foco deve estar sempre em criar o melhor conteúdo possível.