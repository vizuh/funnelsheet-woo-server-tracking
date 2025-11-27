<div align="center">
  <a href="https://vizuh.com" target="_blank">
    <img src="assets/vizuh-logo.png" alt="Vizuh" height="80" style="margin: 0 30px;">
  </a>
  <a href="https://funnelsheet.com" target="_blank">
    <img src="assets/funnelsheet-logo.png" alt="Funnelsheet" height="80" style="margin: 0 30px;">
  </a>
</div>

<div align="center">
  <h3>🇺🇸 <a href="#english">English</a> | 🇧🇷 <a href="#português">Português</a></h3>
</div>

---

<a id="english"></a>
# 🇺🇸 Funnelsheet – WooCommerce Server-Side Tracking (GA4 & GTM-SS)

**Capture 100% of your WooCommerce purchases using bulletproof server-side tracking.**

Google Analytics is missing 20-30% of your purchases thanks to ad blockers. This plugin captures every single transaction server-side, finally giving you the real numbers on what's actually working.

## 🎯 Features

- **100% Order Capture** - Server-side tracking bypasses all ad blockers and browser restrictions
- **Dual Destination Support** - Send events to GA4 Measurement Protocol or server-side GTM (sGTM)
- **Reliable Queue System** - Events are queued and retried with exponential backoff
- **Attribution Preservation** - Captures and sends UTM parameters, client IDs, and campaign data
- **Enhanced Conversions** - Automatically hashes and sends user data for GA4 enhanced conversions
- **Event Log Dashboard** - Monitor all events with status tracking and manual retry
- **Refund Tracking** - Automatically captures refund events
- **Action Scheduler Integration** - Uses WooCommerce's built-in scheduler for reliable processing

## 📋 Requirements

- WordPress 5.9 or higher
- WooCommerce 3.5 or higher
- PHP 7.4 or higher

## 🚀 Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **WooCommerce → Server Tracking** to configure settings

## ⚙️ Configuration

### GA4 Measurement Protocol Setup

1. Go to **WooCommerce → Server Tracking**
2. Select **GA4 Measurement Protocol** as destination type
3. Enter your GA4 Measurement ID (format: `G-XXXXXXXXXX`)
4. Get your API Secret from GA4:
   - Go to GA4 Admin → Data Streams → Choose your stream
   - Scroll to "Measurement Protocol API secrets"
   - Create a new secret or copy existing one
5. Click **Send Test Event** to verify configuration

### Server-Side GTM (sGTM) Setup

1. Go to **WooCommerce → Server Tracking**
2. Select **Server-Side GTM** as destination type
3. Enter your sGTM endpoint URL
4. (Optional) Add authorization header if required
5. Click **Send Test Event** to verify

### Advanced Settings

- **Track on Processing** - Send purchase event when order status changes to Processing
- **Track on Completed** - Send purchase event when order status changes to Completed
- **Max Retry Attempts** - Number of times to retry failed events (1-10)
- **Queue Processing Interval** - How often to process the queue (5/10/15 minutes)
- **Debug Mode** - Enable detailed logging for troubleshooting

## 📊 Event Log

View all tracked events at **WooCommerce → Tracking Events**:

- Filter by status (All, Pending, Sent, Failed)
- View event details and error messages
- Manually retry failed events
- Export events to CSV

## 🔌 Attribution Integration

This plugin automatically detects and sends attribution data stored in order meta:

- **ClickTrail Plugin** - Reads `_clicktrail_attribution` meta
- **Standard UTM Parameters** - Reads `_utm_source`, `_utm_medium`, `_utm_campaign`, etc.
- **GA4 Client ID** - Reads `_ga_client_id` meta
- **Facebook Click IDs** - Reads `_fbp` and `_fbc` meta

If no attribution data is found, the plugin generates a fallback client ID from the customer email.

## 🔧 How It Works

1. **Order Created** - WooCommerce processes an order
2. **Event Captured** - Plugin hooks into order status change and extracts all data
3. **Queued** - Event is stored in database queue with status "pending"
4. **Processed** - Action Scheduler runs every 5 minutes and processes pending events
5. **Sent** - Events are sent to GA4 or sGTM via HTTP request
6. **Retry** - Failed events are retried with exponential backoff (2^attempts minutes)
7. **Logged** - Final status (sent/failed) is logged for monitoring

## 📦 What's Tracked

### Purchase Events

- Transaction ID, value, currency
- Tax, shipping, coupon codes
- Line items with product details
- Customer information (hashed for privacy)
- Attribution data (UTMs, client IDs, etc.)

### Refund Events

- Transaction ID, refund value, currency
- Attribution data

## 🛠️ Developer Hooks

### Filters

```php
// Modify event data before queuing
add_filter('wc_sst_event_data', function($event_data, $order) {
    // Add custom data
    $event_data['custom_field'] = 'custom_value';
    return $event_data;
}, 10, 2);

// Modify whether order should be tracked
add_filter('wc_sst_should_track_order', function($should_track, $order) {
    // Custom logic
    return $should_track;
}, 10, 2);
```

### Actions

```php
// After event is queued
add_action('wc_sst_event_queued', function($event_id, $order_id) {
    // Custom logic
}, 10, 2);

// After event is sent successfully
add_action('wc_sst_event_sent', function($event_id) {
    // Custom logic
}, 10);
```

## ❓ FAQ

### Does this replace client-side tracking?

This plugin is designed to complement client-side tracking, not replace it. For best results, use both:
- **Client-side GA4** - Captures user behavior, pageviews, add-to-cart, etc.
- **Server-side purchases** - Ensures 100% purchase capture even with ad blockers

### Will this cause duplicate purchases in GA4?

The plugin uses the same transaction ID as your order number. GA4 automatically deduplicates events with the same transaction ID. However, you should:
1. Use server-side tracking for purchases **OR** client-side, not both
2. Configure your setup to avoid sending the same event twice

### How do I verify events are being sent?

1. Go to **WooCommerce → Tracking Events** to see event status
2. Use GA4 DebugView to see events in real-time
3. Enable Debug Mode in plugin settings for detailed logs

### What happens if GA4/sGTM is down?

Events are queued and will be retried automatically with exponential backoff. Failed events remain in the queue for manual retry.

## 👥 Authors

- **[Vizuh](https://vizuh.com)** - Digital Solutions
- **HugoC** - [wordpress.org](https://profiles.wordpress.org/)
- **Atroci** - Developer

## 📝 License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## 🤝 Support

For issues or questions, please visit [vizuh.com](https://vizuh.com) or contact support.

## 🔄 Changelog

### 1.0.0
- Initial release
- GA4 Measurement Protocol support
- Server-side GTM support
- Event queue with retry logic
- Event log dashboard
- Attribution data capture

---

<a id="português"></a>
# 🇧🇷 Funnelsheet – Rastreamento Server-Side para WooCommerce (GA4 & GTM-SS)

**Capture 100% das suas compras no WooCommerce usando rastreamento server-side à prova de bloqueadores.**

O Google Analytics está perdendo 20-30% das suas compras devido aos bloqueadores de anúncios. Este plugin captura todas as transações no servidor, finalmente te dando os números reais sobre o que está realmente funcionando.

## 🎯 Funcionalidades

- **100% de Captura de Pedidos** - Rastreamento server-side contorna todos os bloqueadores de anúncios e restrições do navegador
- **Suporte a Dois Destinos** - Envie eventos para GA4 Measurement Protocol ou GTM server-side (sGTM)
- **Sistema de Fila Confiável** - Eventos são enfileirados e reprocessados com backoff exponencial
- **Preservação de Atribuição** - Captura e envia parâmetros UTM, client IDs e dados de campanha
- **Conversões Aprimoradas** - Automaticamente faz hash e envia dados de usuário para conversões aprimoradas do GA4
- **Painel de Log de Eventos** - Monitore todos os eventos com rastreamento de status e retry manual
- **Rastreamento de Reembolsos** - Captura automaticamente eventos de reembolso
- **Integração com Action Scheduler** - Usa o agendador nativo do WooCommerce para processamento confiável

## 📋 Requisitos

- WordPress 5.9 ou superior
- WooCommerce 3.5 ou superior
- PHP 7.4 ou superior

## 🚀 Instalação

1. Faça upload da pasta do plugin para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Navegue até **WooCommerce → Server Tracking** para configurar

## ⚙️ Configuração

### Configuração do GA4 Measurement Protocol

1. Vá para **WooCommerce → Server Tracking**
2. Selecione **GA4 Measurement Protocol** como tipo de destino
3. Insira seu GA4 Measurement ID (formato: `G-XXXXXXXXXX`)
4. Obtenha sua API Secret do GA4:
   - Vá para GA4 Admin → Data Streams → Escolha seu stream
   - Role até "Measurement Protocol API secrets"
   - Crie um novo secret ou copie um existente
5. Clique em **Send Test Event** para verificar a configuração

### Configuração do GTM Server-Side (sGTM)

1. Vá para **WooCommerce → Server Tracking**
2. Selecione **Server-Side GTM** como tipo de destino
3. Insira a URL do seu endpoint sGTM
4. (Opcional) Adicione cabeçalho de autorização se necessário
5. Clique em **Send Test Event** para verificar

### Configurações Avançadas

- **Track on Processing** - Enviar evento de compra quando o status do pedido mudar para Processando
- **Track on Completed** - Enviar evento de compra quando o status do pedido mudar para Completo
- **Max Retry Attempts** - Número de vezes para reprocessar eventos falhos (1-10)
- **Queue Processing Interval** - Com que frequência processar a fila (5/10/15 minutos)
- **Debug Mode** - Ativar logging detalhado para resolução de problemas

## 📊 Log de Eventos

Visualize todos os eventos rastreados em **WooCommerce → Tracking Events**:

- Filtrar por status (Todos, Pendente, Enviado, Falhou)
- Ver detalhes do evento e mensagens de erro
- Reprocessar manualmente eventos falhos
- Exportar eventos para CSV

## 🔌 Integração de Atribuição

Este plugin detecta e envia automaticamente dados de atribuição armazenados nos metadados do pedido:

- **Plugin ClickTrail** - Lê meta `_clicktrail_attribution`
- **Parâmetros UTM Padrão** - Lê `_utm_source`, `_utm_medium`, `_utm_campaign`, etc.
- **GA4 Client ID** - Lê meta `_ga_client_id`
- **Facebook Click IDs** - Lê meta `_fbp` e `_fbc`

Se nenhum dado de atribuição for encontrado, o plugin gera um client ID de fallback a partir do email do cliente.

## 🔧 Como Funciona

1. **Pedido Criado** - WooCommerce processa um pedido
2. **Evento Capturado** - Plugin se conecta à mudança de status do pedido e extrai todos os dados
3. **Enfileirado** - Evento é armazenado na fila do banco de dados com status "pendente"
4. **Processado** - Action Scheduler executa a cada 5 minutos e processa eventos pendentes
5. **Enviado** - Eventos são enviados para GA4 ou sGTM via requisição HTTP
6. **Retry** - Eventos falhos são reprocessados com backoff exponencial (2^tentativas minutos)
7. **Registrado** - Status final (enviado/falhou) é registrado para monitoramento

## 📦 O Que é Rastreado

### Eventos de Compra

- ID da transação, valor, moeda
- Impostos, frete, códigos de cupom
- Itens de linha com detalhes do produto
- Informações do cliente (hash para privacidade)
- Dados de atribuição (UTMs, client IDs, etc.)

### Eventos de Reembolso

- ID da transação, valor do reembolso, moeda
- Dados de atribuição

## 🛠️ Hooks para Desenvolvedores

### Filtros

```php
// Modificar dados do evento antes de enfileirar
add_filter('wc_sst_event_data', function($event_data, $order) {
    // Adicionar dados personalizados
    $event_data['custom_field'] = 'custom_value';
    return $event_data;
}, 10, 2);

// Modificar se o pedido deve ser rastreado
add_filter('wc_sst_should_track_order', function($should_track, $order) {
    // Lógica personalizada
    return $should_track;
}, 10, 2);
```

### Actions

```php
// Após evento ser enfileirado
add_action('wc_sst_event_queued', function($event_id, $order_id) {
    // Lógica personalizada
}, 10, 2);

// Após evento ser enviado com sucesso
add_action('wc_sst_event_sent', function($event_id) {
    // Lógica personalizada
}, 10);
```

## ❓ Perguntas Frequentes

### Isso substitui o rastreamento client-side?

Este plugin foi projetado para complementar o rastreamento client-side, não substituí-lo. Para melhores resultados, use ambos:
- **GA4 Client-side** - Captura comportamento do usuário, visualizações de página, adicionar ao carrinho, etc.
- **Compras Server-side** - Garante 100% de captura de compras mesmo com bloqueadores de anúncios

### Isso vai causar compras duplicadas no GA4?

O plugin usa o mesmo ID de transação que o número do seu pedido. O GA4 automaticamente remove duplicatas de eventos com o mesmo ID de transação. No entanto, você deve:
1. Usar rastreamento server-side para compras **OU** client-side, não ambos
2. Configurar seu setup para evitar enviar o mesmo evento duas vezes

### Como verifico se os eventos estão sendo enviados?

1. Vá para **WooCommerce → Tracking Events** para ver o status dos eventos
2. Use o GA4 DebugView para ver eventos em tempo real
3. Ative o Debug Mode nas configurações do plugin para logs detalhados

### O que acontece se o GA4/sGTM estiver fora do ar?

Eventos são enfileirados e serão reprocessados automaticamente com backoff exponencial. Eventos falhos permanecem na fila para retry manual.

## 👥 Autores

- **[Vizuh](https://vizuh.com)** - Soluções Digitais
- **HugoC** - [wordpress.org](https://profiles.wordpress.org/)
- **Atroci** - Desenvolvedor

## 📝 Licença

GPL v2 ou posterior - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## 🤝 Suporte

Para problemas ou questões, visite [vizuh.com](https://vizuh.com) ou contate o suporte.

## 🔄 Changelog

### 1.0.0
- Lançamento inicial
- Suporte a GA4 Measurement Protocol
- Suporte a GTM server-side
- Fila de eventos com lógica de retry
- Painel de log de eventos
- Captura de dados de atribuição
