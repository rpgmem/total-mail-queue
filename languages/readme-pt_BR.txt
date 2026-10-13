=== Total Mail Queue ===

Coloca os e-mails enviados em uma fila, gerencia contas SMTP com limites diários e mensais e avisa se o site enviar e-mails demais.

== Description ==

O Total Mail Queue guarda em uma fila cada e-mail que o WordPress envia e o libera no ritmo que você escolher, pelo envio que o site já usa ou por contas SMTP com limites diários e mensais. Uma enxurrada repentina de e-mails, como um robô abusando de um formulário, se acumula na fila em vez de sair do site, e um alerta avisa você.

* Intercepta o wp_mail() e coloca as mensagens em uma fila
* Defina quantos e-mails são enviados e em que intervalo
* Gerencie várias contas SMTP com prioridade e limites de envio diários e mensais
* Teste conexões SMTP antes de enviar
* Registre todos os e-mails da fila, com filtro por status (enviado / erro / alerta)
* Reenvio automático de e-mails que falharam, com limite de tentativas configurável
* Reenvie ou force o reenvio de e-mails que falharam a partir do Registro
* Receba alertas quando a fila crescer de forma inesperada
* Receba alertas quando o WordPress não conseguir enviar e-mails
* Veja se o WP-Cron está enviando a fila e copie a linha de cron do servidor que torna o envio confiável
* Exporte e importe as configurações e as contas SMTP em XML
* Detecta e resolve conflitos com outros plugins de e-mail

**Este plugin é um fork do [Mail Queue](https://wordpress.org/plugins/mail-queue/), da WDM.**

== Frequently Asked Questions ==

= O que configuro depois de ativar? =

Abra as configurações do plugin, escolha o modo de operação e defina quantos e-mails saem a cada execução e com que frequência. Os alertas são configurados na mesma tela: o tamanho da fila que dispara um alerta e quem o recebe.

= Como a fila funciona? =

O próprio plugin responde ao wp_mail(). Cada mensagem é gravada no banco de dados e uma tarefa do WP-Cron envia um lote a cada intervalo definido, então nada sai mais rápido que esse ritmo.

= Ele muda a forma como meus e-mails são entregues? =

Só se você pedir. O método padrão `auto` entrega cada mensagem para o que já envia e-mails no seu site hoje: outro plugin SMTP, o mail() do PHP, um serviço de API. Adicione contas SMTP no plugin para enviar por elas, com seus limites, ou escolha `php` para usar sempre o envio do próprio WordPress.

= Meu site usa cache de página. A fila ainda será enviada? =

Um cache de página (W3 Total Cache, WP Rocket, um proxy como o NGINX na frente do site) atende os visitantes sem executar o WordPress, então o WP-Cron roda raramente e a fila espera. Chame o wp-cron.php por um cron do servidor a cada poucos minutos; a aba Cron mostra a linha exata para o seu servidor.

= O que acontece com os anexos? =

Eles são copiados quando o e-mail entra na fila e guardados até que esse e-mail seja enviado; depois são removidos.

= Para que servem os alertas de fila? =

Uma fila que cresce muito além do normal costuma indicar que algo está enviando e-mails que não deveria, como um formulário sob ataque. A fila já desacelera esses e-mails; o alerta avisa quando o tamanho dela passa do limite que você escolheu, para que você verifique antes que mais deles saiam.

= Um e-mail pode passar na frente dos outros? =

Sim. Adicione o cabeçalho `X-Mail-Queue-Prio: High` e o e-mail sai antes dos e-mails comuns na próxima execução. Cada fonte também pode ter sua própria prioridade na aba Fontes.

Com o wp_mail():

`wp_mail( $to, $subject, $message, array( 'X-Mail-Queue-Prio: High' ) );`

Para e-mails do WooCommerce:

`add_filter( 'woocommerce_mail_callback_params', function ( $params ) {
	$header = 'X-Mail-Queue-Prio: High';
	if ( is_array( $params[3] ) ) {
		$params[3][] = $header;
	} else {
		$params[3] = trim( $params[3] . "\r\n" . $header );
	}
	return $params;
} );`

No Contact Form 7, adicione `X-Mail-Queue-Prio: High` como uma linha dos Cabeçalhos adicionais do formulário, na aba E-mail.

= Um e-mail pode ignorar a fila? =

Sim, com o cabeçalho `X-Mail-Queue-Prio: Instant`: o e-mail é enviado na hora e registrado como `instant`. Ele ignora o ritmo definido, então reserve-o para o raro e-mail que não pode esperar, como uma redefinição de senha. Uma fonte que você desativou continua bloqueando-o, e no modo Bloquear ele fica retido como qualquer outro e-mail.

Para e-mails de redefinição de senha:

`add_filter( 'retrieve_password_notification_email', function ( $email ) {
	$header = 'X-Mail-Queue-Prio: Instant';
	if ( is_array( $email['headers'] ) ) {
		$email['headers'][] = $header;
	} else {
		$email['headers'] = trim( $email['headers'] . "\r\n" . $header );
	}
	return $email;
} );`

= O que o wp_mail() retorna enquanto a fila está ativa? =

`true` assim que a mensagem é gravada na fila, e `false` se não puder ser gravada. Um e-mail `Instant` é enviado mesmo que seu registro falhe, porque foi marcado como um que não pode esperar.

= Funciona em uma rede multisite? =

Ative-o em cada site, e não na rede inteira; cada site mantém então sua própria fila e suas configurações.

== Screenshots ==

As imagens ficam na pasta `docs/screenshots/` do repositório e aparecem no README.

1. Registro dos e-mails enviados, com falha e bloqueados, com filtros de busca, status, fonte e data
2. Lista de retenção dos e-mails na fila e retidos
3. Contas SMTP com prioridade e limites diários e mensais
4. Fontes detectadas no site, com prioridade e conta preferida
5. Configurações do modelo HTML com a prévia ao vivo
6. Configurações gerais: modo de operação, ritmo da fila e alertas
7. Acesso: quais funções veem e quais gerenciam o plugin
8. Documentação integrada
9. Modo escuro
10. Cron: o estado do processador da fila, a saúde do WP-Cron e a linha de cron do servidor

== Installation ==

Instale e ative o plugin e ligue a fila nas configurações. A fila é enviada pelo WP-Cron, então garanta que ele rode de forma confiável; em um site com cache ou pouco movimento, a aba Cron traz a linha do servidor que resolve isso.

As atualizações chegam pela tela de atualizações normal do WordPress, direto das [Releases no GitHub](https://github.com/rpgmem/total-mail-queue/releases) deste plugin. Cada pacote é conferido contra o SHA-256 publicado antes de ser instalado, e o próprio botão do WordPress por plugin liga as atualizações automáticas.
