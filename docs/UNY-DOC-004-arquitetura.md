# UNY-DOC-004 · Arquitetura Multi-Parceiro

**Projeto:** UnyEducation
**Versão:** 0.5 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Depende de:** UNY-DOC-001, UNY-DOC-002, UNY-DOC-003 · **Dados reais do servidor:** UNY-DOC-008

---

## 1. Como ler este documento

Este documento responde **como o sistema é organizado por dentro**: onde roda, como separa um parceiro do outro, como chega o pagamento, como conversa com o painel de matrículas e o que fica fora da VPS.

Ele não detalha tabelas (UNY-DOC-005), endpoints (UNY-DOC-006) nem a configuração final do servidor (UNY-DOC-008). Trechos de configuração aqui são **ilustrativos**.

Itens marcados com **[A CONFIRMAR]** dependem de uma decisão ou de uma verificação no servidor antes de virarem definitivos (lista na seção 15).

## 2. Princípios de arquitetura

1. **Um sistema, muitos parceiros.** Uma única aplicação atende todos os parceiros; o que muda é o contexto (tenant).
2. **Isolamento primeiro.** Nenhuma consulta, arquivo, cache ou sessão pode atravessar de um parceiro para outro.
3. **Acoplamento baixo com o painel.** AVA e painel conversam por interfaces autenticadas, não por tabelas compartilhadas.
4. **Pagamento como evento.** Tudo que vem de fora vira um evento interno padronizado antes de mexer em matrícula.
5. **Nada pesado na VPS.** Vídeo e outras cargas pesadas ficam fora do servidor que também roda os outros sistemas.
6. **Simples de operar.** Preferir poucas peças, bem conhecidas, a uma arquitetura sofisticada que ninguém consegue manter.
7. **Preparado para crescer.** Cada decisão de hoje registra o caminho de saída.

## 3. Visão geral

```text
                        Internet
                           │
        ┌──────────────────┼───────────────────────┐
        │                  │                       │
  ava-parceiroA.…    ava-parceiroB.…       dominio-do-parceiro.com
        │                  │                       │
        └──────────────────┼───────────────────────┘
                           ▼
                  ┌─────────────────┐
                  │      Nginx      │  SSL, roteamento por host
                  └────────┬────────┘
                           ▼
                  ┌─────────────────┐
                  │   PHP-FPM (AVA) │  pool próprio, usuário próprio
                  └────────┬────────┘
                           ▼
   ┌──────────────────────────────────────────────────────┐
   │                    Aplicação AVA                      │
   │  Resolução de tenant → Autenticação → Módulos         │
   │                                                       │
   │  Catálogo · Aprendizado · Matrículas · Pagamentos     │
   │  Certificados · Tema/Vitrine · Relatórios · Auditoria │
   │  Ponte com o painel · E-mails                         │
   └───────┬───────────────┬──────────────────┬───────────┘
           │               │                  │
           ▼               ▼                  ▼
     Banco do AVA    Storage por tenant   Fila de tarefas
      (MySQL)        (fora do webroot)    (processamento
                                           assíncrono)

   Fora da VPS:  Vídeo (serviço externo)  ·  E-mail transacional

   Integrações:
     Plataformas de venda ──webhook──▶ AVA
     Painel de matrículas ◀──API assinada──▶ AVA
```

## 4. Hospedagem e servidor

### 4.1 Decisão

O AVA roda na **mesma VPS Contabo** dos sistemas atuais (Ubuntu, Nginx, SSL via Certbot), como uma aplicação **separada** do painel.

### 4.2 Convivência com o que já existe

O painel hoje roda em **PHP 7.2-FPM**. O AVA não depende dessa versão: **roda em contêiner Docker na mesma VPS** (decisão de 08/10/2026, opção A2 do UNY-DOC-008), com migração futura para uma VPS própria. Desenho:

| Item | Painel (existente) | AVA (novo) |
|---|---|---|
| Versão do PHP | 7.2 (sem alteração) | 8.3 ou superior **dentro de um contêiner Docker**. O repositório do PHP para o Ubuntu 20.04 está vazio (medido em 08/10/2026), então o PHP 8 não pode ser instalado no sistema (UNY-DOC-008, seções 3 e 6) |
| Pool PHP-FPM | Atual | Próprio, **no contêiner**, com limite de processos e de memória do contêiner |
| Usuário do sistema | Atual | Próprio (`ava`), usado pelo contêiner; sem acesso às pastas do painel |
| Banco de dados | Banco atual do painel | **Banco novo e separado**, com usuário próprio |
| Pasta | `/var/www/html/painel` | Pasta própria (ex.: `/var/www/ava`), fora de `/var/www/html` |
| Vhosts | Existentes | Novos arquivos de host, sem mexer nos existentes; o Nginx do servidor repassa ao PHP-FPM do contêiner em `127.0.0.1` |

Por que separar tanto:

- **Atualizar o PHP sem quebrar o painel.** O painel segue na versão atual sem ser forçado a mudar.
- **Limitar o estrago.** Se um dos dois sistemas tiver problema de segurança ou de desempenho, o outro não é arrastado.
- **Evitar problemas conhecidos.** O banco do painel já tem divergências de collation entre tabelas (caso do `idPolo`); misturar dados aumentaria esse risco.

### 4.3 Capacidade

A VPS já hospeda vários sites, o painel e o phpMyAdmin. **Medido (UNY-DOC-008, seção 3):** 4 núcleos, 5,8 GiB de memória (cerca de 4,4 GiB disponíveis), **sem swap**, disco de 145 GB com 32 GB livres (78% usado). Banda ainda não medida **[A CONFIRMAR]**. A folga é suficiente para a v1 com orçamento controlado (UNY-DOC-008, seção 5), mas curta para crescer. Regras de proteção desde o início:

- Limites de memória e de processos no pool do AVA
- Nenhuma tarefa longa dentro de requisição web (vai para a fila)
- Monitoramento de uso e alerta antes de o servidor ficar sem folga
- Vídeo fora da VPS (seção 10)

## 5. Domínios e subdomínios

### 5.1 Padrão de endereço

```text
ava-{slug-do-parceiro}.faculdadeunypublica.edu.br
```

Exemplo: `ava-unyboss.faculdadeunypublica.edu.br`.

### 5.2 Regras do slug

| Regra | Motivo |
|---|---|
| Apenas letras minúsculas, números e hífen | Padrão de nome de domínio |
| Não começa nem termina com hífen | Padrão de nome de domínio |
| No máximo **59 caracteres** | Cada parte do domínio aceita até 63; `ava-` ocupa 4 |
| Único em toda a plataforma | Cada slug identifica um tenant |
| Lista de nomes reservados (ex.: `painel`, `server`, `intranet`, `www`, `api`, `admin`, `teste`) | Evita conflito com serviços existentes |
| Depois de criado, só muda com processo controlado | Mudar quebra links, certificados e integrações |

### 5.3 DNS

Para criar parceiros sem tocar no DNS a cada vez, usa-se **um registro curinga** na zona do domínio:

```text
*.faculdadeunypublica.edu.br   A   (IP da VPS)
```

Pontos de atenção:

- Registros **explícitos** que já existem (painel, site, etc.) continuam valendo; o curinga só responde por nomes que não existem.
- Como o curinga responde por **qualquer** nome inexistente, o Nginx precisa de um vhost padrão que **rejeite** hosts desconhecidos.
- O Nginx só aceita hosts que casem com `ava-*` e com um tenant cadastrado e ativo; o resto é rejeitado.
- O DNS do domínio fica hoje no GoDaddy. Verificar se o painel dele permite o registro curinga e se há API para automação **[A CONFIRMAR]**.

### 5.4 Certificado SSL

Duas opções:

| Opção | Como | Prós | Contras |
|---|---|---|---|
| **A. Certificado curinga** | Um certificado para `*.faculdadeunypublica.edu.br`, renovado por validação via DNS | Um só certificado; novo parceiro funciona na hora | Renovação automática depende de acesso programático ao DNS **[A CONFIRMAR]** |
| **B. Certificado por parceiro** | Emitido no cadastro do parceiro, validação via HTTP | Usa o mesmo fluxo do Certbot de hoje | Um certificado por parceiro; há limites de emissão por semana, conferir os valores vigentes antes de escalar |

**Recomendação:** começar com a **B** (já é o fluxo conhecido) e migrar para a **A** se a automação de DNS se mostrar viável ou se o volume de parceiros crescer.

### 5.5 Resolução do parceiro pelo endereço

A aplicação identifica o tenant **pelo cabeçalho de host** da requisição, a cada requisição, antes de qualquer outra coisa:

```text
Host recebido
   ↓
Casa com ava-{slug}.faculdadeunypublica.edu.br?  → busca tenant pelo slug
Casa com domínio próprio cadastrado?              → busca tenant pelo domínio
Senão                                             → resposta de "ambiente não encontrado"
   ↓
Tenant encontrado e ativo?
   ↓ sim
Contexto do tenant fica disponível para toda a requisição
```

Se o tenant estiver suspenso, o aluno vê o aviso de indisponibilidade (FS-OPR-002).

Ilustração do roteamento no Nginx (a versão final vem no UNY-DOC-008):

```nginx
server {
    listen 443 ssl;
    server_name ~^ava-[a-z0-9-]+\.faculdadeunypublica\.edu\.br$;
    # ssl_certificate ...
    # fastcgi_pass → pool PHP-FPM do AVA
}
```

### 5.6 Domínio próprio do parceiro (BR-TEN-004)

```text
Parceiro cadastra "cursos.minhamarca.com.br" no painel
        ↓
Sistema mostra a instrução: criar um apontamento (CNAME) para o endereço ava-{slug}…
        ↓
Sistema verifica o DNS periodicamente
        ↓
DNS correto → emite o certificado → ativa o domínio
        ↓
Subdomínio padrão ava-{slug} passa a redirecionar para o domínio próprio (opcional)
```

Estados do domínio: `aguardando DNS` → `verificado` → `certificado emitido` → `ativo` (ou `com erro`, com o motivo visível ao parceiro).

### 5.7 Sessões e cookies (isolamento entre parceiros e com o painel)

Como tudo fica sob o mesmo domínio-pai, **cookies nunca podem ser definidos para o domínio-pai**.

- Cookies de sessão são **restritos ao host exato** (sem atributo de domínio), com marcação de seguro e de acesso apenas por HTTP.
- Nome de cookie próprio do AVA, diferente dos usados pelo painel.
- Sessão do aluno inclui o tenant; uma sessão de um parceiro **nunca** vale em outro.

## 6. Isolamento de dados (multi-tenancy)

### 6.1 Modelo escolhido

**Banco único para todos os parceiros, com coluna `tenant_id`** em toda tabela de dados de negócio.

| Alternativa | Por que não agora |
|---|---|
| Um banco por parceiro | Mais isolamento, mas multiplica backup, migração e conexões; pesado demais para uma VPS compartilhada |
| Tabelas separadas por parceiro | Complexidade alta e difícil de manter |

O caminho de saída fica registrado: se um parceiro grande exigir isolamento total, ele pode migrar para banco próprio sem mudar o restante (seção 14).

### 6.2 Regras obrigatórias

| # | Regra |
|---|---|
| 1 | Toda tabela de negócio tem `tenant_id` obrigatório |
| 2 | Toda consulta passa por um filtro automático de tenant; consulta sem tenant só existe em código marcado como "global" e revisado |
| 3 | Chaves únicas incluem o tenant (ex.: e-mail único **por parceiro**, não no sistema inteiro) |
| 4 | Gravações herdam o tenant do contexto da requisição; o tenant nunca vem de dado enviado pelo usuário |
| 5 | Filas, cache e arquivos incluem o tenant na chave ou no caminho |
| 6 | Existe uma bateria de **testes de isolamento** que tenta ler e gravar dados de outro tenant e precisa sempre falhar |
| 7 | O acesso do operador a dados de um parceiro é explícito e gera auditoria (FS-PER-002) |

### 6.3 Tabelas globais (sem tenant)

Operador, planos, catálogo de adaptadores de pagamento, configurações da plataforma e modelos padrão de e-mail e certificado.

### 6.4 Aluno em vários parceiros

A conta do aluno **pertence ao parceiro** (e-mail único por tenant). Um mesmo e-mail em dois parceiros são duas contas independentes, sem vínculo visível (BR-TEN-005).

## 7. Módulos da aplicação

| Módulo | Responsabilidade | Principais requisitos |
|---|---|---|
| **Tenancy** | Parceiros, domínios, planos, limites, suspensão | BR-TEN, BR-OPR |
| **Identidade** | Contas, login, link mágico, papéis e permissões | BR-STU-007, FS-PER |
| **Catálogo** | Cursos, módulos, aulas, categorias, trilhas | BR-CAT |
| **Aprendizado** | Progresso, retomada, favoritos | BR-STU |
| **Avaliações** | Provas, tarefas, tentativas, correção, notas e regra de conclusão | BR-AVA |
| **Interação** | Comentários e reações por aula, moderação | BR-STU-013, 014 |
| **Matrículas** | Matrículas, estados, origens, expiração, códigos, importação | BR-PAY-006 a 009 |
| **Pagamentos** | Adaptadores, webhooks, eventos, conciliação | BR-PAY |
| **Certificados** | Regras, modelos, retenção e liberação, emissão com hash do painel | BR-CER |
| **Tema e vitrine** | Aparência, home, banners, vitrines, páginas | BR-CUS |
| **Relatórios** | Indicadores e exportações | BR-ADM-006 |
| **Auditoria** | Registro imutável de ações | BR-TEN-007, BR-NFR-005 |
| **Ponte com o painel** | Espelhamento de matrículas, situação do parceiro, hash e QR Code | BR-INT, seção 9 |
| **Comunicação** | E-mails transacionais com a marca do parceiro | FS-EML |

Regra de dependência: módulos conversam por interfaces claras. Por exemplo, **Pagamentos** nunca altera matrícula diretamente; ele emite um evento e **Matrículas** decide.

## 8. Pagamentos

### 8.1 Fluxo técnico

```text
Plataforma de venda
      │  webhook
      ▼
Endereço único por integração:  /webhooks/{identificador-da-integração}
      │
      ▼
1. Identifica parceiro e integração pelo endereço
2. Valida a autenticidade (assinatura, token ou outro método da plataforma)
3. Grava o conteúdo bruto recebido, ANTES de qualquer processamento
4. Responde com sucesso rapidamente
5. Coloca o processamento na fila
      │
      ▼
Worker da fila
6. Adaptador traduz para um evento interno padrão
7. Checa duplicidade (idempotência)
8. Entrega o evento ao módulo de Matrículas
9. Marca o evento como processado, ignorado ou com erro
```

### 8.2 Peças

| Peça | Função |
|---|---|
| **Adaptador** | Um por plataforma: valida a autenticidade e traduz o formato próprio para o evento interno |
| **Consulta complementar** | Alguns adaptadores (Asaas, Mercado Pago) recebem só identificadores e consultam a API da plataforma com a credencial do parceiro, inclusive para obter o CPF do comprador |
| **Eventos internos** | Compra aprovada, reembolsada, cancelada ou recusada; chargeback; assinatura renovada, atrasada ou cancelada; disputa aberta; informativo (UNY-DOC-006, seção 4) |
| **Registro de eventos** | Tudo que chegou, com conteúdo bruto, resultado e tentativas |
| **Idempotência** | Chave formada por integração + identificador do evento externo; evento repetido não gera nova matrícula |
| **Ordem** | Evento antigo que chega depois de um mais novo não desfaz o estado atual |
| **Reprocessamento** | Evento com erro pode ser reprocessado pelo parceiro ou pelo operador |
| **Webhook genérico** | Adaptador configurável que usa o mapeamento de campos definido pelo parceiro (FS-PAY-002) |

### 8.3 Regras de segurança

- **Segredos** (chaves, tokens) são guardados **criptografados**, isolados por parceiro, e nunca exibidos de novo (RN-PAY-05, FS-PAY-004). A mesma abordagem de criptografia já usada no painel pode ser reaproveitada **[A CONFIRMAR]**.
- Nenhum dado de cartão passa pela plataforma (RN-PAY-04).
- O endereço do webhook contém um identificador não adivinhável e a validação de autenticidade é obrigatória sempre que a plataforma oferecer algum método.
- Limite de requisições por integração, para conter abuso.

### 8.4 Fila de tarefas

Na versão 1, a fila usa **o próprio banco de dados**, com processos de trabalho supervisionados no servidor. Isso evita instalar mais um serviço na VPS. Se o volume crescer, a fila pode migrar para um serviço dedicado sem mudar quem produz ou consome tarefas.

A fila também atende: envio de e-mails, emissão de certificados, importação em lote, expiração de matrículas e verificação de domínios.

### 8.5 Adaptadores previstos

Hotmart, Kiwify, Eduzz, Monetizze, Greenn, Braip, Asaas, Stripe, Mercado Pago e Pagar.me, entregues em ondas conforme o UNY-DOC-002. Antes de construir cada um, a documentação oficial atual da plataforma é consultada (UNY-DOC-006).

## 9. Ponte com o painel de matrículas

### 9.1 Objetivo

A ponte existe por duas razões:

1. **Espelhar** cada matrícula do AVA no painel de matrículas da Faculdade Unypública, onde o sistema de **créditos e assinatura** já controla a situação do parceiro.
2. **Condicionar a liberação do certificado** à situação do parceiro, usando o **hash e o QR Code de validação gerados pelo painel**.

O aluno estuda sem depender disso. **Só a liberação do certificado depende do painel.**

### 9.2 Princípio: o painel é a fonte da verdade

O AVA **não copia** as regras de assinatura e crédito. Ele **pergunta** ao painel e **obedece** à resposta. Isso evita duas versões da mesma regra que, com o tempo, passariam a divergir.

### 9.3 Comunicação por API assinada

```text
          AVA                                           Painel de matrículas
 ┌──────────────────────┐                          ┌──────────────────────────┐
 │ Matrícula criada     │── espelhar matrícula ───▶│ Registra a matrícula     │
 │                      │◀── id da matrícula ──────│ (créditos/assinatura)    │
 │                      │                          │                          │
 │ Curso concluído      │── pedir liberação ──────▶│ Avalia a situação do     │
 │                      │                          │ parceiro e da matrícula  │
 │                      │◀─ habilitado + hash/QR ──│                          │
 │                      │◀─ ou retido + motivo ────│                          │
 │                      │                          │                          │
 │ Certificado emitido  │── confirmar emissão ────▶│ Registra para validação  │
 │ Certificado revogado │── informar revogação ───▶│ Atualiza a validação     │
 └──────────────────────┘                          └──────────────────────────┘
```

### 9.4 Mensagens

| Direção | Mensagem | Quando | Conteúdo mínimo |
|---|---|---|---|
| AVA → painel | Espelhar matrícula | Criação, em qualquer origem | Parceiro vinculado, nome, e-mail e **CPF** do aluno (outros campos **[A CONFIRMAR]**), curso, origem, data |
| AVA → painel | Atualizar matrícula | Suspensa, cancelada, expirada, reativada | Identificador da matrícula, novo estado |
| AVA → painel | Pedir liberação de certificado | Conclusão do curso | Identificador da matrícula espelhada |
| AVA → painel | Confirmar emissão | Certificado gerado | Hash usado, data |
| AVA → painel | Informar revogação | Revogação | Hash, motivo |
| Painel → AVA | Resposta ao pedido de liberação | Resposta imediata | **Habilitado** com hash e endereço de validação, ou **retido** com motivo |
| Painel → AVA | Matrícula criada ou aprovada no painel (P1) | Ação no painel | Dados para abrir matrícula no AVA, com origem "painel" |

A **mudança de situação do parceiro** (regularizou ou ficou fora de dia) chega ao AVA de uma de duas formas, a decidir (seção 9.7): o painel avisa, ou o AVA consulta periodicamente.

### 9.5 Fluxo de liberação do certificado

```text
Aluno conclui o curso
        ↓
Matrícula já espelhada?  → não: espelha agora (fila, com reenvio)
        ↓ sim
AVA pede a liberação ao painel (tempo limite curto)
        ↓
Painel responde:
  • habilitado + hash/QR → AVA gera o PDF com hash/QR → certificado emitido
  • retido (motivo)      → certificado pendente; aluno vê "em preparação"
  • sem resposta         → certificado pendente (aguardando painel); nova tentativa automática
        ↓
Parceiro regulariza → AVA descobre → libera os pendentes e avisa os alunos
```

### 9.6 Regras da ponte

| # | Regra |
|---|---|
| 1 | Toda requisição é **assinada**, tem **carimbo de tempo** e **identificador único**; o receptor ignora repetidas |
| 2 | O espelhamento é **assíncrono**, por fila; a matrícula fica ativa no AVA sem esperar o painel |
| 3 | O estudo **nunca** é bloqueado pela situação do parceiro nem por falha do painel |
| 4 | A pergunta de liberação tem tempo limite curto. **Sem resposta, o certificado fica pendente**; nunca é liberado por padrão |
| 5 | O hash é gerado **pelo painel**, uma única vez, e é imutável. O AVA nunca inventa um hash |
| 6 | Todo parceiro do AVA é criado **vinculado a um parceiro ou polo do painel**, por uma tabela de ligação no AVA, sem alterar tabelas do painel **[A CONFIRMAR: se todo parceiro já existe no painel]** |
| 7 | Falhas entram em **reenvio automático** com intervalo crescente e aparecem como divergência para operador e parceiro |
| 8 | Uma tarefa periódica de **reconciliação** compara as matrículas do AVA com as espelhadas e aponta diferenças |
| 9 | Nenhum sistema grava no banco do outro |
| 10 | Certificado já liberado não é retirado por inadimplência posterior (proposta, decisão 14); revogação só por ato explícito |

### 9.7 O que o painel precisa ganhar

O painel (PHP 7.2) precisa de uma alteração **controlada e testada**:

- Endpoint para **receber a matrícula espelhada** e devolver o identificador
- Endpoint para **responder ao pedido de liberação** (habilitado com hash e QR Code, ou retido com motivo)
- Endpoint para **receber emissão e revogação**
- Tabelas próprias para o vínculo e para o registro dessas mensagens
- Forma de informar a **mudança de situação do parceiro**:
  - **Opção A, o painel avisa o AVA:** exige que o painel faça chamadas externas e trate falhas de envio
  - **Opção B, o AVA consulta periodicamente** os parceiros com certificados retidos: o painel só responde, o que é mais simples para um sistema mais antigo. **Recomendada para a v1**

Se o painel já espelha matrículas e devolve hash e QR Code em outra integração (como na integração com a Pensar Cursos), vale **reaproveitar esse padrão**, se for compatível **[A CONFIRMAR]**.

### 9.8 Alternativas descartadas

| Alternativa | Por que não |
|---|---|
| Ler e gravar direto no banco do painel | Acopla os dois sistemas, quebra o isolamento, herda as divergências de collation e dificulta atualizar um sem afetar o outro |
| Reaproveitar o código PHP do painel dentro do AVA | Amarra o AVA à versão antiga do PHP e aos mesmos padrões do painel |
| O AVA replicar as regras de crédito e assinatura | Duas versões da mesma regra; cedo ou tarde divergem e geram certificados liberados ou retidos por engano |
| Bloquear o acesso às aulas quando o parceiro estiver fora de dia | Contraria a regra do negócio: o aluno estuda normalmente |

## 10. Vídeo, arquivos e armazenamento

### 10.1 Vídeo

**Vídeo não fica na VPS.** Servir vídeo consome banda e processamento e competiria com os outros sistemas. O AVA usa uma **camada de provedor de vídeo**:

```text
Aula de vídeo → VideoProvider (interface)
                    ├── Provedor A (serviço especializado em vídeo com proteção)
                    ├── Provedor B (incorporação de plataforma de vídeo)
                    └── Link externo
```

O provedor concreto é uma decisão em aberto (seção 15). O AVA guarda apenas o identificador do vídeo e as regras de acesso; o player recebe um endereço de reprodução quando o aluno tem matrícula ativa (FS-STU-043).

### 10.2 Arquivos (materiais, capas, logos, banners)

- Armazenados **fora da pasta pública** do servidor, em caminho por tenant
- Servidos por um controlador que **verifica o tenant e, quando aplicável, a matrícula**
- Imagens públicas de tema (logo, banners) podem ser servidas diretamente, mas apenas as do tenant da requisição
- Limites de tipo e de tamanho por plano, validação de conteúdo real do arquivo (não só extensão)
- Verificação de vírus nos envios **[A CONFIRMAR: ferramenta]**

## 11. Tema e personalização

| Elemento | Como funciona |
|---|---|
| **Tokens de design** | Cores, fontes e espaçamentos do parceiro viram variáveis de estilo aplicadas ao ambiente |
| **Home e vitrines** | Guardadas como configuração estruturada e **validada**, não como HTML livre |
| **Rascunho e publicação** | Duas versões da configuração; o aluno só vê a publicada (FS-REG-030) |
| **Elementos obrigatórios** | Definidos pelo sistema, fora do alcance do parceiro (FS-REG-031) |
| **Cache** | Configuração publicada fica em cache por tenant e é invalidada na publicação |
| **Código livre do parceiro** | **Não existe na v1.** O parceiro configura, não injeta HTML, CSS ou script próprio, o que evita ataques entre ambientes e quebra de layout |

## 12. Segurança

| Tema | Medida |
|---|---|
| Isolamento | Seção 6, com testes automáticos de isolamento |
| Autenticação | Senhas com algoritmo de hash moderno; link mágico com prazo curto e uso único; limite de tentativas |
| Autorização | Permissão verificada no servidor em toda ação (FS-PER-001) |
| Entrada de dados | Validação e escape de tudo que vem do usuário; proteção contra CSRF |
| Webhooks | Autenticidade obrigatória, registro bruto, limite de taxa |
| Segredos | Criptografados, isolados por parceiro, fora do repositório |
| Auditoria | Registro **apenas de inclusão**: o usuário do banco que a aplicação usa não tem permissão de alterar ou apagar esses registros |
| Separação no servidor | Usuário, pool PHP e banco próprios para o AVA (seção 4.2) |
| Cabeçalhos HTTP | Política de segurança de conteúdo, proteção contra incorporação indevida, HTTPS obrigatório |
| LGPD | Consentimento, exportação e exclusão de dados do aluno (FS-STU-051); retenção definida por tipo de dado |
| Reputação do domínio | Os ambientes ficam sob o domínio da faculdade. Conteúdo de um parceiro afeta a reputação de todos; é preciso uma política de uso aceitável e capacidade de suspender um ambiente rapidamente (FS-OPR-002) |

## 13. Ambientes, deploy e backup

### 13.1 Ambientes

| Ambiente | Onde | Uso |
|---|---|---|
| **Local** | Máquina de desenvolvimento | Construir e testar |
| **Homologação** | Na VPS, em endereço próprio (ex.: `ava-homolog…`), banco separado | Testar com dados fictícios antes de produção |
| **Produção** | Na VPS | Parceiros reais |

Homologação e produção usam bancos, pastas e segredos **diferentes**.

### 13.2 Deploy

Código em repositório Git; deploy por script repetível (atualizar código, dependências, migrações de banco, limpar cache, reiniciar os processos de trabalho). Migrações de banco sempre versionadas e testadas em homologação antes.

### 13.3 Backup

| Item | Frequência | Observação |
|---|---|---|
| Banco do AVA | Diário | Cópia guardada **fora da VPS** |
| Arquivos por tenant | Diário | Cópia guardada fora da VPS |
| Configurações e segredos | A cada mudança | Cópia protegida |
| **Teste de restauração** | Periódico | Backup que nunca foi restaurado não conta como backup |

### 13.4 Observabilidade

Logs de aplicação, de webhooks e da ponte com o painel; alertas para: fila acumulando, integração falhando, certificado perto de vencer, disco e memória altos.

## 14. Evolução e saídas previstas

| Gatilho | Caminho |
|---|---|
| VPS no limite | Separar o AVA em servidor próprio (a aplicação já roda isolada, então a mudança é de infraestrutura) |
| Parceiro grande exige isolamento total | Banco próprio por tenant, mantendo o mesmo código |
| Volume alto de eventos | Fila em serviço dedicado |
| Muitos certificados de domínio | Certificado curinga com validação por DNS automatizada |
| Muito tráfego de arquivos | CDN na frente dos arquivos públicos |
| Necessidade de mais de um servidor de aplicação | Sessões e cache em serviço compartilhado |

## 15. Decisões e verificações pendentes

| # | Item | Tipo | Impacto |
|---|---|---|---|
| 1 | **Stack da aplicação.** Proposta: PHP 8.x com Laravel (filas, agendador, migrações, escopos por tenant e permissões já prontos), MySQL. Alternativas: PHP 8.x com Slim 4 (mais leve, mas filas, migrações e agendador seriam montados à mão) ou Node.js | Decisão | Todo o desenvolvimento |
| 2 | ~~PHP 8.3+ instalado no sistema~~ **Verificado: indisponível** (repositório vazio). ~~Docker instalável~~ **Verificado:** `docker.io` 26.1.3 e `docker-compose-v2` 2.27.1 no Ubuntu 20.04. **Achado urgente:** o servidor está sem firewall e com o MySQL em `0.0.0.0:3306` (UNY-DOC-008, seção 4.6) | Verificação | Seção 4.2 |
| 3 | ~~Recursos atuais da VPS~~ **Medidos** (UNY-DOC-008, seção 3). Resta medir a banda | Verificação | Seção 4.3 |
| 4 | Registro curinga e API de DNS no GoDaddy | Verificação | Seções 5.3 e 5.4 |
| 5 | Padrão `ava-{slug}.…` (hífen, como você definiu) ou `{slug}.ava.…` (um nível a mais). O segundo permite um curinga restrito a `*.ava` e separa melhor do resto da zona, mas foi mantido o padrão que você escolheu | Decisão | DNS e certificado |
| 6 | ~~O que significa "conexão com o painel"~~ **Resolvida** (seção 9.1): espelhar matrículas e condicionar a liberação do certificado | Decisão | Seção 9 |
| 7 | ~~Chave de identificação do aluno~~ **Resolvida:** o CPF é obrigatório. Resta confirmar se o painel exige outros campos | Decisão | Seções 9.4 e 9.6 |
| 8 | Provedor de vídeo | Decisão | Custo, proteção e player |
| 9 | Reaproveitar a criptografia de credenciais já usada no painel | Decisão | Seção 8.3 |
| 10 | Ferramenta de verificação de vírus nos envios | Decisão | Seção 10.2 |
| 11 | Ordem de implementação dos adaptadores (proposta no UNY-DOC-006, seção 6) | Decisão | Cronograma |
| 12 | Regras exatas de "em dia" (assinatura, créditos, matrícula) e formato da resposta do painel | Verificação | Seções 9.4 e 9.5 |
| 13 | A validação pública do certificado continua sendo a do painel/faculdade (assumido que sim) | Decisão | FS-CER-001 |
| 14 | Certificado já liberado continua acessível se o parceiro ficar fora de dia depois (proposta: sim) | Decisão | Seção 9.6, regra 10 |
| 15 | Painel avisa a mudança de situação ou o AVA consulta periodicamente (proposta: consulta) | Decisão | Seção 9.7 |
| 16 | Todo parceiro do AVA já existe como parceiro ou polo no painel? | Verificação | Seção 9.6, regra 6 |
| 17 | Padrão da integração com a Pensar Cursos é reaproveitável? | Verificação | Seção 9.7 |

## 16. Registro de decisões (ADR resumido)

| ADR | Decisão | Status |
|---|---|---|
| ADR-001 | AVA na VPS Contabo existente, como aplicação separada do painel | Aceita |
| ADR-002 | Banco único com `tenant_id` e testes de isolamento | Aceita |
| ADR-003 | Parceiro identificado pelo host; cookies restritos ao host | Aceita |
| ADR-004 | Subdomínio `ava-{slug}` com curinga no DNS | Aceita, dependente das verificações 4 e 5 |
| ADR-005 | Pagamento como webhook → registro bruto → fila → evento interno | Aceita |
| ADR-006 | Integração com o painel por API assinada, sem banco compartilhado | Aceita |
| ADR-007 | Vídeo fora da VPS, atrás de uma camada de provedor | Aceita (provedor em aberto) |
| ADR-008 | Sem HTML, CSS ou script livre do parceiro na v1 | Aceita |
| ADR-009 | Fila no próprio banco na v1 | Aceita |
| ADR-010 | Stack PHP 8.3 + **Laravel 13** | Aceita (09/10/2026); compatibilidade com MySQL 5.7 a confirmar no CI |
| ADR-011 | O painel é a fonte da verdade da situação do parceiro; certificado só é liberado com resposta positiva (sem resposta, fica pendente) | Aceita |
| ADR-012 | Hash e QR Code do certificado são gerados pelo painel | Aceita |
| ADR-013 | Estudo nunca é bloqueado pela situação comercial do parceiro | Aceita |
| ADR-014 | CPF do aluno é obrigatório para espelhamento e certificado; guardado criptografado, com índice de busca por hash | Aceita |
| ADR-015 | AVA roda em contêiner Docker (PHP 8.3) na VPS atual, pois o PHP 8 não existe para o Ubuntu 20.04; swap de 2 GiB, teto de disco e gatilhos de saída para VPS própria (UNY-DOC-008, seções 4, 6 e 18) | Aceita (08/10/2026); Docker confirmado como instalável |
| ADR-016 | MySQL 5.7.42 existente: AVA usa schema próprio, sem recursos do 8.0 (CTE, janelas, CHECK efetivo, SKIP LOCKED); auditoria em schema separado `ava_audit` | Proposta |

## 17. Próximo documento

**UNY-DOC-005 · Banco de dados:** tabelas, relacionamentos, índices, estados e regras de integridade, partindo dos módulos da seção 7 e dos requisitos já mapeados.
