# UNY-DOC-006 · API e Webhooks

**Projeto:** UnyEducation
**Versão:** 0.1 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Pesquisa de documentação feita em:** 06/10/2026
**Depende de:** UNY-DOC-002, UNY-DOC-003, UNY-DOC-004 (seções 8 e 9), UNY-DOC-005 (seções 10 a 12)

---

## 1. Como ler este documento

Este documento define **como o AVA conversa com o mundo**:

1. O **contrato comum** que todo webhook de pagamento segue ao entrar no AVA (seção 3)
2. O **evento interno padrão** para o qual todo adaptador traduz (seção 4)
3. O **levantamento, plataforma por plataforma**, de como cada uma notifica vendas (seção 5)
4. O **contrato entre AVA e painel de matrículas** (seção 7)
5. A **API interna** do AVA (seção 8)

### Aviso sobre o nível de confiança

Nem toda plataforma tem documentação pública fácil de ler. Cada uma recebe uma etiqueta:

| Etiqueta | Significa |
|---|---|
| **Confirmada** | Documentação oficial lida nesta pesquisa |
| **Parcial** | Parte confirmada em fonte oficial; o resto veio de fontes secundárias (guias de integração de terceiros, exemplos de código) ou não foi encontrado |
| **Baixa** | Apenas fontes secundárias, ou fonte antiga; os detalhes **precisam** ser validados |

**Regra do projeto:** nenhum adaptador vai para produção só com base neste documento. Todo adaptador passa pela **validação com evento real** (seção 11): capturar eventos de teste da própria plataforma e confirmar cada campo e cada forma de autenticação antes de ligar.

Stripe e Pagar.me **não** foram pesquisados nesta rodada.

## 2. Superfícies de API

| Superfície | Direção | Quem chama | Autenticação |
|---|---|---|---|
| **Webhooks de pagamento** | Entrada | Plataformas de venda e gateways | Específica de cada plataforma (seção 5) |
| **Ponte AVA ↔ painel** | Ambas | AVA e painel de matrículas | Assinatura HMAC própria (seção 7) |
| **API do aluno** | Interna | Navegador do aluno | Sessão do aluno + CSRF |
| **API do parceiro** | Interna | Painel do parceiro | Sessão da equipe + CSRF |
| **API do operador** | Interna | Painel do operador | Sessão do operador + CSRF |

Todas as chamadas de navegador resolvem o parceiro **pelo endereço (host)** da requisição (UNY-DOC-004, seção 5.5). Nenhuma delas aceita um `tenant_id` enviado pelo cliente.

---

## 3. Contrato comum dos webhooks de entrada

### 3.1 Endereço

```text
POST https://{host-do-parceiro}/webhooks/{uuid_publico_da_integracao}
```

- O `uuid_publico` identifica a integração (e, por ela, o parceiro). Não é adivinhável e não substitui a autenticação da plataforma.
- Um mesmo endereço atende **uma** integração de **um** parceiro.
- Apenas HTTPS.

### 3.2 Passos obrigatórios (nesta ordem)

```text
1. Limitar tamanho do corpo e taxa de requisições
2. Localizar a integração pelo uuid_publico; se não existir ou estiver desativada → 404
3. Ler o corpo BRUTO (sem reformatar)
4. Validar a autenticidade pelo método da plataforma
      falhou → 401, sem gravar como evento válido (registrar a tentativa)
5. Extrair o identificador do evento (ou derivar um, seção 3.4)
6. Gravar o corpo bruto, criptografado, em eventos_pagamento
      já existe (mesma integração + id_externo) → responder 200 e parar
7. Responder 200 imediatamente
8. Colocar o processamento na fila
```

O processamento (traduzir, decidir, criar ou suspender matrícula) acontece **depois** da resposta. As plataformas esperam resposta rápida (o Asaas, por exemplo, espera no máximo 10 segundos).

### 3.3 Política de respostas

| Situação | Resposta | Por quê |
|---|---|---|
| Evento válido e gravado | **200** | |
| Evento repetido | **200** | A origem não deve reenviar |
| Evento válido, mas sem utilidade (produto não vinculado, tipo ignorado) | **200**, marcado como `ignorado` | Responder erro aqui faria a plataforma reenviar ou **pausar a fila** (o Asaas pausa após 15 falhas consecutivas) |
| Autenticação inválida | **401** | |
| Corpo ilegível | **400** | |
| Falha interna ao gravar | **500** | Pede reenvio |

### 3.4 Identificador do evento (idempotência)

Chave única: (`integracao_id`, `id_externo`).

| Situação | Como obter `id_externo` |
|---|---|
| A plataforma envia um identificador de evento estável | Usar esse identificador |
| O identificador **muda a cada reenvio** | **Não usar.** Derivar de dados do recurso |
| Não há identificador de evento | Derivar: hash de (`tipo_externo` + identificador da compra/assinatura + estado + data do evento, quando existir) |

### 3.5 Ordem e eventos fora de ordem

- Notificações podem chegar repetidas (entrega "ao menos uma vez") e fora de ordem.
- **Reembolso e chargeback são terminais para aquela compra:** um "aprovado" que chegue depois (reenvio atrasado) **não reativa** a matrícula.
- Antes de aplicar um evento, o processador consulta o histórico de eventos da **mesma compra** (`compra_ref`, seção 4).
- Em plataformas cuja notificação traz só um identificador (Asaas, Mercado Pago), o processador **consulta o estado atual** na API da plataforma e aplica esse estado, em vez de confiar na ordem de chegada.

### 3.6 Tolerância a mudanças

- Campos novos no corpo **nunca** podem causar erro. O processador lê o que precisa e ignora o resto (o Asaas avisa que campos novos aparecem sem aviso e que exceções do lado do receptor podem interromper a fila).
- Valores desconhecidos de status viram `ignorado` com alerta, não erro.

### 3.7 Segurança do endpoint

| Medida | Detalhe |
|---|---|
| Comparação de segredos | Sempre em **tempo constante** |
| Segredos | Criptografados; nunca devolvidos depois de salvos (RN-PAY-05) |
| Tamanho máximo do corpo | Limite definido por plataforma (valor inicial proposto: 1 MB) |
| Taxa | Limite por integração |
| Lista de IPs | **Opcional**, por integração, quando a plataforma publica seus IPs (o Asaas publica) |
| Registro | Corpo bruto **criptografado** e com retenção definida; logs da aplicação **nunca** com CPF |
| Reprocessamento | Evento com erro pode ser reprocessado por parceiro ou operador (FS-PAY-003) |

---

## 4. Evento interno padrão

Todo adaptador entrega ao módulo de Matrículas **sempre** este formato. O restante do sistema não conhece nenhuma plataforma.

```json
{
  "integracao_id": 12,
  "id_externo": "…",
  "tipo": "compra_aprovada",
  "ocorrido_em": "2026-10-06T18:30:00Z",
  "compra_ref": "…",
  "assinatura_ref": null,
  "produto_externo_id": "…",
  "comprador": {
    "nome": "…",
    "email": "…",
    "cpf": null,
    "telefone": null
  },
  "periodo_pago_ate": null
}
```

- `cpf` é **nulo** quando a plataforma não informa. O fluxo de coleta posterior está em FS-STU-004 e FS-STU-005.
- `compra_ref` identifica a compra na origem; `assinatura_ref`, a assinatura (quando houver).
- `periodo_pago_ate` só é preenchido se a plataforma informar até quando o acesso foi pago.
- O corpo bruto original fica em `eventos_pagamento.payload_bruto`; o evento padrão **não** guarda o CPF fora dali, só o repassa ao módulo de Matrículas.

### 4.1 Tipos de evento interno

| Tipo | Significado |
|---|---|
| `compra_aprovada` | Pagamento confirmado |
| `compra_reembolsada` | Dinheiro devolvido |
| `chargeback` | Contestação no cartão |
| `compra_cancelada` | Compra cancelada |
| `compra_recusada` | Pagamento recusado |
| `assinatura_renovada` | Nova cobrança de assinatura paga |
| `assinatura_atrasada` | Cobrança de assinatura atrasada |
| `assinatura_cancelada` | Assinatura encerrada |
| `disputa_aberta` | Disputa iniciada, sem decisão ainda |
| `informativo` | Evento sem efeito no acesso (boleto/Pix gerado, carrinho abandonado, etc.) |

### 4.2 O que cada tipo faz

| Tipo | Efeito na matrícula (regra proposta) |
|---|---|
| `compra_aprovada` | Cria a matrícula (origem `nativo` ou `generico`) ou **reativa** a existente. Ativa para estudo; espelhamento no painel conforme o CPF |
| `compra_reembolsada`, `chargeback` | **Suspende** (BR-PAY-002). Progresso preservado. Terminal para aquela compra |
| `compra_cancelada` | Se a matrícula ainda estava `pendente`, vai para `cancelada`; se estava `ativa` por aquela compra, **suspende** |
| `compra_recusada` | Registra e **ignora** (sem matrícula) |
| `assinatura_renovada` | Mantém ativa e estende a expiração, se `periodo_pago_ate` vier |
| `assinatura_atrasada` | **Só registra e avisa o parceiro**; não suspende na v1 |
| `assinatura_cancelada` | **Decisão em aberto** (seção 12): suspender na hora ou manter até o fim do período já pago |
| `disputa_aberta` | Registra e alerta o parceiro; **não** suspende na v1 |
| `informativo` | Registra e ignora |

---

## 5. Levantamento por plataforma

Cada plataforma usa a mesma ficha: configuração, autenticação, identificador, eventos, CPF, entrega e pontos de atenção.

### 5.1 Hotmart · **Parcial**

A página oficial de documentação bloqueou o acesso automático. O levantamento veio de um catálogo de terceiros que cita as páginas oficiais, com data de coleta de 04/08/2026.

| Item | O que se sabe |
|---|---|
| **Configuração** | Pelo painel, **por produto**: escolher produto, eventos e endereço de destino. Há duas versões de formato (`1.0.0`, legada, e `2.0.0`, atual); a versão é escolhida na criação e vem em todo envio. **Usar a 2.0.0** |
| **Autenticação** | Token compartilhado por conta (`hottok`), enviado no cabeçalho `X-HOTMART-HOTTOK` e também no corpo. **Sem assinatura do corpo e sem carimbo de tempo** (sem proteção contra repetição). Trocar o token exige falar com o suporte da Hotmart |
| **Envelope** | `id` (identificador único do evento), `creation_date` (milissegundos), `event`, `version`, `data`, `hottok` |
| **Identificador do evento** | `id` do envelope |
| **Eventos de compra** | `PURCHASE_APPROVED`, `PURCHASE_COMPLETE` (fim da garantia), `PURCHASE_CANCELED`, `PURCHASE_BILLET_PRINTED`, `PURCHASE_PROTEST` (disputa), `PURCHASE_REFUNDED`, `PURCHASE_CHARGEBACK`, `PURCHASE_EXPIRED`, `PURCHASE_DELAYED`, `PURCHASE_OUT_OF_SHOPPING_CART` |
| **Eventos de assinatura** | `SUBSCRIPTION_CANCELLATION`, `SWITCH_PLAN`, `UPDATE_SUBSCRIPTION_CHARGE_DATE` |
| **Status no corpo** | `APPROVED`, `COMPLETE`, `CANCELLED`, `CHARGEBACK`, `REFUNDED`, `PARTIALLY_REFUNDED`, `DISPUTE`, `EXPIRED`, `OVERDUE`, `WAITING_PAYMENT`, entre outros |
| **CPF** | A 2.0.0 traz dados do comprador, mas **o nome do campo do documento não foi confirmado** |
| **Entrega** | Códigos de resposta documentados: 2XX aceito; 400, 401, 404, 408, 5XX e `-1` (conexão encerrada) como falhas. **Política de reenvio não documentada.** Histórico de entregas de 60 dias na tela de configuração |
| **Atenção** | Sem HMAC, a segurança depende do token e de HTTPS. Compare em tempo constante e considere lista de IPs, se a Hotmart publicar |

**Mapeamento:** `PURCHASE_APPROVED` → `compra_aprovada`; `PURCHASE_REFUNDED` → `compra_reembolsada`; `PURCHASE_CHARGEBACK` → `chargeback`; `PURCHASE_CANCELED` → `compra_cancelada`; `PURCHASE_PROTEST` → `disputa_aberta`; `SUBSCRIPTION_CANCELLATION` → `assinatura_cancelada`; `PURCHASE_DELAYED` → `assinatura_atrasada` (se for de assinatura); `PURCHASE_COMPLETE`, `PURCHASE_BILLET_PRINTED`, `PURCHASE_EXPIRED`, `PURCHASE_OUT_OF_SHOPPING_CART`, `SWITCH_PLAN`, `UPDATE_SUBSCRIPTION_CHARGE_DATE` → `informativo`.

**A validar com evento real:** campo do CPF; campo do identificador da compra e do produto; como distinguir compra avulsa de cobrança de assinatura.

### 5.2 Kiwify · **Parcial**

**Atenção a uma armadilha:** a documentação da Kiwify que aparece primeiro nas buscas, com assinatura **Ed25519**, é do produto **bancário** da Kiwify (transferências e Pix). **Não se aplica às vendas de curso.** Não usar.

| Item | O que se sabe |
|---|---|
| **Configuração** | Pelo painel ou por **API** (`POST https://public-api.kiwify.com/v1/webhooks`, com token OAuth e o identificador da conta). O webhook tem nome, endereço, produtos (`all` ou um) e uma lista de gatilhos |
| **Gatilhos de venda (oficial)** | `boleto_gerado`, `pix_gerado`, `carrinho_abandonado`, `compra_recusada`, `compra_aprovada`, `compra_reembolsada`, `chargeback`, `subscription_canceled`, `subscription_late`, `subscription_renewed` |
| **Autenticação** | A configuração tem um campo **`token`** definido pelo vendedor. **Como esse token chega ao receptor não foi confirmado em fonte oficial.** Um guia de terceiros descreve uma assinatura **HMAC-SHA1** enviada no parâmetro `signature` da URL, com o nome do evento em `body.event` (valores como `order_approved`, `order_refunded`). **Tratar como hipótese a validar** |
| **Identificador do evento** | **Não confirmado.** Derivar do identificador do pedido + tipo de evento |
| **CPF** | Não confirmado |
| **Entrega** | Não confirmada |

**Mapeamento previsto:** `compra_aprovada` → `compra_aprovada`; `compra_reembolsada` → `compra_reembolsada`; `chargeback` → `chargeback`; `compra_recusada` → `compra_recusada`; `subscription_renewed` → `assinatura_renovada`; `subscription_late` → `assinatura_atrasada`; `subscription_canceled` → `assinatura_cancelada`; `boleto_gerado`, `pix_gerado`, `carrinho_abandonado` → `informativo`.

**A validar com evento real:** formato do corpo, onde vai o token ou a assinatura, o nome do evento no corpo, campo do CPF, identificador do pedido e política de reenvio.

### 5.3 Eduzz · **Baixa**

A documentação oficial nova fica em `developers.eduzz.com`, mas **não foi possível abri-la** nesta pesquisa. Dados vindos de guias de integração de terceiros.

| Item | O que se sabe |
|---|---|
| **Configuração** | Na plataforma (Órbita): **Avançado → Webhooks → Novo webhook**. Escolhe-se o **tipo** (Fatura ou Abandono de carrinho), os produtos, o formato de envio (JSON) e a URL, com botão de validar a URL |
| **Eventos** | Nomes no padrão `myeduzz.*`, por exemplo `myeduzz.invoice_paid`, `myeduzz.invoice_refunded`. Terceiros listam também: contrato criado, contrato atualizado, fatura cancelada, fatura em negociação, fatura aguardando pagamento, comissões processadas |
| **Autenticação** | O repositório oficial **obsoleto** menciona um campo `origin` ("chave de origem", token de segurança). Para a versão nova, um exemplo de terceiros usa cabeçalho de assinatura HMAC-SHA256, **sem confirmação oficial** |
| **Identificador, CPF, entrega** | **Não confirmados** |

**Mapeamento previsto:** `myeduzz.invoice_paid` → `compra_aprovada`; `myeduzz.invoice_refunded` → `compra_reembolsada`; fatura cancelada → `compra_cancelada`; demais → `informativo`.

**A validar com evento real:** tudo. Começar lendo a documentação oficial nova.

### 5.4 Monetizze · **Baixa**

Fontes: guias de integração de terceiros e um exemplo de código **de 2017**. Os campos podem ter mudado.

| Item | O que se sabe |
|---|---|
| **Configuração** | **Ferramentas → Postback**: tipo "Postback (server to server)", produtos, vendas, eventos, formato (JSON disponível) e URL |
| **Autenticação** | **Chave Única** exibida no painel; no exemplo antigo ela chega **dentro do corpo** (`chave_unica`) e o receptor compara com a sua. **Não é assinatura do corpo**: é um segredo compartilhado em texto |
| **Campos (exemplo antigo)** | `venda.codigo`, `venda.status`, `venda.valor`, `venda.dataInicio`, `venda.dataFinalizada` |
| **Status (exemplo antigo)** | Aguardando pagamento, Finalizada, Cancelada, Devolvida, Bloqueada, Completa |
| **Identificador, CPF, entrega** | **Não confirmados.** Derivar o identificador do código da venda + status |

**Mapeamento previsto:** Finalizada → `compra_aprovada`; Devolvida → `compra_reembolsada`; Cancelada → `compra_cancelada`; Aguardando pagamento e Bloqueada → `informativo`; Completa → `informativo` **[A CONFIRMAR o significado]**.

### 5.5 Greenn · **Parcial**

Fonte oficial: central de ajuda da Greenn. A página tem **data de última atualização de 09/11/2021**, então pode estar defasada.

| Item | O que se sabe |
|---|---|
| **Dois tipos de webhook** | **Vendas** e **contratos** (assinaturas) |
| **Vendas** | `type = "sale"`, evento `saleUpdated`, com `oldStatus` (created, paid, waiting_payment) e `currentStatus` (paid, refused, refunded, chargedback, waiting_payment) |
| **Contratos** | `type = "contract"`, com status (paid, trialing, pending_payment, unpaid, canceled) e datas como `contract.start_date` e `contract.current_period_end` |
| **Autenticação** | **Não descrita** na página lida |
| **Identificador, CPF, entrega** | **Não confirmados.** O modelo é baseado em **mudança de status**: derivar o identificador de (id da venda + `currentStatus`) |

**Mapeamento previsto:** `paid` → `compra_aprovada`; `refunded` → `compra_reembolsada`; `chargedback` → `chargeback`; `refused` → `compra_recusada`; `waiting_payment` → `informativo`; contrato `paid` → `assinatura_renovada`; `unpaid` → `assinatura_atrasada`; `canceled` → `assinatura_cancelada`.

### 5.6 Braip · **Baixa**

| Item | O que se sabe |
|---|---|
| **Configuração** | **Ferramentas → Postback → Configurações → Nova configuração**: nome, URL de retorno, produto, **eventos** e **eventos de assinatura**, método HTTP POST |
| **Autenticação** | **Chave única** exibida na aba de documentação do Postback, usada para verificar que a requisição veio da Braip. **Onde ela vai na requisição não foi confirmado** |
| **Eventos, campos, identificador, CPF, entrega** | **Não confirmados** |

**A validar com evento real:** tudo. Começar pela aba **Documentação** do Postback dentro de uma conta Braip.

### 5.7 Asaas · **Confirmada**

A melhor documentada do conjunto. **O Asaas já é usado no funil de certificados**, o que reduz o risco de integração.

| Item | O que se sabe |
|---|---|
| **Configuração** | Pelo painel (**Integrações → Webhooks**) ou **por API** (`POST /v3/webhooks`), para conta principal **e subcontas**. Até **10 webhooks por conta** |
| **Autenticação** | Campo `authToken` definido por quem configura, enviado em **todas** as notificações no cabeçalho **`asaas-access-token`**. O token **não pode ser uma chave de API** do Asaas e só é mostrado na criação. Não há Bearer. Há **lista de IPs oficiais** para firewall |
| **Identificador do evento** | Campo **`id`** (ex.: `evt_…`). **Se o mesmo evento for reenviado, o `id` é o mesmo**: é a chave de idempotência |
| **Entrega** | **Ao menos uma vez.** Só **HTTP 200** conta como sucesso; qualquer outro código é falha. Espera até **10 segundos**. Reenvios com intervalo crescente; **após 15 falhas consecutivas a fila é interrompida** (novos eventos continuam sendo gerados, mas não enviados). Eventos ficam guardados por **14 dias**; passado isso, são **excluídos**. Ao reativar a fila, os pendentes saem **em ordem cronológica** |
| **Eventos de cobrança** | `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED` e outros do grupo de cobranças (lista completa na página "Eventos de Webhooks" do Asaas; **conferir** os de reembolso e contestação) |
| **Corpo do evento** | `{ id, event, dateCreated, payment: { object, id, customer, externalReference, … } }` |
| **CPF** | **Não vem no webhook.** O campo `customer` traz só o **identificador do cliente** (`cus_…`). O CPF exige **consultar o cliente na API do Asaas** com a credencial do parceiro |
| **Campo útil** | `externalReference`: permite levar uma referência própria na cobrança |

**Consequências para o AVA:**

1. O adaptador do Asaas guarda **duas credenciais** do parceiro: o token do webhook **e** a chave de API (para consultar cliente e cobrança). Ambas criptografadas.
2. Falhar repetidamente **pausa a fila do parceiro**. Por isso a política da seção 3.3 (200 para o que for ignorável) é obrigatória, e um alerta deve avisar o parceiro se a fila ficar interrompida.
3. O onboarding pode ser **automatizado**: o AVA cria o webhook via API com o token gerado por ele mesmo.

**Mapeamento:** `PAYMENT_CONFIRMED`/`PAYMENT_RECEIVED` → `compra_aprovada` (tratar os dois sem duplicar a matrícula, pois podem chegar para a mesma cobrança); eventos de estorno → `compra_reembolsada`; de contestação → `chargeback` ou `disputa_aberta` **[A CONFIRMAR a lista]**; os demais → `informativo`.

### 5.8 Mercado Pago · **Confirmada**

| Item | O que se sabe |
|---|---|
| **Configuração** | **Suas integrações** (por aplicação) ou **por pagamento**, informando a URL ao criar o pagamento. A URL definida no pagamento tem prioridade |
| **Corpo da notificação** | **Só identificadores**: `id`, `type` (ex.: `payment`), `action` (ex.: `payment.created`, `payment.updated`), `api_version`, `data.id`, `date_created`, `live_mode`, `user_id` |
| **Tipos de notificação** | `payment`, `subscription_authorized_payment` (cobrança recorrente de assinatura), entre outros |
| **Autenticação** | Cabeçalho **`x-signature`** no formato `ts=…,v1=…`, mais o cabeçalho `x-request-id`. Valida-se com **HMAC-SHA256 (hexadecimal)**, usando a **assinatura secreta** da integração como chave, sobre o texto: `id:{data.id};request-id:{x-request-id};ts:{ts};`. O `data.id` vem da **URL** (parâmetro) e, se for alfanumérico, deve ir em **minúsculas**. Se algum valor não existir na notificação, **remove-se aquele par** do texto antes de calcular. O `ts` está em milissegundos |
| **Identificador do evento** | O `id` da notificação **não é confiável como único** (o mesmo pagamento gera várias notificações: criado, atualizado). Tratar por **pagamento**: consultar o estado atual e aplicar de forma idempotente |
| **CPF** | **Não vem na notificação.** É preciso **consultar o pagamento na API** com a credencial do parceiro; o documento do pagador deve estar nesse retorno **[A CONFIRMAR o campo]** |
| **Entrega e reenvio** | **Não confirmados** nesta leitura |

**Consequências para o AVA:**

1. O adaptador guarda a **credencial de API** do parceiro (e a assinatura secreta do webhook), criptografadas.
2. O fluxo é sempre: notificação → validar assinatura → gravar bruto → fila → **consultar o pagamento** → traduzir.
3. O estado do pagamento decide o evento interno (aprovado, reembolsado, contestado) **[A CONFIRMAR os nomes de status e como estorno e contestação aparecem]**.

### 5.9 PagBank · **Confirmada, com ressalvas**

A documentação existe, mas a parte de autenticidade é **inconsistente** (inclusive entre produtos do próprio PagBank).

| Item | O que se sabe |
|---|---|
| **Configuração** | **Por pedido ou checkout**: o campo opcional **`notification_urls`** vai no momento de **criar** o pedido pela API. Não é uma configuração geral de conta. **Links de pagamento criados direto no painel do PagBank podem não permitir** definir essa URL **[A CONFIRMAR]** |
| **Conteúdo** | Por padrão, igual à resposta síncrona da API de pedidos |
| **Status de cobrança** | `PAID`, `IN_ANALYSIS`, `DECLINED`, `CANCELED`, `WAITING` |
| **Assinaturas (pagamentos recorrentes)** | Eventos como `subscription.activated`, `subscription.suspended`, `subscription.recurrence`, `subscription.canceled`, `subscription.expired` |
| **Autenticação, método 1 (antigo)** | Cabeçalho `x-authenticity-token`: SHA-256 sobre `token + "-" + payload` |
| **Autenticação, método 2 (novo)** | Cabeçalho `x-payload-signature`, assinatura **ECDSA**; a chave pública vem de um endpoint de chaves públicas, consultado com o token da conta. Pode haver **mais de uma assinatura** no cabeçalho; basta uma ser válida. A própria página pede conferir a URL de produção desse endpoint |
| **Problemas relatados** | Desenvolvedores na comunidade do PagBank relatam divergência de assinatura ("Signature mismatch") e que o formato dos webhooks de **pagamentos recorrentes** não corresponde à documentação pública do método 1 |

**Recomendação:** **não construir adaptador nativo do PagBank na v1.** Atender os parceiros que usam PagBank pelo **nível 3 (matrícula manual, importação, código de acesso)** ou, se o parceiro conseguir enviar a URL, pelo **nível 2 (genérico)** sem depender da validação de assinatura. Revisitar depois de testes em ambiente de homologação do PagBank.

### 5.10 Quadro comparativo

| Plataforma | Confiança | Autenticação | Identificador do evento | CPF no evento | Reenvio |
|---|---|---|---|---|---|
| Hotmart | Parcial | Token fixo em cabeçalho (+ corpo); sem HMAC | `id` do envelope | A verificar | Não documentado |
| Kiwify | Parcial | Token do vendedor; entrega **a confirmar** (hipótese: HMAC-SHA1 na URL) | Derivar | A verificar | A verificar |
| Eduzz | Baixa | A confirmar (antigo: chave `origin`) | A verificar | A verificar | A verificar |
| Monetizze | Baixa | Chave única no corpo (segredo compartilhado) | Derivar | A verificar | A verificar |
| Greenn | Parcial | Não descrita | Derivar (venda + status) | A verificar | A verificar |
| Braip | Baixa | Chave única (local a confirmar) | A verificar | A verificar | A verificar |
| **Asaas** | **Confirmada** | Token em `asaas-access-token` | `id` do evento | **Não: consultar cliente na API** | Sim; 15 falhas pausam a fila; guarda 14 dias |
| **Mercado Pago** | **Confirmada** | HMAC-SHA256 em `x-signature` | Por pagamento (consultar estado) | **Não: consultar pagamento na API** | A confirmar |
| PagBank | Conf. c/ ressalvas | Dois métodos documentados, inconsistentes | A verificar | A verificar | A verificar |

---

## 6. Ordem de implementação proposta

Como não se sabe quais plataformas os primeiros parceiros usam, a ordem proposta se baseia em **qualidade da documentação e esforço**, não em participação de mercado.

| Etapa | Entrega | Motivo |
|---|---|---|
| **0** | **Nível 3 (manual, importação, código)** e **nível 2 (webhook genérico)** | Cobrem qualquer parceiro desde o primeiro dia, inclusive PagBank |
| **1** | **Asaas** | Documentação oficial completa, e o ecossistema já o utiliza |
| **2** | **Hotmart** | Eventos e envelope bem definidos, mesmo com a leitura oficial pendente |
| **3** | **Mercado Pago** | Documentação oficial boa, mas exige consulta à API |
| **4** | **Kiwify** | Gatilhos oficiais claros; falta confirmar a entrega do token |
| **5** | **Eduzz, Monetizze, Greenn, Braip** | Dependem de ler documentação oficial dentro de uma conta de cada plataforma |
| **Depois** | **PagBank nativo** | Após testes de homologação; ver seção 5.9 |

Sugestão prática: **perguntar aos parceiros o que usam** antes de decidir as etapas 2 a 5. Isso pode reordenar tudo.

---

## 7. Contrato AVA ↔ painel de matrículas

Esta seção traduz a seção 9 do UNY-DOC-004 em contrato. É uma **proposta**: o painel ainda não tem esses endpoints e precisará implementá-los.

### 7.1 Autenticação das chamadas

Toda requisição, nos dois sentidos, leva:

| Cabeçalho | Conteúdo |
|---|---|
| `X-Uny-Message-Id` | UUID da mensagem (idempotência; igual a `painel_mensagens.msg_uuid`) |
| `X-Uny-Timestamp` | Milissegundos desde 1970 |
| `X-Uny-Signature` | HMAC-SHA256, em hexadecimal, calculado com o segredo compartilhado sobre o texto `{timestamp}.{message_id}.{corpo_bruto}` |

Regras do receptor:

- Recusar (401) se a assinatura não bater, comparando em tempo constante
- Recusar se o carimbo de tempo estiver a mais de **5 minutos** do relógio local (proteção contra repetição)
- Uma mesma `X-Uny-Message-Id` já processada devolve **a mesma resposta** da primeira vez, sem reprocessar
- O segredo é **por ambiente** (homologação e produção diferentes) e permite **dois segredos válidos ao mesmo tempo** durante a troca

### 7.2 Endpoints que o painel precisa expor (proposta)

| Método e caminho | Mensagem do UNY-DOC-004 | Função |
|---|---|---|
| `POST /api/ava/matriculas` | Espelhar matrícula | Registra a matrícula e devolve o identificador do painel |
| `PATCH /api/ava/matriculas/{painel_matricula_id}` | Atualizar matrícula | Informa mudança de estado |
| `POST /api/ava/matriculas/{painel_matricula_id}/liberacao-certificado` | Pedir liberação | Pergunta se o certificado pode sair e, se puder, devolve hash e QR Code |
| `POST /api/ava/matriculas/{painel_matricula_id}/emissao` | Confirmar emissão | Registra a emissão |
| `POST /api/ava/matriculas/{painel_matricula_id}/revogacao` | Informar revogação | Registra a revogação |
| `GET /api/ava/polos/{polo_id}/situacao` | Consulta de situação | Devolve a situação atual do parceiro (opção B do UNY-DOC-004, seção 9.7) |

### 7.3 Endpoint que o AVA expõe ao painel (P1)

| Método e caminho | Função |
|---|---|
| `POST /bridge/painel/matriculas` | Matrícula criada ou aprovada no painel (origem `painel`) |

### 7.4 Exemplos

**Espelhar matrícula** (único ponto em que o CPF completo trafega):

```json
POST /api/ava/matriculas
{
  "polo_id": "…",
  "matricula": {
    "uuid": "…",
    "origem": "nativo",
    "estado": "ativa",
    "criada_em": "2026-10-06T18:30:00Z",
    "curso": { "uuid": "…", "titulo": "…", "carga_horaria_min": 600 }
  },
  "aluno": {
    "uuid": "…",
    "nome": "…",
    "email": "…",
    "cpf": "<11 dígitos>"
  }
}
```

Resposta de sucesso:

```json
200 { "painel_matricula_id": "…", "situacao": "registrada" }
```

**Pedir liberação de certificado:**

```json
POST /api/ava/matriculas/{id}/liberacao-certificado
{ "concluida_em": "2026-12-01T12:00:00Z", "carga_horaria_min": 600 }
```

Resposta: **habilitado**

```json
200 { "habilitado": true, "hash": "…", "url_validacao": "https://…" }
```

Resposta: **retido** (é uma resposta de negócio, não um erro, por isso **HTTP 200**)

```json
200 { "habilitado": false, "motivo": "parceiro_fora_de_dia" }
```

Valores de `motivo`: `parceiro_fora_de_dia`, `matricula_nao_regularizada`. Qualquer valor desconhecido o AVA trata como `aguardando_painel`.

### 7.5 Erros

| HTTP | Código | Significado | O que o AVA faz |
|---|---|---|---|
| 401 | `assinatura_invalida` | Assinatura ou carimbo de tempo inválidos | Alerta ao operador; **não reenvia** |
| 404 | `polo_desconhecido` | Parceiro sem vínculo no painel | Marca `erro` e alerta ao operador |
| 409 | `matricula_duplicada` | O painel recusou CPF + curso repetidos | Registra a divergência; devolve o identificador existente, se vier **[A CONFIRMAR o comportamento do painel]** |
| 422 | `cpf_invalido`, `campo_obrigatorio` | Dados rejeitados | Matrícula volta a `aguardando_cpf` ou `erro`, com motivo para operador |
| 5xx / tempo esgotado | — | Falha temporária | Reenvio com intervalo crescente |

---

## 8. API interna do AVA

Todas sob `/api/v1`, em JSON, resolvendo o parceiro pelo host. As listas abaixo são o **catálogo**; o detalhe de campos sai na implementação e vira documentação gerada (RNF-009).

### 8.1 Convenções

| Tema | Regra |
|---|---|
| Versão | Prefixo `/api/v1` |
| Autenticação | Sessão por cookie restrito ao host + proteção CSRF em toda gravação |
| Paginação | `?pagina=1&por_pagina=25` (máximo 100); resposta com `total` |
| Filtros | Parâmetros nomeados (ex.: `estado=ativa`) |
| Datas | ISO 8601 em UTC |
| Identificadores | Sempre `uuid`, nunca o `id` numérico |
| Idempotência | Criação de matrícula manual, importação e resgate de código aceitam o cabeçalho `Idempotency-Key` |
| Limite de taxa | Por usuário e por parceiro; resposta `429` com `Retry-After` |

### 8.2 Formato de erro

```json
{ "erro": { "codigo": "matricula_nao_encontrada", "mensagem": "…", "detalhes": {} } }
```

| HTTP | Uso |
|---|---|
| 400 | Requisição malformada |
| 401 | Sem sessão |
| 403 | Sem permissão (FS-PER-001) |
| 404 | Não existe **ou pertence a outro parceiro** (nunca revelar a diferença) |
| 409 | Conflito de estado (ex.: matrícula já ativa) |
| 422 | Dados inválidos (inclui CPF inválido) |
| 429 | Limite de taxa |
| 500 | Erro interno (sem detalhes técnicos na resposta) |

### 8.3 API do aluno

| Grupo | Endpoints principais | Requisitos |
|---|---|---|
| Acesso | `POST /aluno/acesso/link-magico`, `POST /aluno/acesso/entrar`, `POST /aluno/acesso/sair`, `POST /aluno/acesso/redefinir-senha` | FS-STU-001 |
| Cadastro | `GET /aluno/perfil`, `PATCH /aluno/perfil`, `PUT /aluno/perfil/cpf` | FS-STU-004, 005 |
| Home | `GET /aluno/home` (banner, vitrines, continuar assistindo) | FS-STU-010, 011 |
| Catálogo | `GET /aluno/cursos`, `GET /aluno/cursos/{uuid}`, `GET /aluno/busca?q=` | FS-STU-020, 030 |
| Curso | `GET /aluno/cursos/{uuid}/conteudo`, `POST /aluno/cursos/{uuid}/matricula-gratuita` | FS-STU-030, 033 |
| Aula | `GET /aluno/aulas/{uuid}`, `PUT /aluno/aulas/{uuid}/progresso`, `POST /aluno/aulas/{uuid}/concluir` | FS-STU-040 a 043 |
| Minha área | `GET /aluno/meus-cursos`, `GET/PUT/DELETE /aluno/favoritos/{curso}`, `GET /aluno/certificados` | FS-STU-050 a 052 |
| Código de acesso | `POST /aluno/codigos/resgatar` | FS-ADM-034 |
| Privacidade | `POST /aluno/privacidade/exportacao`, `POST /aluno/privacidade/exclusao` | FS-STU-051 |
| Progresso | `PUT/DELETE /aluno/aulas/{uuid}/visto`, `PUT/DELETE /aluno/aulas/{uuid}/reacao` | FS-STU-045, 046 |
| Comentários | `GET/POST /aluno/aulas/{uuid}/comentarios`, `PATCH/DELETE /aluno/comentarios/{uuid}` | FS-STU-049 |
| Provas | `GET /aluno/avaliacoes/{uuid}`, `POST /aluno/provas/{uuid}/tentativas`, `PUT /aluno/tentativas/{uuid}/respostas`, `POST /aluno/tentativas/{uuid}/enviar`, `GET /aluno/tentativas/{uuid}` | FS-AVA-001 a 005 |
| Tarefas | `GET /aluno/tarefas/{uuid}/entregas`, `POST /aluno/tarefas/{uuid}/entregas` (envio de arquivos em várias partes) | FS-AVA-006, 007 |

### 8.4 API do parceiro

| Grupo | Endpoints principais | Requisitos |
|---|---|---|
| Painel | `GET /parceiro/resumo` | FS-ADM-001 |
| Cursos | `GET/POST /parceiro/cursos`, `GET/PATCH /parceiro/cursos/{uuid}`, `POST …/publicar`, `POST …/arquivar`, módulos e aulas com `…/ordem` | FS-ADM-010 a 013 |
| Alunos | `GET /parceiro/alunos`, `GET /parceiro/alunos/{uuid}`, `GET …/cpf` (completo, **com auditoria**) | FS-ADM-020 a 022 |
| Matrículas | `POST /parceiro/matriculas` (manual), `PATCH /parceiro/matriculas/{uuid}` (suspender, reativar), `POST /parceiro/importacoes`, `POST /parceiro/importacoes/{uuid}/confirmar`, `GET/POST /parceiro/codigos-acesso` | FS-ADM-030 a 034 |
| Visual | `GET/PUT /parceiro/visual/tema/rascunho`, `GET/PUT /parceiro/visual/home/rascunho`, `POST /parceiro/visual/publicar`, `GET/PUT /parceiro/paginas/{slug}` | FS-ADM-040 a 052 |
| Pagamentos | `GET/POST /parceiro/integracoes`, `PATCH …/{uuid}`, `POST …/{uuid}/testar`, `GET/PUT …/{uuid}/produtos`, `GET /parceiro/eventos-pagamento`, `POST /parceiro/eventos-pagamento/{uuid}/reprocessar`, `GET /parceiro/conciliacao` | FS-PAY-001 a 006 |
| Certificados | `GET/POST /parceiro/modelos-certificado`, `GET /parceiro/certificados`, `GET /parceiro/certificados/retidos`, `POST …/{uuid}/revogar` | FS-CER-010 a 017 |
| Equipe | `GET/POST /parceiro/equipe`, `PATCH/DELETE /parceiro/equipe/{uuid}` | FS-ADM-060 |
| Relatórios | `GET /parceiro/relatorios/{tipo}` (com `?formato=planilha`) | FS-ADM-070 |
| Ambiente | `GET/PUT /parceiro/ambiente`, `POST /parceiro/dominios`, `GET /parceiro/dominios/{uuid}/status` | FS-ADM-080 |
| Avaliações | `GET/PUT /parceiro/aulas/{uuid}/avaliacao`, `GET/POST /parceiro/avaliacoes/{uuid}/questoes`, `PATCH/DELETE …/questoes/{uuid}`, `POST /parceiro/avaliacoes/{uuid}/pre-visualizar` | FS-AVA-010 a 012, 017, 018 |
| Correção | `GET /parceiro/correcoes` (fila), `GET /parceiro/entregas/{uuid}`, `POST /parceiro/entregas/{uuid}/corrigir`, `PATCH /parceiro/entregas/{uuid}` (alterar, exige motivo) | FS-AVA-013 a 015 |
| Resultados | `GET /parceiro/avaliacoes/{uuid}/resultados`, `GET /parceiro/avaliacoes/{uuid}/notas?formato=planilha` | FS-AVA-016 |
| Comentários | `GET /parceiro/comentarios`, `POST /parceiro/comentarios/{uuid}/responder`, `POST …/ocultar`, `PUT /parceiro/aulas/{uuid}/comentarios-ativos` | FS-ADM-090 a 092 |

### 8.5 API do operador

| Grupo | Endpoints principais | Requisitos |
|---|---|---|
| Parceiros | `GET/POST /operador/parceiros`, `PATCH …/{uuid}` (suspender, reativar), `PUT …/{uuid}/vinculo-painel` | FS-OPR-001, 002 |
| Planos e uso | `GET/POST /operador/planos`, `GET /operador/uso` | BR-OPR-002, 003 |
| Integrações | `GET /operador/integracoes/falhas` | BR-OPR-005 |
| Ponte com o painel | `GET /operador/painel/mensagens`, `GET /operador/painel/divergencias`, `POST …/reenviar` | BR-INT-006 |
| Certificados | `POST /operador/certificados/{uuid}/liberar`, `POST …/reter` | FS-CER-018 |
| Auditoria e suporte | `GET /operador/auditoria`, `POST /operador/suporte/entrar` (exige motivo) | FS-OPR-003 |

---

## 9. Adaptadores: contrato técnico

Todo adaptador implementa a **mesma interface**, para que adicionar uma plataforma não mude o restante do sistema (BR-PAY-013):

| Operação | Função |
|---|---|
| `autenticar(requisicao, integracao)` | Valida a autenticidade; devolve verdadeiro ou falso |
| `identificar(requisicao)` | Devolve o `id_externo` (ou o derivado) e o `compra_ref` |
| `traduzir(corpo, integracao)` | Devolve o evento interno padrão (seção 4) |
| `completar(evento, integracao)` | **Opcional.** Consulta a API da plataforma para completar dados (obrigatório em Asaas e Mercado Pago) |
| `configurar(integracao)` | **Opcional.** Cria o webhook por API quando a plataforma permite (Asaas, Kiwify) |
| `testar(integracao)` | Confirma a conexão (FS-PAY-001, passo 4) |

---

## 10. Webhook genérico (nível 2)

Para plataformas sem adaptador, o parceiro configura:

| Etapa | O que o parceiro faz |
|---|---|
| 1 | Copia o endereço do webhook do AVA e cola na plataforma |
| 2 | Escolhe **como o AVA valida**: cabeçalho com token fixo, parâmetro na URL, ou nenhum (com aviso de risco) |
| 3 | Cola um **exemplo de evento** recebido |
| 4 | **Mapeia os campos** apontando caminhos do exemplo: e-mail, nome, CPF (opcional), produto, identificador da compra, status |
| 5 | Define quais valores de status significam **aprovado**, **reembolsado**, **chargeback**, **cancelado** |
| 6 | Vincula produtos a cursos |

Limites do genérico na v1:

- Só valida **segredo fixo** (cabeçalho ou parâmetro). **Não** valida assinaturas calculadas (HMAC, ECDSA), que exigem adaptador próprio.
- Só trabalha com o que vem **no corpo** do evento; não consulta APIs externas.
- Sem identificador de evento estável, usa a derivação da seção 3.4.

---

## 11. Validação com evento real (obrigatória)

Definição de pronto de **cada adaptador**:

| # | Verificação |
|---|---|
| 1 | Capturar, no ambiente de teste ou sandbox da plataforma, **eventos reais** de: compra aprovada, reembolso, chargeback, cancelamento, recusa e (se houver) assinatura renovada, atrasada e cancelada |
| 2 | Guardar esses eventos como **exemplos de teste** (com dados fictícios) |
| 3 | Confirmar o **método de autenticação** com um evento válido e um com token ou assinatura errados |
| 4 | Confirmar **onde está o CPF**, ou que ele **não vem** |
| 5 | Confirmar o **identificador do evento** e que ele é **igual** em reenvios |
| 6 | Testar **evento duplicado** (não cria segunda matrícula) |
| 7 | Testar **fora de ordem** (aprovado depois de reembolsado não reativa) |
| 8 | Testar **produto não vinculado** (200 e `ignorado`) |
| 9 | Testar **campo novo no corpo** (não quebra) |
| 10 | Testar **tempo de resposta** abaixo do limite da plataforma |
| 11 | Registrar na documentação interna a **data da validação** e a versão do formato |

---

## 12. Decisões e verificações pendentes

| # | Item | Tipo | Impacto |
|---|---|---|---|
| 1 | Quais plataformas os **primeiros parceiros** usam (pode reordenar a seção 6) | Levantamento com parceiros | Cronograma |
| 2 | **Hotmart:** ler a documentação oficial dentro de uma conta e confirmar campos (CPF, produto, compra) | Verificação | Adaptador |
| 3 | **Kiwify:** confirmar como o token ou a assinatura das **vendas** chega ao receptor | Verificação | Adaptador |
| 4 | **Eduzz, Monetizze, Braip:** ler a documentação oficial e capturar eventos de teste | Verificação | Adaptadores |
| 5 | **Greenn:** confirmar autenticação e se o formato de 2021 continua válido | Verificação | Adaptador |
| 6 | **Asaas:** confirmar a lista completa de eventos de reembolso e contestação | Verificação | Mapeamento |
| 7 | **Mercado Pago:** confirmar o campo do documento do pagador e os status de estorno e contestação; política de reenvio | Verificação | Adaptador |
| 8 | **PagBank:** manter fora do nativo na v1 (proposta) | Decisão | Escopo |
| 9 | **Assinatura cancelada:** suspender na hora ou manter acesso até o fim do período pago | Decisão | Seção 4.2 |
| 10 | **Disputa aberta** (ex.: Hotmart `PURCHASE_PROTEST`): suspender ou só alertar (proposta: só alertar) | Decisão | Seção 4.2 |
| 11 | **Assinatura atrasada:** quanto tempo tolerar antes de suspender (proposta: não suspender na v1) | Decisão | Seção 4.2 |
| 12 | O painel aceita o contrato da seção 7 ou impõe o formato dele? Hoje o painel já faz algo parecido com outra integração? | Verificação com a equipe do painel | Seção 7 |
| 13 | **Lista de IPs:** usar quando a plataforma publicar (Asaas publica) | Decisão | Segurança |
| 14 | Limite de tamanho do corpo dos webhooks (proposta: 1 MB) | Decisão | Seção 3.7 |
| 15 | **Stripe e Pagar.me:** pesquisar numa próxima rodada, se algum parceiro usar | Pendência | Escopo |

---

## 13. Fontes consultadas

| Plataforma | Fonte | Tipo |
|---|---|---|
| Hotmart | Catálogo de eventos de webhook (api-evangelist, citando a documentação oficial; coleta de 04/08/2026) | Secundária |
| Kiwify | docs.kiwify.com.br, páginas de **criar e atualizar webhook** | Oficial |
| Kiwify | docs.kiwify.com.br, páginas de **webhooks do produto bancário** | Oficial, **fora do escopo de vendas** |
| Kiwify | Guia de terceiros sobre HMAC-SHA1 | Secundária |
| Eduzz | Repositório oficial `eduzz/webhook` (obsoleto) | Oficial, antigo |
| Eduzz | Guias de integração de terceiros (Voxuy, Ensinio, Zapier, Pluga) | Secundária |
| Monetizze | Guias de terceiros (Nemu, Pluga) e exemplo de código de 2017 | Secundária |
| Greenn | Central de ajuda da Greenn ("Documentação Webhook", 2021) | Oficial, antiga |
| Braip | Guia de terceiros (Nemu) | Secundária |
| Asaas | docs.asaas.com (webhooks, criação por API, idempotência, FAQ) | Oficial |
| Mercado Pago | mercadopago.com.br/developers (notificações e validação de origem) | Oficial |
| PagBank | developer.pagbank.com.br (webhooks, validação de autenticidade, comunidade) | Oficial, com inconsistências |

## 14. Próximo documento

**UNY-DOC-007 · Design System:** identidade visual do AVA e dos ambientes dos parceiros, componentes da experiência estilo streaming, tokens de tema que o parceiro pode personalizar e limites dessa personalização.
