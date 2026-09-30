<?php

/**
 * Terabras — local translation overrides for the GLPI core catalogue (pt_BR).
 *
 * GLPI loads `GLPI_LOCAL_I18N_DIR/core/<lang>.php` (and `core_*` siblings) as a
 * `phparray` translation source on top of its own catalogue
 * (see Session::loadLanguage()), which is the supported way for a downstream
 * distribution to add or override strings without patching `locales/*.po`.
 * Deployed by the container entrypoint, which copies this directory to
 * `files/_locales/core/`.
 *
 * Keys are upstream msgids; values are what Terabras shows instead.
 *
 * ---------------------------------------------------------------------------
 * WHAT IS OVERRIDDEN, AND WHAT IS DELIBERATELY NOT
 * ---------------------------------------------------------------------------
 * Overridden: ordinary interface copy where "GLPI" is just the name of the
 * running application. Those become "o sistema" — the wording agreed with the
 * customer — because a Terabras user has no reason to meet another product's
 * name in a routine message.
 *
 * NOT overridden, on purpose:
 *
 *  - Console command descriptions (`php bin/console ...`, "Create a new local
 *    GLPI user", "Check GLPI source code file integrity", …). These are
 *    troubleshooting surfaces for operators; renaming them would make the
 *    documentation and the tool disagree.
 *  - "GLPI Agent", "GLPI Native Inventory", "When reported by GLPI agent …".
 *    The agent is a SEPARATE product the customer installs on their machines;
 *    it keeps its real name or nobody can find it.
 *  - GLPI Network / GLPI Cloud / registration-key copy. That is a commercial
 *    offer from Teclib', not ours to re-label.
 *  - Telemetry copy. The data really is sent to the GLPI project, so the person
 *    consenting has to be told whose project it is.
 *  - Marketplace strings (the marketplace is disabled — see inc/downstream.php).
 *  - Anything carrying a table, column, class or file name.
 *
 * Copyright and licence notices are not translations and are untouched.
 */

return [
    // -----------------------------------------------------------------------
    // Terabras installer (strings introduced by templates.terabras/install/)
    // -----------------------------------------------------------------------
    'Administrator account'
        => 'Conta de administrador',
    'This password is shown only once. Copy it now and store it somewhere safe.'
        => 'Esta senha é exibida apenas uma vez. Copie-a agora e guarde-a em local seguro.',
    'The administrator account %s was configured with the password provided to the installer.'
        => 'A conta de administrador %s foi configurada com a senha fornecida ao instalador.',
    'You can create, modify or delete accounts once logged in.'
        => 'Você pode criar, alterar ou excluir contas após entrar no sistema.',
    'Use %s'
        => 'Usar o %s',

    // Introduced by the Terabras edit to src/Auth.php, which replaces the hardcoded
    // "GLPI internal database" auth-source label with the product name. Being a new
    // msgid it has no upstream translation, so the login page showed the English
    // "Terabras internal database" in a Portuguese UI.
    '%s internal database'
        => 'Banco de dados interno do %s',

    // -----------------------------------------------------------------------
    // The application's own name, where it is used as a label for "the core
    // product" (automatic-action mode, plugin/core origin, inventory source).
    // -----------------------------------------------------------------------
    'GLPI'
        => 'Terabras',
    'Use GLPI'
        => 'Usar o Terabras',
    'Use GLPI in mode'
        => 'Usar o sistema no modo',
    'You have at least one automatic action configured in GLPI mode, we advise you to switch to CLI mode.'
        => 'Você tem pelo menos uma ação automática configurada no modo Terabras; aconselhamos mudar para o modo CLI.',

    // -----------------------------------------------------------------------
    // Setup / installation screens
    // -----------------------------------------------------------------------
    'GLPI setup'
        => 'Configuração do sistema',
    'GLPI update'
        => 'Atualização do sistema',
    'Installation or update of GLPI'
        => 'Instalação ou atualização do sistema',
    'The GLPI database must be configured and installed.'
        => 'O banco de dados do sistema deve ser configurado e instalado.',
    'If you see this page when the installation has already been done, it means that the GLPI database configuration file has been removed or corrupted.'
        => 'Se você está vendo esta página mesmo após a instalação ter sido concluída, isso significa que o arquivo de configuração do banco de dados do sistema foi removido ou corrompido.',
    'Choose \'Install\' for a completely new installation of GLPI.'
        => 'Escolha "Instalar" para uma nova instalação do sistema.',
    'Select \'Upgrade\' to update your version of GLPI from an earlier version'
        => 'Selecione "Atualizar" para atualizar o sistema a partir de uma versão anterior',
    'Checking of the compatibility of your environment with the execution of GLPI'
        => 'Verificando a compatibilidade do seu ambiente para a execução do sistema',
    'Caution! You will update the GLPI database named: %s'
        => 'Atenção! Você irá atualizar o banco de dados do sistema chamado: %s',
    'Current GLPI version not found for database named "%s". Update cannot be done.'
        => 'A versão atual do sistema não foi encontrada no banco de dados "%s". Não foi possível atualizar.',
    'The GLPI codebase has been updated. The update of the GLPI database is necessary.'
        => 'O código-fonte do sistema foi atualizado. É necessário atualizar o banco de dados.',
    'You are trying to use GLPI with outdated files compared to the version of the database. Please install the correct GLPI files corresponding to the version of your database.'
        => 'Você está tentando usar o sistema com arquivos desatualizados em relação à versão do banco de dados. Instale os arquivos correspondentes à versão do seu banco de dados.',
    'The database contains no GLPI tables.'
        => 'O banco de dados não contém tabelas do sistema.',
    'Unable to load the GLPI configuration from the database.'
        => 'Não foi possível carregar a configuração do sistema a partir do banco de dados.',
    'Unable to fetch GLPI version.'
        => 'Não foi possível obter a versão do sistema.',

    // -----------------------------------------------------------------------
    // Requirements and security warnings (Setup > General, home page banners)
    // -----------------------------------------------------------------------
    'PHP directive "session.cookie_secure" should be set to "on" when GLPI can be accessed on HTTPS protocol.'
        => 'A diretiva PHP "session.cookie_secure" deve ser definida como "on" quando o sistema pode ser acessado via protocolo HTTPS.',
    'The web server is configured to allow session cookies only on secured context (https). Therefore, you must access GLPI on a secured context to be able to use it.'
        => 'O servidor web está configurado para permitir cookies de sessão apenas em contexto seguro (HTTPS). Portanto, você deve acessar o sistema em um contexto seguro para poder utilizá-lo.',
    'A minimum of %s is commonly required for GLPI.'
        => 'O mínimo de %s é normalmente necessário para o sistema.',
    'A minimum of 64 Mio is commonly required for GLPI.'
        => 'Um mínimo de 64 MB é normalmente necessário para o sistema.',
    'Installing and enabling the "%s" extension may improve GLPI performance'
        => 'Instalar e habilitar a extensão "%s" pode melhorar o desempenho do sistema',
    'Even if GLPI still supports this PHP version, an upgrade to a more recent PHP version is recommended.'
        => 'Mesmo que o sistema ainda suporte esta versão do PHP, recomenda-se atualizar para uma versão mais recente.',
    'Enable usage of ChaCha20-Poly1305 encryption required by GLPI. This is provided by libsodium 1.0.12 and newer.'
        => 'Habilitar o uso da criptografia ChaCha20-Poly1305 exigida pelo sistema. Fornecida pelo libsodium 1.0.12 ou mais recente.',
    'Permissions for GLPI data directories'
        => 'Permissões para os diretórios de dados do sistema',
    'The database schema is not consistent with the current GLPI version.'
        => 'O esquema do banco de dados não é consistente com a versão atual do sistema.',
    'The database schema is not consistent with the installed GLPI version (%s).'
        => 'O esquema do banco de dados não é consistente com a versão instalada do sistema (%s).',
    'It is recommended to run the "%s" command to validate that the database schema is consistent with the current GLPI version.'
        => 'Recomenda-se executar o comando "%s" para validar se o esquema do banco de dados é consistente com a versão atual do sistema.',
    'GLPI source code integrity is validated.'
        => 'A integridade do código-fonte do sistema foi validada.',
    'GLPI source code integrity is not validated.'
        => 'A integridade do código-fonte do sistema não foi validada.',

    // -----------------------------------------------------------------------
    // General configuration and preferences
    // -----------------------------------------------------------------------
    'GLPI version'
        => 'Versão do sistema',
    'GLPI database version'
        => 'Versão do banco de dados do sistema',
    'Default language of GLPI'
        => 'Idioma padrão do sistema',
    'GLPI server time zone'
        => 'Fuso horário do servidor',
    'GLPI history delay:'
        => 'Atraso do histórico:',
    'This indicates the delay between the source and the replica for GLPI history (table: glpi_logs, column: date_mod).'
        => 'Indica o atraso entre a origem e a réplica para o histórico do sistema (tabela: glpi_logs, coluna: date_mod).',
    'The maximum upload size is primarily determined by the "upload_max_filesize" and "post_max_size" PHP settings. The setting below is to further restrict the uploads for just GLPI.'
        => 'O tamanho máximo de envio é determinado principalmente pelas configurações PHP "upload_max_filesize" e "post_max_size". A configuração abaixo serve para restringir ainda mais os envios apenas no sistema.',
    'Cache namespace can be use to ensure either separation or sharing of multiple GLPI instances data on same cache system.'
        => 'O espaço de nomes de cache pode ser usado para separar ou compartilhar os dados de múltiplas instâncias do sistema no mesmo servidor de cache.',
    'Show GLPI ID'
        => 'Mostrar ID do sistema',
    'Do not use this option if your GLPI is reachable from the internet. Use at your own risk.'
        => 'Não use esta opção se o sistema puder ser acessado pela internet. Use por sua conta e risco.',
    'For more information, check the GLPI logs.'
        => 'Para mais informações, consulte os logs do sistema.',
    'URL "%s" is not considered safe and cannot be fetched from GLPI server.'
        => 'A URL "%s" não é considerada segura e não pode ser obtida pelo servidor do sistema.',
    'Authentication on GLPI database'
        => 'Autenticação no banco de dados do sistema',

    // -----------------------------------------------------------------------
    // Sign-in, accounts and everyday messages
    // -----------------------------------------------------------------------
    'User not authorized to connect in GLPI'
        => 'Usuário não autorizado a se conectar no sistema',
    '%s wants to access your GLPI account'
        => '%s quer acessar sua conta no sistema',
    'If the given email address corresponds to one and only one GLPI user, you will receive an email containing the information required to reset your password. Please contact your administrator if you do not receive an email.'
        => 'Se o endereço de e-mail informado corresponder a um único usuário do sistema, você receberá uma mensagem com as informações necessárias para redefinir sua senha. Caso não receba o e-mail, entre em contato com o administrador.',
    'Contact your GLPI admin!'
        => 'Contate o administrador do sistema!',
    'Go back to GLPI'
        => 'Voltar ao sistema',
    'GLPI page'
        => 'Página do sistema',

    // -----------------------------------------------------------------------
    // Assets, documents and automation
    // -----------------------------------------------------------------------
    'Automatically generated by GLPI %s'
        => 'Gerado automaticamente pelo sistema %s',
    'Last update in GLPI'
        => 'Última atualização no sistema',
    'The asset is created or updated in GLPI'
        => 'O ativo é criado ou atualizado no sistema',
    'Software deleted by GLPI dictionary rules'
        => 'Software excluído pelas regras do dicionário',
    '%s "%s" (%d) is most recent on GLPI side, its update has been skipped.'
        => '%s "%s" (%d) está mais recente no sistema, sua atualização foi ignorada.',
    'Other items do not exist in GLPI core.'
        => 'Os demais itens não existem no núcleo do sistema.',
    'This will only work for types from GLPI itself or enabled plugins that support this action.'
        => 'Isso só funcionará para tipos do próprio sistema ou de plug-ins habilitados que suportem esta ação.',
    'It can be personalized, but some words are reserved such as classes from GLPI like Computer, Monitor, etc.'
        => 'Pode ser personalizado, mas algumas palavras são reservadas, como as classes do sistema (Computer, Monitor, etc.).',
    'The object types families have not been imported as they are not handled by GLPI.'
        => 'As famílias de tipos de objeto não foram importadas, pois não são suportadas pelo sistema.',

    // -----------------------------------------------------------------------
    // Webhooks
    // -----------------------------------------------------------------------
    'CRA is mandatory due to GLPI configuration'
        => 'A autenticação desafio-resposta é obrigatória pela configuração do sistema',
    'Challenge–response authentication can be used to validate the identity of the target server before any data is sent from GLPI. This uses the shared secret that was generated in the above section.'
        => 'A autenticação desafio-resposta pode ser usada para validar a identidade do servidor de destino antes que qualquer dado seja enviado pelo sistema. Ela utiliza o segredo compartilhado gerado na seção acima.',
    'The webhook secret can be shared with a target server to allow it to validate requests are actually coming from the GLPI server and that the content was not modified between GLPI and the target.'
        => 'O segredo do webhook pode ser compartilhado com o servidor de destino para que ele valide que as requisições vêm mesmo do servidor do sistema e que o conteúdo não foi alterado no caminho.',

    // -----------------------------------------------------------------------
    // Plugins and data migrations (operator-facing screens, not CLI help)
    // -----------------------------------------------------------------------
    'This plugin is not available for your GLPI version.'
        => 'Este plug-in não está disponível para a sua versão do sistema.',
    'Plugin "%s" is not available for your GLPI version.'
        => 'O plug-in "%s" não está disponível para a sua versão do sistema.',
    'This plugin requires GLPI parameter %1$s'
        => 'Este plug-in requer o parâmetro %1$s do sistema',
    'Racks plugin is not part of GLPI plugin list. It has never been installed or has been cleaned.'
        => 'O plug-in Racks não faz parte da lista de plug-ins do sistema. Nunca foi instalado ou foi removido.',
    'You are about to launch migration of Appliances plugin data into GLPI core tables.'
        => 'Você está prestes a iniciar a migração dos dados do plug-in Appliances para as tabelas principais do sistema.',
    'You are about to launch migration of Racks plugin data into GLPI core tables.'
        => 'Você está prestes a iniciar a migração dos dados do plug-in Racks para as tabelas principais do sistema.',
    'You are about to launch migration of "%s" plugin data into GLPI core tables.'
        => 'Você está prestes a iniciar a migração dos dados do plug-in "%s" para as tabelas principais do sistema.',
];
