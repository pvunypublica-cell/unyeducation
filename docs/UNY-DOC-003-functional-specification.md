# UNY-DOC-003 · Functional Specification

**Projeto:** UnyEducation
**Versão:** 0.2 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Depende de:** UNY-DOC-001 (Product Vision), UNY-DOC-002 (Business Requirements)

---

## 1. Como ler este documento

Este documento descreve **o que cada tela e fluxo faz**. Cada item funcional tem um código `FS-AREA-000` e aponta para o requisito de negócio que o originou (`BR-...`). Decisões de arquitetura, banco e API ficam nos documentos seguintes.

Convenções:

- **Obrigatório** = a tela não funciona sem o item
- **Configurável** = o parceiro controla
- **Estados** = situações em que a tela precisa se comportar de forma diferente (vazio, carregando, erro)

## 2. Superfícies do produto

| Superfície | Quem usa | Domínio |
|---|---|---|
| **Ambiente do aluno** | Alunos | Domínio ou subdomínio do parceiro |
| **Painel do parceiro** | Equipe do parceiro | `/admin` no domínio do parceiro, ou endereço central de painel |
| **Painel do operador** | Equipe da plataforma | Endereço central, separado |
| **Páginas públicas** | Qualquer pessoa | Validação de certificado e páginas institucionais do parceiro |

## 3. Papéis e permissões

### 3.1 Papéis

| Papel | Escopo |
|---|---|
| Operador | Plataforma inteira |
| Administrador do parceiro | Todo o ambiente do parceiro |
| Instrutor | Seus cursos, alunos desses cursos |
| Suporte | Consulta e ajuda a alunos, sem alterar conteúdo nem aparência |
| Aluno | Seu próprio conteúdo e progresso |

### 3.2 Matriz de permissões

| Ação | Operador | Admin parceiro | Instrutor | Suporte | Aluno |
|---|---|---|---|---|---|
| Criar e suspender parceiros | ✅ | | | | |
| Configurar aparência e home | | ✅ | | | |
| Configurar integrações de pagamento | | ✅ | | | |
| Gerenciar equipe | | ✅ | | | |
| Criar e editar cursos | | ✅ | ✅ (os seus) | | |
| Publicar curso | | ✅ | ✅ (os seus, se permitido) | | |
| Ver lista de alunos | | ✅ | ✅ (dos seus cursos) | ✅ | |
| Matricular manualmente | | ✅ | | ✅ | |
| Revogar certificado | | ✅ | | | |
| Corrigir tarefas | | ✅ | ✅ (os seus) | | |
| Responder e moderar comentários | | ✅ | ✅ (os seus) | ✅ | |
| Ver relatórios | | ✅ | ✅ (dos seus cursos) | | |
| Assistir cursos matriculados | | | | | ✅ |
| Acesso de suporte a um parceiro | ✅ (com registro) | | | | |

**FS-PER-001** · O sistema verifica a permissão em toda ação, nunca apenas escondendo botões. *(BR-TEN-002, BR-ADM-005)*
**FS-PER-002** · Todo acesso do operador a dados de um parceiro gera registro de auditoria. *(BR-OPR-004)*

---

## 4. Ambiente do aluno

### 4.1 Acesso (login e cadastro)

| Item | Comportamento |
|---|---|
| Campos | E-mail; senha ou link mágico |
| Primeiro acesso após compra | O aluno recebe e-mail com link para definir senha ou entrar direto |
| Recuperação de senha | Link por e-mail, com prazo de validade |
| Visual | Logo, cores e textos do parceiro |
| Estados | Credenciais inválidas, conta sem matrícula ativa, link expirado |

**FS-STU-001** · O aluno entra por e-mail com link mágico ou senha. *(BR-STU-007)*
**FS-STU-002** · Compra aprovada cria a conta do aluno (se não existir) e envia e-mail de acesso. *(BR-STU-008, BR-PAY-001)*
**FS-STU-003** · Conta sem matrícula ativa vê o catálogo do parceiro, mas não acessa as aulas. *(BR-PAY-002)*
**FS-STU-004** · No primeiro acesso, se faltar o CPF, o aluno vê a tela "Complete seu cadastro", com CPF validado na hora e o aviso de que ele é necessário para o certificado. *(BR-STU-010, BR-STU-011)*
**FS-STU-005** · Enquanto faltar o CPF, um lembrete discreto aparece na home e, ao concluir um curso, o aluno é levado a informá-lo para liberar o certificado. As aulas continuam acessíveis. *(BR-STU-012)*

### 4.2 Home

Ordem vertical padrão (o parceiro pode reordenar, exceto o item fixo):

1. **Banner de destaque** (configurável)
2. **Continuar assistindo** (fixo, aparece só se houver progresso)
3. **Vitrines** (configuráveis, ver seção 5.5)
4. **Rodapé** (links institucionais do parceiro)

| Elemento | Comportamento |
|---|---|
| Banner | Imagem, título, texto curto e botão (leva a curso, trilha ou link) |
| Continuar assistindo | Cards com progresso; clique abre direto na aula onde parou |
| Vitrine | Linha horizontal com rolagem; cada card mostra capa (vertical por padrão), título e progresso, se houver |
| Card de curso bloqueado | Aparece com identificação de "não incluído"; clique leva à página do curso |

**Estados:** primeira visita (sem progresso), catálogo vazio, carregando (esqueleto dos cards), erro de carregamento com botão de tentar de novo.

**FS-STU-010** · Home exibe banner, continuar assistindo e vitrines conforme configuração do parceiro. *(BR-STU-001, BR-CUS-002)*
**FS-STU-011** · "Continuar assistindo" retoma a última aula no ponto em que o aluno parou. *(BR-STU-002)*
**FS-STU-012** · A home funciona bem em celular, com rolagem horizontal por toque. *(BR-STU-006)*
**FS-STU-013** · O ambiente tem menu lateral recolhível no computador e menu inferior no celular, com os itens definidos pelo parceiro. *(BR-CUS-009)*
**FS-STU-014** · Um botão flutuante de ajuda, se ativado pelo parceiro, leva ao destino configurado e se recolhe durante a reprodução do vídeo. *(BR-CUS-010)*

### 4.3 Busca

| Item | Comportamento |
|---|---|
| Campo | No menu lateral (computador e tablet) ou na barra superior (celular), em todas as telas |
| Resultados | Cursos e aulas, agrupados |
| Filtros | Categoria, gratuito/pago, "meus cursos" |
| Estados | Sem resultados com sugestão de categorias |

**FS-STU-020** · A busca encontra cursos e aulas pelo título e descrição. *(BR-STU-004)*

### 4.4 Página do curso

| Bloco | Conteúdo |
|---|---|
| Cabeçalho | Capa, título, instrutor, carga horária estimada, trailer opcional |
| Ação principal | **Continuar** / **Começar** (matriculado) ou **Quero este curso** (não matriculado) |
| Descrição | Texto do curso, o que o aluno vai aprender |
| Conteúdo | Módulos e aulas, com marcação de concluída, bloqueada ou disponível |
| Certificado | Indicação de que o curso emite certificado, se aplicável |

**FS-STU-030** · Aluno matriculado vê módulos e aulas com progresso. *(BR-STU-003)*
**FS-STU-031** · Aluno não matriculado vê a descrição e o botão de compra, que leva ao checkout configurado pelo parceiro. *(BR-PAY-003)*
**FS-STU-032** · Aulas com liberação progressiva mostram a data ou a condição de liberação. *(BR-CAT-006)*
**FS-STU-033** · Cursos gratuitos permitem matrícula com um clique, se o parceiro habilitar. *(BR-CAT-007)*

### 4.5 Player da aula

| Elemento | Comportamento |
|---|---|
| Vídeo | Controles de reprodução, velocidade, tela cheia, qualidade |
| Retomada | Abre no ponto em que parou |
| Navegação | Aula anterior e próxima; próxima automática ao terminar, com contagem de poucos segundos e opção de cancelar |
| Lista lateral | Módulos e aulas do curso, com progresso |
| Materiais | Downloads vinculados à aula |
| Texto | Aulas em texto ocupam a área principal |
| Marcar como concluída | Automático ao assistir quase todo o vídeo; botão manual para aulas em texto |

**FS-STU-040** · O progresso do vídeo é salvo continuamente, mesmo se a pessoa fechar a página. *(BR-STU-002)*
**FS-STU-041** · Aula de vídeo é marcada como concluída ao atingir o percentual mínimo de visualização definido pela plataforma (padrão: 90%). *(BR-STU-003)*
**FS-STU-042** · Aula em texto é concluída por ação do aluno. *(BR-CAT-002)*
**FS-STU-043** · Conteúdo só é entregue a quem tem matrícula ativa. *(BR-PAY-002, BR-NFR-002)*

### 4.6 Meus cursos e perfil

| Tela | Conteúdo |
|---|---|
| Meus cursos | Cursos matriculados, em andamento e concluídos, com progresso |
| Minha lista | Cursos favoritados |
| Certificados | Certificados liberados, com download e QR Code de validação; cursos concluídos com certificado em preparação |
| Perfil | Nome, e-mail, senha, foto, preferências de e-mail |
| Privacidade | Exportar meus dados, solicitar exclusão |

**FS-STU-050** · O aluno vê todos os seus cursos e certificados em um só lugar. *(BR-STU-003, BR-CER-001)*
**FS-STU-051** · O aluno pode exportar e solicitar a exclusão dos próprios dados. *(BR-NFR-004)*
**FS-STU-052** · Curso concluído com certificado retido mostra "Certificado em preparação", sem explicar o motivo comercial. *(BR-CER-009, RN-CER-05)*

### 4.7 Validação pública de certificado

O certificado leva o **hash e o QR Code gerados pelo painel de matrículas**. A validação pública usa a página de validação do painel/faculdade, que o AVA não replica na versão 1 **[A CONFIRMAR]**.

| Resultado na validação | Origem |
|---|---|
| Válido, revogado ou não encontrado | Painel, que recebe do AVA os eventos de emissão e revogação |

**FS-CER-001** · O QR Code do certificado leva à validação pública do painel, que reflete o estado atual (válido ou revogado). *(BR-CER-003, BR-CER-004)*

### 4.8 Página da aula: complementos

| Elemento | Comportamento |
|---|---|
| **Trilha de navegação** | Início > Curso > Aula, com links |
| **Tipos de conteúdo** | Vídeo, **áudio** (player nativo com velocidade), texto, material para download (nome e tamanho do arquivo), prova e tarefa |
| **Blocos da aula** | O parceiro pode combinar, na mesma aula, texto, áudio e materiais, cada bloco com um título |
| **Marcar como vista** | Botão que alterna entre "Marcar como vista" e "Desmarcar como visto" |
| **Avaliar a aula** | Curtir e não curtir |
| **Navegação** | Cartões "aula anterior" e "próxima aula", com capa e título |
| **Conteúdo do curso** | Coluna lateral com o **progresso geral** ("X de Y aulas", com indicador circular) e **um cartão por módulo** com indicador circular e contagem de aulas; o módulo atual abre mostrando as aulas, com marca de concluída, aula atual e pendente |
| **Certificado na lista** | Último item da lista de conteúdo: bloqueado (curso incompleto), **em preparação** (retido) ou **disponível** |
| **Comentários** | Seção abaixo da aula: dúvidas e respostas encadeadas, com selo para a equipe |

**FS-STU-044** · A aula de áudio usa player com controle de velocidade, e o ponto de reprodução é salvo como no vídeo. *(BR-CAT-002, BR-STU-002)*
**FS-STU-045** · O aluno marca e desmarca uma aula como vista; aulas de prova e tarefa **não** podem ser marcadas manualmente. *(BR-STU-014)*
**FS-STU-046** · O aluno curte ou não curte uma aula, e o parceiro vê o resultado por aula. *(BR-STU-014)*
**FS-STU-047** · A página da aula mostra "aula anterior" e "próxima aula" como cartões com capa. *(BR-STU-015)*
**FS-STU-048** · A coluna "Conteúdo do curso" mostra progresso geral, um cartão por módulo e o módulo atual expandido. *(BR-STU-003, BR-STU-015)*
**FS-STU-049** · O aluno comenta uma aula e responde a comentários; respostas da equipe aparecem com selo. *(BR-STU-013)*
**FS-CER-020** · O certificado aparece como último item da lista de conteúdo, com o estado correspondente (bloqueado, em preparação, disponível). *(BR-CER-009)*

### 4.9 Provas e tarefas (aluno)

**Prova**

| Etapa | Comportamento |
|---|---|
| Instruções | Nota de aprovação, tentativas restantes, tempo limite, período de abertura, se é obrigatória |
| Tentativa | Botão "Iniciar"; o tempo passa a contar; navegação entre questões com indicação das respondidas |
| Salvamento | Respostas salvas automaticamente |
| Envio | Manual ou automático ao fim do tempo, com as respostas já salvas |
| Resultado | Nota, aprovado ou reprovado, tentativas restantes; gabarito conforme a regra do parceiro |

**Tarefa**

| Etapa | Comportamento |
|---|---|
| Enunciado | Texto, anexos e prazo |
| Entrega | Arquivo, texto ou link, conforme o tipo definido; validação de tipo e tamanho; barra de envio |
| Acompanhamento | Status, histórico de envios, nota e feedback da equipe |
| Reenvio | Quando devolvida para ajuste ou quando a regra permitir |

**FS-AVA-001** · A prova mostra as instruções (nota de aprovação, tentativas restantes, tempo, prazo) **antes** de iniciar. *(BR-AVA-004, BR-AVA-009)*
**FS-AVA-002** · Ao iniciar, a tentativa é registrada e o tempo continua a contar **mesmo que o aluno saia**; ao esgotar, a prova é enviada com as respostas salvas. *(BR-AVA-004)*
**FS-AVA-003** · As respostas são salvas automaticamente durante a tentativa. *(BR-AVA-003)*
**FS-AVA-004** · Ao enviar, a prova é corrigida automaticamente e mostra nota, resultado e tentativas restantes; o gabarito segue a regra do parceiro (nunca, após aprovação ou após esgotar as tentativas). *(BR-AVA-003, BR-AVA-004, BR-AVA-009)*
**FS-AVA-005** · Reprovado com tentativas restantes, o aluno vê "Tentar novamente"; sem tentativas, vê uma mensagem com orientação para falar com o suporte. *(BR-AVA-004)*
**FS-AVA-006** · A tarefa mostra enunciado, anexos e prazo, e oferece a área de envio conforme os tipos de entrega permitidos. *(BR-AVA-005)*
**FS-AVA-007** · Depois do envio, o status é "Enviada, aguardando correção"; o aluno vê o histórico, a nota e o feedback, e pode reenviar quando devolvida ou quando a regra permitir. *(BR-AVA-005, BR-AVA-009)*
**FS-AVA-008** · Na lista de conteúdo, cada prova e tarefa mostra ícone e estado (pendente, em andamento, enviada, aprovada com nota, reprovada, devolvida). *(BR-AVA-009)*
**FS-AVA-009** · Avaliações obrigatórias levam um selo "obrigatória" e entram no progresso do curso. *(BR-AVA-002, BR-AVA-008)*

---

## 5. Painel do parceiro

### 5.1 Visão geral (dashboard)

Resumo com: matrículas recentes, alunos ativos, conclusões, cursos mais acessados e alertas (integração com falha, certificados retidos, cursos em rascunho há muito tempo).

**FS-ADM-001** · O dashboard exibe os principais números e alertas do ambiente. *(BR-ADM-006, BR-OPR-005)*

### 5.2 Cursos

**Lista:** título, status, categoria, alunos, última edição. Filtros por status e categoria.

**Editor de curso** (em abas):

| Aba | Conteúdo |
|---|---|
| Informações | Título, descrição, capa, trailer, categoria, instrutor |
| Conteúdo | Módulos e aulas, com arrastar para reordenar |
| Acesso | Gratuito ou pago, produto do checkout vinculado, regras de liberação |
| Avaliações | Opção **"Este curso tem tarefas e provas"**, desligada por padrão. Ligada, o editor passa a oferecer os tipos Prova e Tarefa e a opção de exigi-las para o certificado |
| Certificado | Ativar, regra de emissão, modelo |
| Publicação | Status e pré-visualização como aluno |

**Editor de aula:** título, tipo (vídeo, texto), conteúdo, materiais anexos, duração, regra de liberação.

**Estados do curso:** `rascunho` → `publicado` → `arquivado`. Curso arquivado não aparece para novos alunos, mas quem já está matriculado mantém acesso.

**FS-ADM-010** · O parceiro cria, edita, reordena e arquiva cursos, módulos e aulas. *(BR-CAT-001, BR-CAT-005, BR-ADM-001)*
**FS-ADM-011** · O parceiro pré-visualiza o curso como aluno antes de publicar. *(BR-CAT-005)*
**FS-ADM-012** · O parceiro vincula o curso a um produto do checkout configurado. *(BR-PAY-003)*
**FS-ADM-013** · O parceiro define regras de liberação progressiva por data ou por conclusão da aula anterior. *(BR-CAT-006)*

### 5.3 Alunos

**Lista:** nome, e-mail, cursos, origem da matrícula, status, progresso, último acesso. Busca e filtros.

**Ficha do aluno:** dados, matrículas, progresso por curso, certificados, histórico de acessos e de ações administrativas.

**Ações:** matricular, suspender, reativar, redefinir senha, reenviar e-mail de acesso.

**FS-ADM-020** · A lista de alunos mostra a origem de cada matrícula (nativa, genérica, manual, código, importação). *(BR-ADM-002, RN-PAY-02)*
**FS-ADM-021** · Toda ação sobre um aluno é registrada com responsável e data. *(BR-TEN-007)*
**FS-ADM-022** · O CPF aparece **mascarado** nas listas; o valor completo só é exibido a quem tem permissão e cada visualização é registrada. *(BR-STU-011)*

### 5.4 Matrículas manuais, importação e códigos

| Função | Comportamento |
|---|---|
| **Matrícula manual** | Escolhe aluno (existente ou novo por e-mail), curso(s), motivo (campo obrigatório) e expiração (opcional) |
| **Importação em lote** | Envia planilha com e-mail, nome e curso; sistema valida e mostra prévia antes de confirmar; relatório de erros ao final |
| **Códigos de acesso** | Cria código ou link para um curso, com limite de usos e prazo; aluno resgata e entra |

**FS-ADM-030** · Matrícula manual exige motivo e registra o responsável. *(BR-PAY-006)*
**FS-ADM-031** · Matrícula manual pode ter expiração; ao expirar, o acesso é encerrado automaticamente. *(BR-PAY-007)*
**FS-ADM-032** · A importação mostra uma prévia e nunca grava sem confirmação. *(BR-PAY-008)*
**FS-ADM-033** · Códigos de acesso têm limite de usos e prazo. *(BR-PAY-009)*
**FS-ADM-034** · O formulário de matrícula manual, a planilha de importação e o resgate de código aceitam CPF; sem CPF, a matrícula é criada e o aluno é solicitado a informá-lo. *(BR-STU-010, BR-STU-012)*

### 5.5 Home e vitrines

**Gerenciador de vitrines:** lista ordenável com arrastar, ativar/desativar e editar cada vitrine.

**Tipos de vitrine:**

| Tipo | O que mostra |
|---|---|
| Seleção manual | Cursos escolhidos pelo parceiro |
| Por categoria | Cursos de uma categoria |
| Trilha | Cursos em sequência |
| Novidades | Cursos publicados mais recentemente |
| Mais assistidos | Cursos com mais acessos no período |
| Gratuitos | Cursos gratuitos |
| Módulos de um curso | Cada card é um módulo de um curso escolhido (P1) |

**Banner:** imagem para computador e celular, título, texto, botão e destino. Pode haver mais de um, em rotação.

**Editor visual (P1):** pré-visualização em tempo real da home, em modos computador e celular, com arrastar e soltar.

**FS-ADM-040** · O parceiro cria, ordena, ativa e desativa vitrines. *(BR-CUS-002)*
**FS-ADM-041** · O parceiro configura banners com versões para computador e celular. *(BR-CUS-003)*
**FS-ADM-042** · O editor mostra pré-visualização em tempo real antes de publicar as alterações. *(BR-CUS-006)*
**FS-ADM-043** · Alterações da home ficam em rascunho até o parceiro publicar. *(BR-CUS-006)*
**FS-ADM-044** · O parceiro configura o menu lateral (itens, ícones, ordem, destino) e o botão de ajuda, com pré-visualização. *(BR-CUS-009, BR-CUS-010)*

### 5.6 Aparência

| Grupo | Campos |
|---|---|
| Identidade | Logo (claro e escuro), favicon, nome do ambiente |
| Cores | Cor principal, secundária, fundo, texto |
| Tipografia | Fonte para títulos e para texto, dentre uma lista oferecida |
| Modo | Claro, escuro ou ambos |
| Páginas | Sobre, contato, termos, privacidade |
| E-mails | Logo, cor, remetente, rodapé |

A pré-visualização é imediata. O sistema avisa quando uma combinação de cores prejudica a leitura.

**FS-ADM-050** · O parceiro altera identidade, cores e fontes com pré-visualização. *(BR-CUS-001)*
**FS-ADM-051** · O sistema alerta sobre baixo contraste entre texto e fundo. *(BR-CUS-001)*
**FS-ADM-052** · Páginas institucionais simples são editáveis pelo parceiro. *(BR-CUS-007)*

### 5.7 Integrações de pagamento

**Tela principal:** lista de integrações do parceiro, com status (ativa, com erro, desativada).

**Fluxo de configuração (nível 1, nativo):**

1. Escolher a plataforma de venda
2. Informar credenciais ou chave
3. Copiar o endereço de webhook gerado e colar na plataforma de venda
4. Testar a conexão (o sistema espera um evento de teste e confirma)
5. Vincular produtos da plataforma de venda aos cursos

**Fluxo de configuração (nível 2, genérico):**

1. Criar integração genérica e copiar o endereço de webhook
2. Colar um exemplo de evento recebido
3. Mapear os campos: e-mail do aluno, nome, identificador do produto, status
4. Definir quais status significam aprovado, reembolsado e cancelado
5. Vincular produtos aos cursos

**Nível 3 (manual):** não exige configuração; usa as funções da seção 5.4.

**Registro de eventos:** lista de tudo o que chegou, com data, integração, resultado (processado, ignorado, com erro) e detalhes técnicos. Eventos com erro podem ser reprocessados.

**FS-PAY-001** · O parceiro configura integrações nativas informando credenciais e vinculando produtos a cursos. *(BR-PAY-003, BR-ADM-004)*
**FS-PAY-002** · O parceiro configura integração genérica com mapeamento de campos e de status. *(BR-PAY-010)*
**FS-PAY-003** · Todo evento recebido é registrado, com resultado e possibilidade de reprocessamento. *(BR-PAY-004)*
**FS-PAY-004** · Segredos de integração não são exibidos novamente depois de salvos. *(RN-PAY-05)*
**FS-PAY-005** · Evento duplicado é reconhecido e ignorado sem criar nova matrícula. *(BR-PAY-005)*
**FS-PAY-006** · O painel de conciliação lista compras recebidas, matrículas criadas e divergências. *(BR-PAY-012)*

### 5.8 Certificados

**Modelos:** lista de modelos do parceiro; um pode ser o padrão e cada curso pode usar o seu.

**Editor de modelo (P1):**

| Item | Comportamento |
|---|---|
| Fundo | Imagem ou cor |
| Elementos | Texto, imagem, assinatura (imagem), campos dinâmicos |
| Campos dinâmicos | Nome do aluno, curso, carga horária, data de conclusão, código de validação |
| Elementos obrigatórios | QR Code e código de validação, não removíveis |
| Pré-visualização | Com dados de exemplo |

**Emissão e liberação:** o certificado é gerado quando o aluno cumpre a regra do curso, mas só é **liberado** se o painel confirmar que o parceiro está em dia e devolver o hash e o QR Code. Caso contrário, fica **retido**.

**Certificados retidos:** tela do parceiro com a lista de certificados aguardando liberação, o motivo (ex.: assinatura ou matrículas fora de dia, aguardando resposta do painel) e atalho para regularizar no painel.

**Regras de emissão (por curso):** percentual de aulas concluídas e, opcionalmente, **aprovação em todas as avaliações obrigatórias** (provas e tarefas, seção 5.12).

**Revogação:** administrador revoga com motivo; o certificado continua existindo, marcado como revogado.

**FS-CER-010** · O parceiro cria modelos de certificado com campos dinâmicos e elementos obrigatórios protegidos. *(BR-CER-002, BR-CER-006, BR-CUS-008)*
**FS-CER-011** · O certificado é gerado automaticamente ao cumprir a regra do curso e liberado conforme FS-CER-014. *(BR-CER-001)*
**FS-CER-012** · Revogação exige motivo e mantém o histórico. *(BR-CER-004)*
**FS-CER-013** · Segunda via preserva o mesmo hash de validação. *(BR-CER-005)*
**FS-CER-014** · A liberação depende da situação do parceiro e da matrícula informada pelo painel. *(BR-CER-008, RN-CER-02)*
**FS-CER-015** · Concluído sem liberação, o certificado fica retido, com motivo visível só para parceiro e operador. *(BR-CER-009, RN-CER-05)*
**FS-CER-016** · Quando o parceiro regulariza, os retidos são liberados automaticamente e os alunos recebem e-mail. *(BR-CER-010)*
**FS-CER-017** · O parceiro vê a lista de certificados retidos com o motivo. *(BR-CER-011)*
**FS-CER-018** · O operador libera ou retém um certificado manualmente, com motivo registrado. *(BR-CER-012)*
**FS-CER-019** · O certificado não é emitido sem o hash e o QR Code devolvidos pelo painel. *(BR-CER-003, RN-CER-04)*

### 5.9 Equipe

Lista de pessoas com papel e status. Convite por e-mail. Alteração e remoção de papel. O último administrador não pode ser removido.

**FS-ADM-060** · O administrador convida, altera e remove membros da equipe. *(BR-ADM-005)*

### 5.10 Relatórios

| Relatório | Informação |
|---|---|
| Matrículas | Por período, curso e origem |
| Engajamento | Alunos ativos, aulas assistidas, tempo médio |
| Conclusão | Taxa por curso, onde os alunos abandonam |
| Certificados | Emitidos e revogados |

Exportação em planilha.

**FS-ADM-070** · O parceiro consulta e exporta relatórios básicos. *(BR-ADM-006)*

### 5.11 Configurações do ambiente

Domínio (próprio ou subdomínio), dados do parceiro, política de acesso, idioma, e-mail de suporte. Para domínio próprio, a tela mostra as instruções de configuração de DNS e o status da verificação.

**FS-ADM-080** · O parceiro conecta um domínio próprio seguindo instruções guiadas e acompanha o status. *(BR-TEN-004)*

### 5.12 Avaliações: provas e tarefas

**Onde:** no editor do curso, ao adicionar uma aula, o parceiro escolhe o tipo **Prova** ou **Tarefa**.

**Editor de prova**

| Bloco | Conteúdo |
|---|---|
| Configurações | Obrigatória ou não, nota de aprovação, tentativas (ilimitadas ou número), tempo limite, período de abertura e encerramento, embaralhar questões e alternativas, regra de exibição do gabarito |
| Questões | Enunciado, tipo (uma escolha, várias escolhas, verdadeiro ou falso), alternativas com a(s) correta(s), peso, explicação mostrada no gabarito, ordem |
| Pré-visualização | Como aluno, **sem gerar tentativa real** |

**Editor de tarefa**

| Bloco | Conteúdo |
|---|---|
| Enunciado | Texto e anexos |
| Entrega | Tipos permitidos (arquivo, texto, link), quantidade e tamanho máximo dos arquivos, extensões permitidas |
| Regras | Obrigatória ou não, prazo, aceita entrega com atraso, número de reenvios, nota (opcional) ou apenas aprovado e reprovado |

**Fila de correção**

Lista de entregas aguardando correção, com filtros (curso, tarefa, aluno, estado, atrasadas) e ordenação por antiguidade. Cada linha leva à **tela de correção**:

```text
┌──────────────────────────────────┬──────────────────────────────┐
│ ENTREGA DO ALUNO                 │ CORREÇÃO                     │
│ Aluno, data, atraso?             │ Nota: [  ]  (se houver)      │
│ Texto / link                     │ Feedback: [____________]     │
│ Arquivos (baixar)                │                              │
│ Envios anteriores                │ ( Aprovar ) ( Devolver )     │
│                                  │ ( Reprovar )                 │
└──────────────────────────────────┴──────────────────────────────┘
```

**Resultados:** por avaliação, número de alunos, média, taxa de aprovação, tentativas médias; exportação em planilha.

**FS-AVA-010** · O parceiro cria e edita provas com questões, alternativas, pesos, explicações e configurações. *(BR-AVA-001, 003, 004)*
**FS-AVA-011** · O parceiro cria e edita tarefas com enunciado, anexos, tipos de entrega, limites e prazo. *(BR-AVA-001, 005)*
**FS-AVA-012** · A pré-visualização de uma avaliação não gera tentativa nem entrega reais. *(BR-AVA-001)*
**FS-AVA-013** · A fila de correção lista entregas pendentes com filtros e destaque para atrasadas. *(BR-AVA-007)*
**FS-AVA-014** · A tela de correção permite ver a entrega, dar nota, escrever feedback e decidir (aprovar, devolver para ajuste ou reprovar). O aluno é avisado por e-mail. *(BR-AVA-006)*
**FS-AVA-015** · Alterar uma nota ou decisão depois de registrada exige motivo e fica na auditoria. *(RN-AVA-05)*
**FS-AVA-016** · O parceiro consulta resultados por avaliação e exporta as notas em planilha. *(BR-AVA-010)*
**FS-AVA-017** · Depois da primeira tentativa, questões e alternativas **não podem ser excluídas**; mudar o gabarito pede confirmação e pode recalcular as tentativas já feitas, com registro na auditoria. *(BR-AVA-003)*
**FS-AVA-018** · O parceiro pode condicionar a liberação de uma aula à aprovação em uma avaliação. *(BR-AVA-011)*
**FS-AVA-019** · A opção "Este curso tem tarefas e provas" vem **desligada**. Desligada, o editor não oferece os tipos Prova e Tarefa, a lista de conteúdo não mostra avaliações e a regra do certificado ignora avaliações. *(BR-AVA-015)*
**FS-AVA-020** · Desligar a opção em um curso que já tem avaliações com tentativas ou entregas exige confirmação; os registros são **preservados** (as avaliações deixam de aparecer aos alunos, mas nada é apagado). *(BR-AVA-015, FS-REG-040)*

### 5.13 Comentários

Fila de comentários por curso e aula, com busca e filtro por "sem resposta". A equipe responde, oculta (com motivo) e pode desativar comentários em uma aula ou curso.

**FS-ADM-090** · O parceiro e a equipe respondem comentários, que aparecem com selo da equipe. *(BR-STU-013)*
**FS-ADM-091** · O parceiro oculta um comentário com motivo registrado e pode desativar comentários por aula ou curso. *(BR-STU-013)*
**FS-ADM-092** · A equipe é avisada de novos comentários sem resposta. *(BR-STU-013)*

---

## 6. Painel do operador

| Tela | Função |
|---|---|
| Parceiros | Criar, suspender, reativar; ver plano, uso e status |
| Planos | Definir limites (alunos, cursos, armazenamento) |
| Uso | Consumo por parceiro |
| Integrações | Integrações com falha em todos os parceiros |
| Auditoria | Registro global de ações administrativas |
| Suporte | Entrar em um ambiente de parceiro para dar suporte, com registro obrigatório |

**FS-OPR-001** · O operador cria um parceiro informando nome, domínio, plano e administrador inicial, que recebe convite por e-mail. *(BR-OPR-001)*
**FS-OPR-002** · Parceiro suspenso mantém seus dados, mas o ambiente do aluno exibe aviso de indisponibilidade. *(BR-TEN-003)*
**FS-OPR-003** · O acesso de suporte exige motivo e fica registrado. *(BR-OPR-004)*

---

## 7. Fluxos principais

### 7.1 Compra aprovada até o primeiro acesso

```text
Aluno compra no checkout do parceiro
        ↓
Plataforma de venda envia notificação
        ↓
Sistema valida a origem e registra o evento
        ↓
Evento duplicado? → sim: ignora
        ↓ não
Aluno já existe? → não: cria conta
        ↓
Cria matrícula (origem: nativo/genérico) com status ativa
        ↓
Envia e-mail de acesso com a marca do parceiro
        ↓
Aluno entra e vê o curso na home
```

### 7.2 Reembolso ou cancelamento

```text
Notificação de reembolso/cancelamento
        ↓
Registra o evento
        ↓
Localiza a matrícula correspondente
        ↓
Status → suspensa/cancelada
        ↓
Aluno perde acesso às aulas; progresso é mantido
        ↓
Certificado já emitido continua válido (regra padrão; parceiro pode optar por revogar)
```

### 7.3 Matrícula manual (ex.: pagamento por PIX direto)

```text
Parceiro recebe o pagamento fora da plataforma
        ↓
Equipe abre "Matricular alunos"
        ↓
Informa e-mail, curso, motivo (obrigatório) e expiração (opcional)
        ↓
Sistema cria matrícula (origem: manual) e registra o responsável
        ↓
Aluno recebe e-mail de acesso
```

### 7.4 Publicação de curso

```text
Rascunho → adiciona módulos e aulas → define acesso e certificado
        ↓
Pré-visualiza como aluno
        ↓
Sistema verifica pendências (sem aulas, sem capa, sem produto vinculado quando pago)
        ↓
Publica
        ↓
Curso aparece no catálogo e pode entrar em vitrines
```

### 7.5 Conclusão e liberação de certificado

```text
Aluno conclui a última aula
        ↓
Sistema avalia a regra do curso (ex.: 100% das aulas)
        ↓
Cumpriu? → não: nada acontece
        ↓ sim
Matrícula já espelhada no painel? → não: espelha agora (com reenvio)
        ↓ sim
AVA pede a liberação ao painel
        ↓
Painel responde:
  • Habilitado + hash e QR Code → gera o PDF → certificado liberado → aluno é avisado
  • Retido (parceiro/matrícula fora de dia) → certificado pendente
        → aluno vê "Certificado em preparação"
        → parceiro vê o certificado na lista de retidos
  • Sem resposta → certificado pendente, nova tentativa automática
        ↓
Parceiro regulariza no painel
        ↓
Pendentes são liberados automaticamente e os alunos avisados
```

### 7.6 Personalização da home

```text
Parceiro abre o editor da home
        ↓
Altera banners e vitrines (em rascunho)
        ↓
Pré-visualiza em modo computador e celular
        ↓
Publica
        ↓
Alunos veem a nova home imediatamente
```

### 7.7 Espelhamento da matrícula no painel

```text
Matrícula criada no AVA (qualquer origem)
        ↓
Matrícula ativa imediatamente: o aluno já pode estudar
        ↓
Tarefa em fila envia a matrícula ao painel
        ↓
Painel registra e devolve o identificador da matrícula
        ↓
AVA guarda o identificador
        ↓
Falha? → reenvio automático; aparece como divergência para operador e parceiro
```

### 7.8 Prova

```text
Aluno abre a prova
        ↓
Vê instruções e tentativas restantes
        ↓
Inicia → tentativa registrada, tempo começa
        ↓
Responde (salvamento automático)
        ↓
Envia (ou o tempo acaba e o envio é automático)
        ↓
Correção automática → nota → resultado da avaliação
        ↓
Aprovado → avaliação concluída → recalcula o progresso do curso
Reprovado → tentativas restantes? sim: pode tentar de novo / não: orientação
        ↓
Todas as obrigatórias aprovadas e aulas concluídas? → conclusão do curso (fluxo 7.5)
```

### 7.9 Tarefa

```text
Aluno abre a tarefa
        ↓
Envia texto, arquivo ou link
        ↓
Sistema valida tipo e tamanho, verifica vírus e registra a entrega
        ↓
Status "enviada" → entra na fila de correção
        ↓
Equipe corrige: nota, feedback e decisão
        ↓
Aprovar   → avaliação concluída → recalcula o progresso
Devolver  → aluno ajusta e reenvia
Reprovar  → conforme reenvios permitidos
        ↓
Aluno recebe e-mail com o resultado
```

---

## 8. Estados e regras funcionais

### 8.1 Matrícula

| Estado | Significado | Acesso |
|---|---|---|
| `ativa` | Pagamento aprovado ou liberação válida | Sim |
| `pendente` | Aguardando confirmação | Não |
| `suspensa` | Reembolso, chargeback ou ação do parceiro | Não |
| `expirada` | Prazo de acesso encerrado | Não |
| `cancelada` | Encerrada definitivamente | Não |

Regras:

- **FS-REG-001** · Toda mudança de estado registra data, causa e responsável (sistema ou pessoa).
- **FS-REG-002** · Matrícula não é excluída, apenas muda de estado.
- **FS-REG-003** · Reativar matrícula restaura o progresso anterior.
- **FS-REG-004** · Toda matrícula tem um estado de espelhamento no painel (`pendente`, `aguardando_cpf`, `enviado`, `confirmado`, `erro`), independente do estado de acesso.

### 8.2 Certificado

| Estado | Significado |
|---|---|
| `pendente` | Concluído, aguardando liberação. Sempre com um motivo interno |
| `emitido` | Liberado ao aluno, com hash e QR Code do painel |
| `revogado` | Invalidado, mantido no histórico |

Motivos de `pendente`: `parceiro_fora_de_dia`, `matricula_nao_regularizada`, `aguardando_painel`.

- **FS-REG-010** · Certificado nunca é excluído.
- **FS-REG-011** · O hash de validação nunca muda, mesmo em segunda via.
- **FS-REG-012** · Certificado `pendente` nunca é exibido como válido nem tem hash antes da resposta positiva do painel.

### 8.3 Progresso

- **FS-REG-020** · O progresso é por aluno, por matrícula e por aula.
- **FS-REG-021** · O percentual do curso considera apenas aulas publicadas.
- **FS-REG-022** · Adicionar aulas a um curso já concluído não revoga o certificado emitido.

### 8.4 Personalização

- **FS-REG-030** · Alterações de aparência e home só aparecem para alunos após a publicação explícita.
- **FS-REG-031** · Elementos obrigatórios (QR Code do certificado, links legais) não podem ser removidos pelo parceiro.

### 8.5 Avaliações

| Objeto | Estados |
|---|---|
| Resultado da avaliação (por matrícula) | `pendente`, `em_andamento`, `aguardando_correcao`, `aprovada`, `reprovada` |
| Tentativa de prova | `em_andamento`, `enviada`, `expirada` |
| Entrega de tarefa | `enviada`, `em_correcao`, `devolvida`, `aprovada`, `reprovada` |

- **FS-REG-040** · Tentativas e entregas nunca são excluídas; ficam no histórico.
- **FS-REG-041** · O resultado de uma avaliação obrigatória aprovada soma ao progresso do curso; avaliação opcional não entra na regra de conclusão.
- **FS-REG-042** · Uma tentativa expirada é enviada automaticamente com as respostas salvas.

---

## 9. E-mails transacionais

Todos com a identidade visual do parceiro.

| E-mail | Quando |
|---|---|
| Acesso liberado | Matrícula criada |
| Redefinição de senha | Solicitação do aluno |
| Link mágico | Solicitação de acesso |
| Acesso suspenso | Matrícula suspensa |
| Acesso a expirar | Alguns dias antes da expiração |
| Certificado emitido | Emissão |
| Convite de equipe | Novo membro |
| Alerta de integração | Falha repetida (para o parceiro) |
| Certificados retidos | Parceiro fora de dia com certificados pendentes (para o parceiro) |
| Tarefa corrigida | Resultado e feedback disponíveis (para o aluno) |
| Nova entrega para correção | Aluno enviou uma tarefa (para a equipe do curso) |
| Novo comentário sem resposta | Comentário aguardando resposta (para a equipe) |

**FS-EML-001** · E-mails usam logo, cor, remetente e rodapé configurados pelo parceiro. *(BR-CUS-005)*

---

## 10. Suposições assumidas neste documento

Estes pontos foram assumidos para a especificação andar. Se algum estiver errado, é só indicar.

| # | Suposição |
|---|---|
| 1 | O percentual padrão para concluir um vídeo é 90%, definido pela plataforma e não pelo parceiro |
| 2 | Em reembolso, o certificado já emitido continua válido por padrão |
| 3 | Instrutor só publica se o administrador permitir |
| 4 | A versão 1 não tem avaliações com nota, só conclusão de aulas (a regra de nota fica preparada) |
| 5 | O painel do parceiro é acessado no domínio do próprio parceiro |
| 6 | A situação do parceiro (assinatura, créditos, matrículas em dia) é decidida pelo painel de matrículas; o AVA só obedece |
| 7 | Certificado já liberado continua acessível se o parceiro ficar fora de dia depois |
| 8 | A validação pública do certificado usa a página do painel/faculdade, sem página própria no AVA na v1 |
| 9 | O CPF é pedido no primeiro acesso e **não bloqueia as aulas**; é obrigatório para o espelhamento e para o certificado |
| 10 | Provas e tarefas são **opcionais por curso** (opção desligada por padrão), entram na **fase 2** e não bloqueiam o lançamento; correção de provas é automática, de tarefas é manual |
| 11 | Comentários por aula entram como P1 (proposta), com moderação pelo parceiro |
| 12 | A nota final de uma prova é a melhor tentativa |

## 11. Rastreabilidade (resumo)

| Área de negócio | Itens funcionais |
|---|---|
| Parceiros e isolamento (BR-TEN) | FS-PER, FS-OPR, FS-ADM-080 |
| Catálogo (BR-CAT) | FS-ADM-010 a 013, FS-STU-030 a 033 |
| Experiência do aluno (BR-STU) | FS-STU-001 a 051 |
| Personalização (BR-CUS) | FS-ADM-040 a 052, FS-REG-030, 031 |
| Pagamento (BR-PAY) | FS-PAY, FS-ADM-030 a 033, FS-REG-001 a 003 |
| Certificado (BR-CER) | FS-CER, FS-REG-010, 011 |
| Painel do parceiro (BR-ADM) | FS-ADM |
| Painel do operador (BR-OPR) | FS-OPR |
| Integração com o painel (BR-INT) | FS-CER-014 a 019, fluxos 7.5 e 7.7 |
| Avaliações (BR-AVA) | FS-AVA, fluxos 7.8 e 7.9, seção 8.5 |
| Página da aula e comentários (BR-STU-013 a 015) | FS-STU-044 a 049, FS-ADM-090 a 092 |

## 12. Próximo documento

**UNY-DOC-004 · Arquitetura multi-parceiro**: como isolar os dados de cada parceiro, como funcionam domínios próprios, temas e adaptadores de pagamento, e qual stack técnica sustenta tudo isso.
