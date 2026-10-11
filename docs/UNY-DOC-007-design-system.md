# UNY-DOC-007 · Design System

**Projeto:** UnyEducation
**Versão:** 0.3 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Depende de:** UNY-DOC-003 (telas e fluxos), UNY-DOC-004 (seção 11, tema), UNY-DOC-005 (`configuracoes_visuais`)

---

## 1. Como ler este documento

Este documento define **como o AVA se parece e como o parceiro pode mudar essa aparência** sem quebrar nada. Ele é a ponte entre as telas descritas no UNY-DOC-003 e o código.

> **Sobre as referências visuais.** Recebi cinco prints: a **home** de uma área de membros (menu lateral, banner e linhas de cards), uma peça sobre **comunidade**, e três prints da **página da aula** (player, materiais, comentários e lista de conteúdo). Com eles, a seção 8 deixa de ser provisória. A página `lp.waid.io` indicada antes continua sem ter sido vista por mim.

Itens marcados com **[A CONFIRMAR]** dependem de decisão sua (lista na seção 13).

## 2. Princípios de design

| # | Princípio | Consequência prática |
|---|---|---|
| 1 | **O conteúdo é o protagonista** | Capas e vídeos ocupam o espaço; a interface recua |
| 2 | **Streaming, não sistema** | Linhas horizontais, cards grandes, retomada em um toque, poucos menus |
| 3 | **O parceiro manda na marca, a plataforma manda na usabilidade** | O parceiro troca cores, logo e fontes; espaçamento, hierarquia e acessibilidade são fixos |
| 4 | **Nenhuma combinação do parceiro pode quebrar a leitura** | O sistema calcula e valida contraste automaticamente |
| 5 | **Calmo para estudar** | Sem animação gratuita, sem distração dentro da aula |
| 6 | **Funciona no celular primeiro** | Todo componente nasce no tamanho de celular |
| 7 | **Acessível por padrão** | Alvo WCAG 2.2 nível AA |

## 3. Dois níveis de identidade

| Nível | Onde aparece | Quem define |
|---|---|---|
| **Plataforma (UnyEducation)** | Painel do operador, painel do parceiro, páginas de erro e e-mails de sistema | A plataforma |
| **Parceiro** | Todo o **ambiente do aluno**, e-mails ao aluno, certificado | O parceiro, dentro dos limites deste documento |

**Painel do parceiro:** mantém a **identidade da plataforma** (para o suporte reconhecer qualquer painel), mostrando o **logo do parceiro** no topo. O parceiro personaliza o que **o aluno** vê, não a ferramenta de trabalho dele.

## 4. Temas base (propostas)

Em vez de partir do zero, o parceiro **escolhe um tema base** e ajusta. São três direções, todas com contraste verificado.

### 4.1 Tema A · **Cinema** (escuro, estilo streaming)

Fundo escuro, capas em destaque, cor principal vibrante. É a direção mais próxima do "Netflix de cursos".

| Papel | Cor | |
|---|---|---|
| Fundo | `#0E0E14` | |
| Superfície | `#16161F` | cards, painéis |
| Superfície 2 | `#1F1F2B` | elementos elevados, hover |
| Borda | `#2C2C3A` | |
| Texto | `#F4F4F8` | |
| Texto secundário | `#A6A6B8` | |
| Principal (padrão) | `#8B6CFF` | violeta |
| Texto sobre o principal | `#0E0E14` | **escuro**, não branco |
| Sucesso / Erro | `#3DD68C` / `#FF6B6B` | |

Tipografia: títulos **Sora**, texto **Inter**.

### 4.2 Tema B · **Estúdio** (claro, editorial)

Fundo quente, tipografia com serifa nos títulos, tom de revista. Combina com cursos de criação, bem-estar e artesanato.

| Papel | Cor |
|---|---|
| Fundo | `#FAF8F4` |
| Superfície | `#FFFFFF` |
| Superfície 2 | `#F1EDE6` |
| Borda | `#DDD6CB` |
| Texto | `#1B1B20` |
| Texto secundário | `#5E5A55` |
| Principal (padrão) | `#C2410C` (terracota) |
| Texto sobre o principal | `#FFFFFF` |
| Sucesso / Erro | `#15803D` / `#B91C1C` |

Tipografia: títulos **Fraunces**, texto **DM Sans**.

### 4.3 Tema C · **Foco** (claro, neutro e frio)

O mais sóbrio. Pensado para cursos técnicos e profissionalizantes, em que a leitura longa pesa mais que o clima.

| Papel | Cor |
|---|---|
| Fundo | `#F6F8FB` |
| Superfície | `#FFFFFF` |
| Superfície 2 | `#E9EEF5` |
| Borda | `#D3DBE6` |
| Texto | `#11181F` |
| Texto secundário | `#55606E` |
| Principal (padrão) | `#1D5FD6` (azul) |
| Texto sobre o principal | `#FFFFFF` |
| Sucesso / Erro | `#15803D` / `#B91C1C` |

Tipografia: títulos **Plus Jakarta Sans**, texto **Source Sans 3**.

### 4.4 Contrastes verificados

Razões de contraste calculadas pela fórmula do WCAG. O mínimo exigido para texto comum é **4,5**.

| Combinação | A · Cinema | B · Estúdio | C · Foco |
|---|---|---|---|
| Texto sobre fundo | 17,54 | 16,17 | 16,81 |
| Texto sobre superfície | 16,38 | 17,15 | 17,88 |
| Texto secundário sobre fundo | 8,03 | 6,45 | 6,01 |
| Texto secundário sobre superfície 2 | 6,80 | 5,86 | 5,48 |
| Texto sobre o principal (botão) | 5,21 | 5,18 | 5,74 |
| Principal sobre fundo (links, ícones) | 5,21 | 4,88 | 5,39 |
| Sucesso sobre fundo | 10,26 | 4,73 | 4,71 |
| Erro sobre fundo | 6,93 | 6,10 | 6,08 |

Observação de design: no tema **Cinema**, **texto branco sobre o violeta falharia** (3,69). Por isso o botão principal usa **texto escuro**. Essa decisão é calculada automaticamente (seção 6.3).

### 4.5 Recomendação

- **Tema padrão da plataforma: A · Cinema**, por ser o que mais entrega a experiência de streaming. Os prints enviados são coerentes com ele: fundo escuro, uma cor viva como destaque e texto escuro sobre o destaque.
- Os três ficam disponíveis como **temas base** no editor do parceiro (FS-ADM-050).
- O parceiro pode permitir **claro e escuro** (BR-CUS-004): cada tema base tem uma versão invertida **[A CONFIRMAR se na v1 ou depois]**.

> Tipografia e cores acima são **propostas de partida** para você, que é designer, reagir e ajustar. Nada aqui é definitivo.

## 5. Tokens de design

Tudo o que o parceiro configura é salvo como **tokens**, nunca como CSS livre (UNY-DOC-004, seção 11). O ambiente aplica os tokens como variáveis de estilo.

### 5.1 Tokens que o **parceiro pode alterar**

| Token | Opções | Validação |
|---|---|---|
| `tema_base` | Cinema, Estúdio, Foco | |
| `modo` | claro, escuro, ambos | |
| `cor_principal` | Qualquer cor | Sistema calcula contraste e escolhe automaticamente texto claro ou escuro sobre ela; **bloqueia** cores com contraste insuficiente contra o fundo e **avisa** (FS-ADM-051) |
| `cor_secundaria` | Qualquer cor | Mesma validação. Usada em destaques secundários, como o botão flutuante de ajuda |
| `fonte_titulo`, `fonte_texto` | Lista curada (seção 5.3) | |
| `raio_borda` | Reto, Suave, Arredondado | |
| `logo_claro`, `logo_escuro`, `favicon` | Arquivo | Tipo, tamanho e proporção validados |
| `banner_*` | Imagens para computador e celular | Peso máximo, proporção, recorte guiado |
| `menu` | Itens do menu lateral (ícone, rótulo, destino, ordem) | Seção 8.4 |
| `botao_ajuda` | Ativo ou não, rótulo, destino | Seção 8.5 |

### 5.2 Tokens **fixos** (o parceiro não altera)

Escala de espaçamento, escala tipográfica (tamanhos e proporções), alturas de linha, tamanho mínimo de alvos de toque, duração e curva de animações, ordem e estrutura dos componentes, estados de foco, cores semânticas de **erro**, **aviso** e **sucesso** (para não confundir o aluno), e elementos obrigatórios (FS-REG-031).

### 5.3 Fontes disponíveis ao parceiro (proposta)

Uma lista curada de **6 a 8 pares**, para evitar fontes pesadas ou ilegíveis. Os três pares dos temas base entram, mais alternativas como uma fonte geométrica e uma serifada de leitura.

Regra técnica: as fontes são **hospedadas no próprio servidor**, não carregadas de terceiros. Isso melhora o desempenho e evita enviar dados do aluno a serviços externos (LGPD). **[A CONFIRMAR]**

### 5.4 Como os tokens viram estilo

Exemplo do **tema Cinema** como variáveis CSS (nomes em português coerentes com a documentação):

```css
:root[data-tema="cinema"] {
  /* cores */
  --cor-fundo:        #0E0E14;
  --cor-superficie:   #16161F;
  --cor-superficie-2: #1F1F2B;
  --cor-borda:        #2C2C3A;
  --cor-texto:        #F4F4F8;
  --cor-texto-suave:  #A6A6B8;
  --cor-principal:    #8B6CFF;
  --cor-sobre-principal: #0E0E14;
  --cor-sucesso:      #3DD68C;
  --cor-aviso:        #F5B942;
  --cor-erro:         #FF6B6B;
  --cor-foco:         #C9BBFF;

  /* tipografia */
  --fonte-titulo: "Sora", system-ui, sans-serif;
  --fonte-texto:  "Inter", system-ui, sans-serif;

  /* forma */
  --raio-suave: 10px;
}
```

E como **dados salvos** em `configuracoes_visuais.conteudo_json` (UNY-DOC-005, seção 13.1):

```json
{
  "tema_base": "cinema",
  "modo": "escuro",
  "cor_principal": "#8B6CFF",
  "cor_secundaria": null,
  "fonte_titulo": "sora",
  "fonte_texto": "inter",
  "raio_borda": "suave",
  "logo_claro": "arquivo-uuid",
  "logo_escuro": "arquivo-uuid",
  "favicon": "arquivo-uuid"
}
```

## 6. Regras de personalização

### 6.1 Derivação automática

A partir da `cor_principal`, o sistema **gera sozinho**: versão de hover, de clique, tom suave (para fundos de destaque), cor de foco e a cor de texto sobre ela. O parceiro escolhe **uma cor**, não uma paleta inteira.

### 6.2 Limites

| Situação | O que o sistema faz |
|---|---|
| Cor principal clara demais para o tema escuro ou escura demais para o claro | Sugere o ajuste mais próximo que passa no contraste |
| Contraste abaixo do mínimo mesmo após ajuste | **Bloqueia a publicação** e explica |
| Logo com fundo opaco ruim para o tema | Avisa e sugere versão para fundo claro ou escuro |
| Banner com texto embutido na imagem | Avisa que pode ficar ilegível no celular |

### 6.3 Texto sobre a cor principal

O sistema calcula o contraste do **texto claro** e do **texto escuro** contra a cor principal e **escolhe o melhor**. Foi assim que o tema Cinema resultou em texto escuro sobre o violeta.

### 6.4 Presença da plataforma

Definir se os ambientes mostram uma marca discreta de **tecnologia da plataforma** (por exemplo, no rodapé) e se a marca da **Faculdade Unypública** aparece em algum ponto. **[A CONFIRMAR]**

## 7. Componentes

### 7.1 Estrutura da escala

| Elemento | Regra |
|---|---|
| Unidade de espaçamento | 4 px (múltiplos: 4, 8, 12, 16, 24, 32, 48, 64) |
| Escala tipográfica | Proporção constante entre tamanhos; texto corrido nunca abaixo de 16 px |
| Alvo de toque | Mínimo **44 × 44 px** |
| Raios | 3 níveis (reto, suave, arredondado), aplicados a todo o ambiente |
| Elevação | 3 níveis, no escuro por contraste de superfície e não por sombra |
| Pontos de quebra | Celular (até 599), tablet (600–1023), computador (1024 em diante), tela grande (1440 em diante) |

### 7.2 Catálogo (ambiente do aluno)

| Componente | Descrição | Tela de origem |
|---|---|---|
| **Menu lateral** | Logo, busca, itens com ícone, perfil no rodapé. Fixo e recolhível no computador e no tablet; item ativo em destaque na cor principal | Todas (computador) |
| **Barra superior e menu inferior** | No celular: logo e busca no topo, itens principais em uma barra inferior | Todas (celular) |
| **Banner de destaque** | Imagem ampla, título, texto curto, botão. Versões para computador e celular | Home (FS-STU-010) |
| **Vitrine** | Título e linha horizontal de cards, rolagem por toque ou setas, com "ver tudo" | Home |
| **Card de curso ou módulo** | **Capa vertical 2:3** enviada pelo parceiro, título em texto (mesmo que a arte já o traga), rótulo opcional, progresso quando houver. Opção de capa 16:9 por vitrine **[A CONFIRMAR]** | Home, busca |
| **Card "continuar assistindo"** | Card de curso com **barra de progresso** e título da aula; clique abre direto na aula | Home (FS-STU-011) |
| **Card bloqueado** | Mesmo card com selo "não incluído"; clique leva à página do curso | Home (UNY-DOC-003, 4.2) |
| **Cabeçalho do curso** | Capa grande, título, instrutor, carga horária, ação principal | Curso (FS-STU-030) |
| **Lista de módulos** | Acordeão de módulos com aulas, estado de cada aula (concluída, disponível, bloqueada com data) | Curso, player |
| **Indicador de progresso** | Barra e percentual, em curso e módulo | Vários |
| **Selos de estado** | Concluída, em andamento, bloqueada, certificado em preparação, certificado disponível | Vários |
| **Formulário** | Campos com rótulo visível, erro junto ao campo, máscara de CPF com validação ao digitar | "Complete seu cadastro" (FS-STU-004) |
| **Aviso e lembrete** | Faixa discreta (ex.: "informe seu CPF para liberar o certificado") | Home (FS-STU-005) |
| **Estados vazios** | Mensagem + ação (ex.: "Você ainda não começou nenhum curso") | Vários |
| **Carregamento** | **Esqueletos** no formato dos cards, nunca roda sozinha | Vários |
| **Erro de carregamento** | Mensagem simples + botão de tentar de novo | Vários |
| **Aviso temporário** | Confirmações rápidas (ex.: "Aula marcada como concluída") | Vários |
| **Certificado** | Cartão com miniatura, nome do curso, data e botão de baixar | Meus certificados (FS-STU-050) |
| **Botão de ajuda** | Botão flutuante no canto inferior direito, na cor secundária, com rótulo e destino definidos pelo parceiro | Todas (FS-STU-014) |
| **Perfil e notificações** | Foto, nome e contador de avisos, no rodapé do menu lateral (notificações: seção 8.9) | Todas |

### 7.3 Catálogo (painel do parceiro e do operador)

Identidade da plataforma, **densidade maior** que o ambiente do aluno: tabelas, filtros, formulários, abas (editor de curso), arrastar para ordenar, editor visual da home e do certificado, alertas e painéis de conciliação. Os **tokens** são os mesmos; mudam a densidade e os componentes.

### 7.4 Estados obrigatórios

Todo componente interativo define: **padrão, hover, foco, pressionado, desabilitado e erro**. O estado de **foco** é sempre visível (anel de 2 px com a cor de foco, contraste mínimo 3:1 contra o entorno).

### 7.5 Movimento

- Duração curta (100 a 250 ms) para respostas de interface
- Sem animação dentro da aula, exceto indicadores de progresso e carregamento
- Respeitar a preferência do sistema por **movimento reduzido**: nesse caso, as animações são removidas

## 8. Ambiente do aluno: estrutura e área de estudo

### 8.1 O que os prints mostram

| Print | O que se vê |
|---|---|
| **1 · Home do ambiente** | **Menu lateral escuro** com logo, campo de busca, itens com ícone (um deles ativo, em destaque na cor da marca), um botão tracejado "Novo menu" (parece a visão de edição do parceiro) e perfil com contador de avisos no rodapé. À direita, **banner largo** com a marca do parceiro e linhas de **cards verticais** com arte própria (título dentro da imagem e um rótulo embaixo), com seta para rolar. Um **botão flutuante "Ajuda"** no canto inferior direito. Itens de menu como "Aulas", "Continuar assistindo", "Comunidade", um assistente de IA, FAQ e suporte |
| **3 a 5 · Página da aula** | Barra superior com marca do parceiro, link de suporte, **troféu**, busca, avisos e foto. **Player centralizado sobre fundo preto**. Abaixo: trilha de navegação, título, blocos como **aula em áudio** e **material para download** (nome e tamanho), botões de **curtir, não curtir e desmarcar como visto**, cartões de **aula anterior e próxima**, e **comentários** com respostas da equipe. À direita, **progresso geral** ("87 de 155 aulas") e **um cartão por módulo** com indicador circular e contagem de aulas; o módulo aberto lista as aulas com marca de concluída e uma linha ligando os itens; há um módulo de **certificado** na lista |
| **2 · Peça sobre comunidade** | Notebook e celular mostrando uma **comunidade**: feed de publicações, **ranking**, **calendário de eventos** e espaços organizados por seções (aviso geral, apresente-se, vagas, etc.) |

Observação: o nome que aparece no canto inferior do print 1 indica uma plataforma concorrente de e-learning. Isso mostra o que parte do mercado já oferece, mas **não obriga** a v1 a ter tudo isso.

### 8.2 O que muda no nosso design

| Referência | Decisão |
|---|---|
| Menu lateral fixo e recolhível | **Adotado** para computador e tablet; no celular vira menu inferior |
| Item ativo em destaque na cor da marca, com texto escuro | **Já é compatível** com a regra de texto sobre a cor principal (seção 6.3) |
| Cards verticais com arte própria | **Capa vertical 2:3 vira o padrão** (resolve a decisão aberta sobre proporção) |
| Título dentro da imagem do card | Permitido, mas o **título em texto continua obrigatório** (leitor de tela, busca e cards sem arte) |
| Rótulo sobre o card | Rótulo opcional, definido pelo parceiro (ex.: grupo ou categoria) |
| Banner largo | Banner largo com **versão própria para celular** |
| Botão flutuante de ajuda | Componente configurável (seção 8.5) |
| "Novo menu" | Menu lateral configurável (seção 8.4) |
| Perfil e avisos no rodapé do menu | Perfil na v1; avisos conforme decisão (seção 8.9) |
| Cor viva da marca em fundo escuro | Confirma o **tema Cinema** como ponto de partida |

### 8.3 Estrutura geral (computador)

```text
┌────────────────┬────────────────────────────────────────────────────────┐
│  LOGO      ‹‹  │                                                        │
│  [busca     ]  │           BANNER LARGO (marca e destaque)              │
│                │                                                        │
│ ▌Aulas        ▐│  Título da vitrine                                     │
│  Continuar     │  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐        ›          │
│  Comunidade ◦  │  │ 2:3│ │ 2:3│ │ 2:3│ │ 2:3│ │ 2:3│                   │
│  FAQ           │  │    │ │    │ │    │ │    │ │    │                   │
│  Suporte       │  └────┘ └────┘ └────┘ └────┘ └────┘                   │
│  + (edição)    │                                                        │
│                │  Outra vitrine                                         │
│ ◉ Nome   (3)   │  ┌────┐ ┌────┐ ┌────┐ ...                             │
│   perfil       │                                          ( ? Ajuda )   │
└────────────────┴────────────────────────────────────────────────────────┘
        ◦ item opcional, conforme os módulos ativos do parceiro
```

| Medida (proposta) | Valor |
|---|---|
| Menu lateral aberto | 240 px |
| Menu lateral recolhido | 72 px (só ícones, com dica de texto ao passar o mouse ou focar) |
| Recolhimento | Escolha do aluno, lembrada; recolhe sozinho na tela de aula |

**Celular:** barra superior (logo e busca) + **menu inferior** com os 4 itens principais e um "Mais" que abre os demais.

### 8.4 Menu lateral configurável

| Tipo de item | Exemplos | Quem define |
|---|---|---|
| **Fixos do sistema** | Aulas (início), Continuar assistindo, Meus certificados, Perfil | Plataforma (o parceiro pode renomear e reordenar, não remover "Aulas") |
| **Módulos opcionais** | Comunidade, assistente de IA (se existirem e estiverem ativos para o parceiro) | Plataforma e parceiro |
| **Páginas internas** | FAQ, Sobre, Regras | Parceiro |
| **Links externos** | Suporte, WhatsApp, site do parceiro | Parceiro |
| **Curso ou trilha** | Um curso específico em destaque no menu | Parceiro |

Regras:

- Cada item tem **ícone** (de um conjunto curado), **rótulo** e **destino**
- Rótulo de até **24 caracteres**; se não couber, mostra reticências e **dica com o texto completo** (o print mostra rótulos cortados)
- Parceiro ordena, ativa e desativa itens, com pré-visualização e publicação (FS-REG-030)
- Limite de itens visíveis (proposta: **8**); acima disso, o excedente vai para "Mais"
- O item ativo usa a cor principal; os demais, a cor de texto suave
- Dentro do menu, **nada que dependa só de cor** (sempre ícone e texto)

### 8.5 Botão de ajuda

| Item | Regra |
|---|---|
| Configuração | Ativo ou não; rótulo; destino (link de suporte, WhatsApp, página de FAQ) |
| Cor | Cor secundária do parceiro, com texto de contraste calculado |
| Posição | Canto inferior direito, acima do menu inferior no celular |
| Celular | Apenas o ícone, para não cobrir conteúdo |
| Aula | **Recolhe-se durante a reprodução do vídeo e em tela cheia**, para não cobrir os controles |
| Teclado | Alcançável e acionável por teclado |

### 8.6 Home

Ordem vertical (UNY-DOC-003, seção 4.2): **banner largo**, **continuar assistindo** (se houver), **vitrines**, rodapé.

Anatomia de uma vitrine:

| Parte | Regra |
|---|---|
| Título | Texto da vitrine, definido pelo parceiro |
| Linha | Rolagem horizontal por toque ou setas, com ponto de parada em cada card |
| Card | Capa 2:3, título em texto, rótulo opcional, barra de progresso quando houver |
| Seta | Visível ao passar o mouse e acessível por teclado |
| Cards na tela | Computador: de 5 a 6; tablet: 3 a 4; celular: 2 e meio (o meio card sinaliza que dá para rolar) |

**Ponto de modelo importante:** no print 1, os cards da vitrine "Módulos Magnéticos" parecem ser **módulos de um mesmo programa**, não cursos diferentes. No nosso modelo, uma vitrine mostra **cursos**. Para atender esse formato, proponho um tipo novo de vitrine, **"Módulos de um curso"** (P1), em que cada card é um módulo e leva à primeira aula pendente dele. Isso exige uma **capa vertical também no módulo** (já refletido no banco, UNY-DOC-005).

### 8.7 Imagens que o parceiro precisa enviar (proposta)

| Imagem | Proporção | Tamanho sugerido | Observação |
|---|---|---|---|
| Capa de curso e de módulo | **2:3 (vertical)** | 800 × 1200 px (mínimo 400 × 600) | Título pode estar na arte, mas o texto continua existindo |
| Banner largo | Cerca de **3:1** | 2400 × 800 px | Manter texto e rosto dentro da **zona central segura** |
| Banner para celular | **4:5** | 1080 × 1350 px | Versão própria; recorte automático só como último recurso |
| Capa 16:9 (opcional) | 16:9 | 1280 × 720 px | Para vitrines em formato horizontal |
| Logo claro e escuro | Livre | Altura mínima de 96 px | Fundo transparente |
| Favicon | 1:1 | 512 × 512 px | |

Regras de envio: formatos JPG, PNG ou WebP; **geração automática de versões menores** (miniaturas) para carregar rápido; peso máximo por tipo (proposta: 500 KB para capas, 1 MB para banners); aviso quando houver texto embutido pequeno que ficará ilegível no celular (seção 6.2). **[A CONFIRMAR os números]**

### 8.8 Página da aula

Baseada nos prints 3 a 5. O menu lateral **recolhe sozinho** na aula (72 px); a barra superior da referência é absorvida pelo menu lateral e pela barra do celular.

```text
┌────┬────────────────────────────────────────────────┬────────────────────────┐
│ ‹‹ │ ░░░░░░░░░░░░░░ fundo escuro ░░░░░░░░░░░░░░░░░░ │ ◔ Meu progresso 56%    │
│ ◧  │ ░░░░░░  ┌──────────────────────────┐  ░░░░░░░░ │   87 de 155 aulas      │
│ ▶  │ ░░░░░░  │      PLAYER DE VÍDEO     │  ░░░░░░░░ │                        │
│ ☰  │ ░░░░░░  └──────────────────────────┘  ░░░░░░░░ │ ◔ Comece por aqui      │
│ ◉  ├────────────────────────────────────────────────┤   7 aulas              │
│    │ Início › Curso                                 │ ◯ 1. Impulso           │
│    │ Título da aula                                 │   11 aulas             │
│    │                                                │ ▾ ◯ 2. Criação         │
│    │ Aula em áudio       ▶ ──────── 24:14   🔊      │   │ ✓ Aula 1           │
│    │ Material da aula    📎 arquivo.pdf  38,8 MB    │   │ ▶ Aula 2 (atual)   │
│    │ [👍] [👎]  [ Desmarcar como visto ×]           │   │ ○ Aula 3           │
│    │                                                │ ◯ 3. Você precisa…     │
│    │ ┌ ◂ Aula anterior ┐ ┌ Próxima aula ▸ ┐         │ ▣ Certificado          │
│    │ Comentários (12)                               │                        │
│    │  ◉ Aluno  data  texto…                         │                        │
│    │    ↳ ◉ Instrutor  data  resposta…              │                        │
└────┴────────────────────────────────────────────────┴────────────────────────┘
```

**Celular:**

```text
┌──────────────────────────┐
│ ◂  Nome do curso     ⋮   │
├──────────────────────────┤
│      PLAYER DE VÍDEO     │
├──────────────────────────┤
│ Título da aula           │
│ ▓▓▓▓▓░░░░ 56%            │
│ [👍][👎] [Marcar como vista]│
├──────────────────────────┤
│ Aula│Materiais│Comentários│  ← abas
│ conteúdo da aba          │
├──────────────────────────┤
│ ☰ Conteúdo do curso   ▴  │  ← lista em folha inferior
└──────────────────────────┘
```

| Elemento | Regra |
|---|---|
| **Fundo do player** | Faixa escura em volta do player (modo "cinema"), com largura máxima para não esticar em telas grandes |
| **Trilha de navegação** | Início › Curso, com links; no celular fica no cabeçalho |
| **Blocos da aula** | Áudio, texto, materiais, cada um com título; ordem definida pelo parceiro |
| **Materiais** | Nome do arquivo, tipo e tamanho; o clique baixa |
| **Ações da aula** | Curtir, não curtir, marcar ou desmarcar como visto |
| **Anterior e próxima** | Cartões com capa e título; setas |
| **Coluna "Conteúdo do curso"** | Cartão de **progresso geral** e **um cartão por módulo**, cada um com indicador circular de progresso e contagem de aulas |
| **Módulo aberto** | Lista as aulas com estados (✓ concluída, ▶ atual, ○ pendente) e uma linha vertical ligando os itens; o módulo atual abre sozinho |
| **Avaliações na lista** | Ícone próprio de prova e tarefa, estado e selo "obrigatória" (seção 8.10) |
| **Certificado na lista** | Último item: bloqueado, **em preparação** ou **disponível** (UNY-DOC-003, FS-CER-020) |
| **Comentários** | Respostas encadeadas, selo para equipe e suporte, avatar e data |
| **Retomada** | Abre no ponto em que parou (FS-STU-040) |
| **Próxima aula** | Contagem de poucos segundos ao terminar, com opção de cancelar |
| **Modo foco** | Atalho que oculta menu e lista, mantendo só o player |
| **Aula em texto** | A área do player vira área de leitura, com linha de 60 a 75 caracteres |
| **Botão de ajuda** | Recolhido durante o vídeo (seção 8.5) |
| **Teclado** | Espaço (reproduzir), setas (avançar e voltar), F (tela cheia), M (silenciar) |
| **Fim do curso** | Tela de conclusão: parabéns, **certificado** disponível ou **em preparação** (sem expor motivo comercial, RN-CER-05), sugestão de próximos cursos |

### 8.9 Recursos vistos nas referências que **não** estão nos requisitos (a decidir)

| Recurso | Onde apareceu | Tamanho do trabalho | Proposta |
|---|---|---|---|
| **Comunidade** (feed, espaços, comentários, moderação) | Print 2 e menu do print 1 | **Grande.** É um módulo inteiro: publicações, respostas, moderação, denúncias, notificações, regras de privacidade e LGPD | **Versão 2** |
| **Ranking** | Print 2 | Médio. Depende de regras de pontuação e, de preferência, da comunidade | Versão 2 |
| **Calendário de eventos** | Print 2 | Médio. Só faz sentido com aulas ao vivo ou eventos (fora do escopo da v1) | Versão 2 |
| **Assistente de IA** | Item de menu no print 1 | Grande. A arquitetura prevê IA, mas o UNY-DOC-001 a deixou fora da v1 | Versão 2 |
| **Notificações** (contador no perfil) | Print 1 | Pequeno a médio. Na v1 poderiam ser só avisos do sistema e do parceiro (certificado liberado, curso novo) | **A decidir** |
| **FAQ e suporte no menu** | Print 1 | Pequeno. Já atendido pelo menu configurável (páginas e links) | **Versão 1** |
| **Comentários e dúvidas por aula** | Print 5 | Médio. Respostas encadeadas, moderação, avisos para a equipe, LGPD | **Fase 2 (P1)** **[A CONFIRMAR]** |
| **Curtir e não curtir** e **marcar como visto** | Print 4 | Pequeno | **Versão 1 (P1)** |
| **Aula em áudio** | Print 4 | Pequeno. Mesmo mecanismo de vídeo | **Versão 1** |
| **Tarefas e provas** | Seu pedido | Grande, mas isolado e opcional por curso (seção 8.10) | **Fase 2**, desligadas por padrão em cada curso |
| **Troféu** (gamificação) | Print 3 | Médio. Pontos, conquistas e ranking | Versão 2 |

**Gancho de arquitetura:** para não bloquear essas áreas, o menu e o ambiente tratam recursos como **módulos ativáveis por parceiro** (guardado em `configuracoes_ambiente`, UNY-DOC-005, seção 13.3). Na v1, o conjunto de módulos opcionais está vazio, mas o desenho já comporta o crescimento.

### 8.10 Provas e tarefas (componentes)

> Estes componentes só aparecem em cursos em que o parceiro ligou a opção "Este curso tem tarefas e provas" (BR-AVA-015). Entram na **fase 2**.

**Na lista de conteúdo**

| Item | Representação |
|---|---|
| Prova | Ícone de prancheta; estado: pendente, em andamento, aprovada (com nota), reprovada |
| Tarefa | Ícone de documento; estado: pendente, enviada, aprovada, devolvida, reprovada |
| Obrigatória | Selo "obrigatória" |
| Bloqueada | Ícone de cadeado e o motivo (data ou "aprove a prova anterior") |

**Tela da prova**

```text
┌──────────────────────────────────────────────────────────────┐
│ Prova do módulo 2          ⏱ 24:10 restantes   [Enviar]      │
├────────────────────────────────────┬─────────────────────────┤
│ Questão 3 de 10          peso 1,0  │ Questões                │
│                                    │ ① ② ③ ④ ⑤ ⑥ ⑦ ⑧ ⑨ ⑩   │
│ Enunciado…                         │ ● respondida ○ pendente │
│                                    │                         │
│ ( ) Alternativa A                  │                         │
│ (•) Alternativa B                  │                         │
│ ( ) Alternativa C                  │                         │
│                                    │                         │
│ [◂ Anterior]        [Próxima ▸]    │                         │
└────────────────────────────────────┴─────────────────────────┘
```

| Item | Regra |
|---|---|
| Cronômetro | Visível e discreto; **não** é anunciado continuamente a leitores de tela (anuncia em marcos: 10, 5 e 1 minuto) |
| Salvamento | Indicador "salvo" a cada resposta |
| Mapa de questões | Grade clicável com estado de cada questão |
| Envio | Confirmação antes de enviar, avisando quantas questões estão em branco |
| Resultado | Nota, aprovado ou reprovado, tentativas restantes, gabarito conforme a regra |
| Cor | Acerto e erro **nunca só por cor**: ícone e texto junto |

**Tela da tarefa**

| Bloco | Conteúdo |
|---|---|
| Enunciado | Texto e materiais |
| Prazo | Data e hora, com estado ("faltam 2 dias", "em atraso") |
| Envio | Área de soltar arquivo, campo de texto ou de link, conforme permitido; progresso de envio; lista dos arquivos enviados |
| Histórico | Envios anteriores, nota, **feedback** da equipe |
| Ações | Enviar, reenviar (quando permitido) |

**Para a equipe (correção):** tela dividida com a entrega à esquerda e a correção à direita (UNY-DOC-003, seção 5.12), ações em botões grandes (aprovar, devolver, reprovar), e atalhos para a próxima entrega da fila.

### 8.11 Acessibilidade do conjunto

| Tema | Requisito |
|---|---|
| Menu lateral | Marcado como navegação; item atual identificado; recolher e abrir por teclado |
| "Pular para o conteúdo" | Link no início de cada tela |
| Rótulos cortados | Dica com o texto completo, também por foco de teclado |
| Cards | O nome acessível é o **título em texto**, nunca a imagem |
| Provas | Cronômetro sem anúncio contínuo; mapa de questões navegável por teclado; resposta marcada indicada por ícone, não só por cor |
| Comentários | Estrutura de respostas anunciada (nível e autor) |
| Rolagem horizontal | Setas acessíveis e navegação por teclado entre cards |
| Botão de ajuda | Alcançável por teclado e não cobre controles do player |

## 9. Certificado (modelos visuais)

Alinhado ao UNY-DOC-005, seção 11.

| Item | Regra |
|---|---|
| Formato | A4 em paisagem |
| Elementos livres | Fundo, textos, imagens, assinaturas (imagem), campos dinâmicos |
| Elementos **obrigatórios e protegidos** | **Hash de validação e QR Code** (gerados pelo painel) |
| Margem de segurança | O QR Code e o hash ficam dentro de uma área protegida; o editor impede sobrepor outro elemento |
| Tamanho mínimo do QR Code | Definido para leitura confiável em impressão; **validar na implementação** |
| Contraste | O QR Code é sempre escuro sobre claro, mesmo que o fundo do modelo seja colorido (o editor aplica uma base clara atrás dele) |
| Modelos iniciais | Um por tema base (Cinema, Estúdio, Foco) |

## 10. Acessibilidade

Alvo: **WCAG 2.2 nível AA**.

| Tema | Requisito |
|---|---|
| Contraste | Seção 4.4 e validação automática da seção 6.2 |
| Teclado | Todo o ambiente navegável sem mouse; ordem de foco lógica; atalhos do player |
| Leitor de tela | Elementos com nome acessível; progresso e estados anunciados |
| Vídeo | Suporte a **legendas** quando o provedor oferecer; controle de velocidade |
| Alvos de toque | Mínimo 44 × 44 px |
| Movimento | Respeita movimento reduzido |
| Formulários | Rótulos visíveis, erros descritos em texto (não só por cor) |
| Cor | Nunca é o único indicador de estado (sempre ícone ou texto junto) |

## 11. Desempenho visual

| Tema | Meta proposta |
|---|---|
| Imagens | Formatos modernos, versões em vários tamanhos, carregamento tardio fora da tela |
| Capas | Tamanho e peso máximos no envio; redimensionamento automático |
| Fontes | Hospedadas localmente, apenas os pesos usados |
| Primeira tela | Esqueletos imediatos; conteúdo sem esperar imagens |
| Meta | Carregamento rápido em celular com conexão média (BR-NFR-001) |

## 12. Entregáveis de design

Como o design vem antes do código, os entregáveis sugeridos são:

| # | Entregável | Observação |
|---|---|---|
| 1 | **Biblioteca de componentes** no Figma, com variáveis para os tokens | Sua principal ferramenta; os nomes dos tokens seguem a seção 5.4 |
| 2 | **Telas-chave** nos três temas: home, página do curso, player, "Complete seu cadastro", meus certificados | Começar pelo tema Cinema |
| 3 | **Modelos de certificado** (um por tema) | |
| 4 | **Arquivo de tokens** (JSON) exportado do Figma | Alimenta o código e o editor do parceiro |
| 5 | **Guia de uso** curto (esta seção e a 6, em formato visual) | Para novos parceiros |

## 13. Decisões e verificações pendentes

| # | Item | Tipo | Impacto |
|---|---|---|---|
| 1 | ~~Print da tela de aula~~ **Recebido** (prints 3 a 5). Seção 8.8 atualizada | Resolvida | Seção 8.8 |
| 2 | ~~Tarefas e provas na v1~~ **Resolvida:** opcionais por curso, **fase 2**, fora do caminho crítico do lançamento | Resolvida | Seção 8.10 |
| 3 | **Comentários por aula** na fase 2 (proposta: P1) | Decisão | Seção 8.9 |
| 4 | **Comunidade, ranking e calendário** na v1 ou na v2 (proposta: v2) | Decisão | Seção 8.9 |
| 5 | **Assistente de IA** na v1 ou na v2 (proposta: v2) | Decisão | Seção 8.9 |
| 6 | **Troféu e gamificação** (proposta: v2) | Decisão | Seção 8.9 |
| 7 | **Notificações** na v1 (avisos simples) ou depois | Decisão | Seção 8.9 |
| 8 | Vitrine **"Módulos de um curso"** na v1 (proposta: P1) | Decisão | Seção 8.6, banco |
| 9 | Capa vertical 2:3 como padrão (proposta) e capa 16:9 opcional | Decisão | Seções 7.2 e 8.7 |
| 10 | Medidas, formatos e pesos das imagens (seção 8.7) | Verificação | Envio de arquivos |
| 11 | Limite de itens no menu (proposta: 8) e tamanho do rótulo (24) | Decisão | Seção 8.4 |
| 12 | Direção visual padrão (proposta: **Cinema**) e se os três temas base entram na v1 | Decisão | Seção 4 |
| 13 | Claro **e** escuro já na v1 ou só um modo por tema | Decisão | BR-CUS-004 |
| 14 | Fontes hospedadas no servidor (proposta) e lista curada de pares | Decisão | Seção 5.3 |
| 15 | Presença da marca da plataforma e da Faculdade Unypública nos ambientes | Decisão | Seção 6.4 |
| 16 | Painel do parceiro com identidade da plataforma (proposta) ou temático | Decisão | Seção 3 |
| 17 | Tamanho mínimo do QR Code no certificado impresso | Verificação | Seção 9 |
| 18 | Vídeo: o provedor escolhido oferece legendas e controle de velocidade? | Verificação | Seção 10 |
| 19 | Áudio: hospedar no servidor ou em provedor externo (peso e banda) | Decisão | Seção 8.8, infraestrutura |

## 14. Rastreabilidade

| Requisito | Onde este documento atende |
|---|---|
| BR-CUS-001 a 006 (personalização) | Seções 4 a 6 |
| BR-CUS-008, FS-REG-031 (elementos obrigatórios) | Seções 5.2 e 9 |
| BR-CUS-009 a 012 (menu, ajuda, capas, módulos) | Seções 7.2, 8.4 a 8.7 |
| BR-STU-013 a 015 (comentários, visto, navegação) | Seção 8.8 |
| BR-AVA (tarefas e provas) | Seção 8.10 |
| FS-ADM-050, 051 (aparência, contraste) | Seções 5.1, 6.2, 6.3 |
| FS-STU-010 a 012 (home) | Seção 7.2 |
| FS-STU-040 a 043 (player) | Seção 8 |
| BR-STU-006 (celular) | Seções 2, 7.1 e 8.2 |
| BR-CER-006, FS-CER-010 (modelo de certificado) | Seção 9 |
| BR-NFR-001 (desempenho) | Seção 11 |

## 15. Próximo documento

**UNY-DOC-008 · Infraestrutura, Segurança e Deploy:** configuração do servidor (Nginx, PHP, banco, certificados), rotina de deploy, backups, monitoramento, plano de homologação e checklist de segurança, fechando o conjunto de documentos antes do código.
