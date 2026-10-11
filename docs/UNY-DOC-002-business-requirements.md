# UNY-DOC-002 · Business Requirements

**Projeto:** UnyEducation
**Versão:** 0.2 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Depende de:** UNY-DOC-001 (Product Vision)

---

## 1. Como ler este documento

Cada requisito tem um código (`BR-AREA-000`) e uma prioridade:

- **P0**: obrigatório na versão 1
- **P1**: desejável na versão 1, pode ficar para logo depois
- **P2**: futuro, arquitetura apenas prepara o terreno

Estes são requisitos de **negócio**: dizem *o quê* e *por quê*. O *como* (telas, APIs, tabelas) vem nos documentos seguintes.

## 2. Atores

| Ator | Descrição |
|---|---|
| **Operador** | Equipe que administra a plataforma e cria parceiros |
| **Parceiro** | Dono de um ambiente: marca, cursos, preços e alunos |
| **Equipe do parceiro** | Administradores, instrutores e suporte dentro de um ambiente |
| **Aluno** | Consome os cursos de um ou mais parceiros |
| **Sistema de pagamento** | Plataforma de vendas ou gateway externo usado pelo parceiro |

## 3. Parceiros e isolamento

| Código | Requisito | Prioridade |
|---|---|---|
| BR-TEN-001 | Cada parceiro opera em um ambiente isolado, com dados próprios | P0 |
| BR-TEN-002 | Nenhum dado de um parceiro pode ser visto por outro, nem pela equipe de outro parceiro | P0 |
| BR-TEN-003 | O operador cria, suspende e reativa parceiros | P0 |
| BR-TEN-004 | Cada parceiro pode usar domínio próprio ou subdomínio da plataforma | P0 |
| BR-TEN-005 | Um mesmo aluno pode ter conta em mais de um parceiro, sem que os ambientes se vejam | P1 |
| BR-TEN-006 | Limites por plano (alunos, cursos, armazenamento) configuráveis pelo operador | P1 |
| BR-TEN-007 | Toda ação administrativa gera registro de auditoria | P0 |

## 4. Catálogo e conteúdo

| Código | Requisito | Prioridade |
|---|---|---|
| BR-CAT-001 | O parceiro cria cursos com módulos e aulas | P0 |
| BR-CAT-002 | Aulas podem ser vídeo, áudio, texto ou material para download | P0 |
| BR-CAT-003 | O parceiro organiza cursos em categorias e trilhas | P0 |
| BR-CAT-004 | Cursos têm capa, descrição, trailer opcional e instrutor | P0 |
| BR-CAT-005 | Cursos podem ser rascunho, publicados ou arquivados | P0 |
| BR-CAT-006 | Liberação de aulas pode ser imediata ou progressiva (por data ou por conclusão) | P1 |
| BR-CAT-007 | Cursos gratuitos e pagos convivem no mesmo ambiente | P0 |
| BR-CAT-008 | Provas e tarefas dentro do curso: ver seção 4.1 (BR-AVA) | ver 4.1 |

### 4.1 Tarefas e provas (opcional por curso)

O parceiro pode, se quiser, montar um esquema de **tarefas** (com correção manual) e **provas** (com correção automática). Nada disso é obrigatório: **cada curso decide** se terá avaliações, por meio de uma opção no editor do curso, **desligada por padrão**. Um curso sem avaliações funciona exatamente como antes. Provas e tarefas **não bloqueiam o lançamento**: entram na fase 2 (seção 12.1).

| Código | Requisito | Prioridade |
|---|---|---|
| BR-AVA-001 | O parceiro adiciona **provas** e **tarefas** a qualquer módulo, como itens da lista de aulas | P1 |
| BR-AVA-002 | Cada prova ou tarefa pode ser **obrigatória ou opcional** para a conclusão do curso | P1 |
| BR-AVA-003 | Provas objetivas (uma escolha, várias escolhas, verdadeiro ou falso) com **correção automática** | P1 |
| BR-AVA-004 | Provas com nota de aprovação, número de tentativas, tempo limite, período de abertura e regra de exibição do gabarito | P1 |
| BR-AVA-005 | Tarefas com enunciado, anexos e entrega por arquivo, texto ou link, com regra de reenvio | P1 |
| BR-AVA-006 | **Correção manual** de tarefas pela equipe, com nota, feedback e decisão (aprovar, devolver para ajuste, reprovar) | P1 |
| BR-AVA-007 | Fila de correção para a equipe, com filtros e indicação de atrasos | P1 |
| BR-AVA-008 | A conclusão do curso e a emissão do certificado consideram as avaliações obrigatórias | P1 |
| BR-AVA-009 | O aluno acompanha status, nota, tentativas restantes e feedback | P1 |
| BR-AVA-010 | Relatórios de notas e aprovação, exportáveis em planilha | P1 |
| BR-AVA-011 | Aulas liberadas somente após aprovação em uma avaliação | P1 |
| BR-AVA-012 | Provas dissertativas e banco de questões reutilizável entre cursos | P2 |
| BR-AVA-013 | Sorteio de questões de um banco, critérios de correção (rubricas) e correção por pares | P2 |
| BR-AVA-014 | Tempo extra por aluno (adaptações de acessibilidade) | P2 |
| BR-AVA-015 | Opção **por curso** para habilitar tarefas e provas, **desligada por padrão**; desligada, o editor não oferece esses tipos, a lista de conteúdo não mostra avaliações e a regra do certificado as ignora | P1 |

**Regras de negócio**

- **RN-AVA-01:** reprovar em uma avaliação **não bloqueia** o acesso às aulas, exceto quando o parceiro configurou liberação condicionada (BR-AVA-011).
- **RN-AVA-02:** a nota final de uma prova é a **melhor tentativa**, por padrão.
- **RN-AVA-03:** arquivos de entrega têm tipo e tamanho validados e passam por verificação de vírus (ferramenta a definir, UNY-DOC-004, seção 10.2).
- **RN-AVA-04:** entregas, respostas e notas só são visíveis ao **próprio aluno** e à **equipe do curso**.
- **RN-AVA-05:** qualquer alteração de nota ou de decisão de correção fica **registrada** com responsável e motivo.
- **RN-AVA-06:** a plataforma **não verifica a identidade** de quem faz a prova; o parceiro deve saber dessa limitação ao usar provas como critério de certificado.

## 5. Experiência do aluno

| Código | Requisito | Prioridade |
|---|---|---|
| BR-STU-001 | Home com banner, vitrines e "continuar assistindo" | P0 |
| BR-STU-002 | Retomada automática da última aula assistida | P0 |
| BR-STU-003 | Progresso visível por curso e por módulo | P0 |
| BR-STU-004 | Busca por cursos e aulas | P0 |
| BR-STU-005 | Favoritos e "minha lista" | P1 |
| BR-STU-006 | Uso confortável em celular, tablet e computador | P0 |
| BR-STU-007 | Acesso por e-mail com link mágico ou senha | P0 |
| BR-STU-008 | Acesso automático após a compra, sem o aluno precisar se cadastrar manualmente | P0 |
| BR-STU-009 | Anotações pessoais por aula | P2 |
| BR-STU-010 | **CPF do aluno obrigatório** para espelhar a matrícula e liberar o certificado; coletado na compra (quando a plataforma de venda enviar), no primeiro acesso, na matrícula manual ou na importação | P0 |
| BR-STU-011 | CPF validado (dígitos verificadores) e tratado como dado pessoal protegido: guardado criptografado, mascarado nas telas, com acesso completo registrado | P0 |
| BR-STU-012 | Matrícula sem CPF fica ativa para estudo; o espelhamento e a liberação do certificado aguardam o CPF | P0 |
| BR-STU-013 | **Comentários e dúvidas por aula**, com respostas encadeadas da equipe e moderação pelo parceiro **[A CONFIRMAR]** | P1 |
| BR-STU-014 | Avaliar a aula (curtir ou não curtir) e **marcar ou desmarcar como vista** | P1 |
| BR-STU-015 | Página da aula com navegação para a aula anterior e a próxima e progresso geral do curso ("X de Y aulas") | P0 |

## 6. Personalização pelo parceiro

| Código | Requisito | Prioridade |
|---|---|---|
| BR-CUS-001 | Logo, cores, fontes e favicon definidos pelo parceiro | P0 |
| BR-CUS-002 | Parceiro define ordem, título e conteúdo das vitrines da home | P0 |
| BR-CUS-003 | Parceiro define banners de destaque | P0 |
| BR-CUS-004 | Modo claro e escuro preparados | P1 |
| BR-CUS-005 | E-mails transacionais com a identidade do parceiro | P1 |
| BR-CUS-006 | Editor visual para montar a home sem código | P1 |
| BR-CUS-007 | Páginas institucionais simples (sobre, contato, termos) | P1 |
| BR-CUS-008 | Nenhuma personalização pode quebrar elementos obrigatórios (ex.: QR Code do certificado, aviso legal) | P0 |
| BR-CUS-009 | Menu lateral configurável pelo parceiro: itens com ícone, rótulo e destino (página interna, link externo, curso ou trilha), ordenáveis e ativáveis | P1 |
| BR-CUS-010 | Botão flutuante de ajuda configurável (rótulo e destino, como suporte, WhatsApp ou FAQ) | P1 |
| BR-CUS-011 | Cursos e módulos com capa vertical enviada pelo parceiro; banner largo com versão para celular | P0 |
| BR-CUS-012 | Vitrine "Módulos de um curso", em que cada card é um módulo | P1 |

## 7. Pagamento e liberação de acesso

### Princípio

A plataforma **não processa pagamento**. Ela **recebe a confirmação** de onde o parceiro vende e **libera ou retira o acesso** conforme o resultado.

### Três níveis de integração

| Nível | Para quem | Como funciona |
|---|---|---|
| **1. Nativo** | Plataformas de venda de cursos mais usadas pelos parceiros | Adaptador pronto: o parceiro informa as credenciais e cola o endereço de webhook |
| **2. Genérico** | Gateways e checkouts sem adaptador pronto, mas com webhook | Webhook configurável, com mapeamento de campos (e-mail, produto, status) feito pelo parceiro |
| **3. Manual** | Quem vende por meios sem integração (PIX direto, maquininha, link de pagamento simples, transferência) | A equipe do parceiro libera o acesso na mão, com registro obrigatório |

### Plataformas previstas para o nível 1

| Tipo | Plataformas |
|---|---|
| **Plataformas de venda de infoprodutos** | Hotmart, Kiwify, Eduzz, Monetizze, Greenn, Braip |
| **Gateways de pagamento** | Asaas, Mercado Pago, Stripe, Pagar.me. O **PagBank** é atendido inicialmente pelos níveis 2 e 3 (UNY-DOC-006, seção 5.9) |

Observações:

- Cada plataforma tem formato de notificação, forma de autenticação e nomes de status próprios. Antes de construir cada adaptador, a documentação oficial atual dela será consultada (isso entra no UNY-DOC-006).
- Os adaptadores são entregues em ondas, não todos de uma vez. O sistema nasce com o nível 3 (manual) e o nível 2 (genérico) funcionando, e os adaptadores nativos entram um a um, começando pelos que os primeiros parceiros mais usam.
- Qualquer plataforma fora da lista cai no nível 2 ou 3 até ganhar adaptador.

### Requisitos

| Código | Requisito | Prioridade |
|---|---|---|
| BR-PAY-001 | Compra aprovada gera matrícula e libera acesso automaticamente (níveis 1 e 2) | P0 |
| BR-PAY-002 | Reembolso, chargeback e cancelamento suspendem o acesso | P0 |
| BR-PAY-003 | Cada parceiro conecta seus próprios produtos do checkout a seus cursos | P0 |
| BR-PAY-004 | Toda notificação recebida é registrada, inclusive as que falham | P0 |
| BR-PAY-005 | Notificações duplicadas ou fora de ordem não podem gerar acesso incorreto | P0 |
| BR-PAY-006 | Matrícula manual com motivo e responsável registrados | P0 |
| BR-PAY-007 | Matrícula manual pode ter data de expiração | P1 |
| BR-PAY-008 | Importação de alunos em lote por planilha | P1 |
| BR-PAY-009 | Código ou link de acesso para liberar um curso sem pagamento (cortesia, parceria, turma fechada) | P1 |
| BR-PAY-010 | Webhook genérico com mapeamento de campos configurável pelo parceiro | P1 |
| BR-PAY-011 | Assinaturas recorrentes: acesso enquanto a assinatura estiver ativa | P1 |
| BR-PAY-012 | Painel de conciliação: compras recebidas, matrículas criadas e divergências | P1 |
| BR-PAY-013 | Novo adaptador pode ser adicionado sem alterar o restante do sistema | P0 |
| BR-PAY-014 | Checkout próprio da plataforma | P2 |

### Regras de negócio

- **RN-PAY-01:** a plataforma é a fonte da verdade do **acesso**; o gateway é a fonte da verdade do **dinheiro**.
- **RN-PAY-02:** uma matrícula sempre tem uma **origem** (nativo, genérico, manual, código, importação) e essa origem fica visível ao parceiro.
- **RN-PAY-03:** matrícula manual nunca pode ser indistinguível de uma matrícula paga no relatório.
- **RN-PAY-04:** dados sensíveis de cartão **nunca** passam pela plataforma.
- **RN-PAY-05:** chaves e segredos de integração ficam isolados por parceiro e nunca aparecem em texto aberto depois de salvos.

## 8. Certificado de conclusão e situação do parceiro

### Princípio

O aluno estuda e conclui **sem restrição comercial**. A **liberação** do certificado depende da situação do parceiro no painel de matrículas (assinatura e/ou matrículas em dia), como já acontece hoje no sistema de créditos e assinatura. A fonte da verdade dessa situação é o **painel**.

### Requisitos do certificado

| Código | Requisito | Prioridade |
|---|---|---|
| BR-CER-001 | Certificado gerado ao cumprir a regra definida pelo parceiro (ex.: 100% das aulas) | P0 |
| BR-CER-002 | Modelo visual definido pelo parceiro | P0 |
| BR-CER-003 | Hash e QR Code de validação **gerados pelo painel** e inseridos no certificado | P0 |
| BR-CER-004 | Certificado pode ser revogado, com histórico, e a revogação é informada ao painel | P1 |
| BR-CER-005 | Segunda via mantendo o mesmo hash de validação | P1 |
| BR-CER-006 | Editor visual de certificado com blocos obrigatórios protegidos (hash e QR Code) | P1 |
| BR-CER-007 | Emissão em lote | P2 |
| BR-CER-008 | Certificado só é liberado ao aluno se o parceiro (e a matrícula) estiverem em dia segundo o painel | P0 |
| BR-CER-009 | Conclusão sem liberação deixa o certificado **retido**; o aluno vê mensagem neutra, sem detalhes comerciais | P0 |
| BR-CER-010 | Regularização do parceiro libera automaticamente os certificados retidos | P0 |
| BR-CER-011 | O parceiro vê quais certificados estão retidos e o motivo | P0 |
| BR-CER-012 | O operador pode liberar ou reter um certificado manualmente, com registro | P1 |
| BR-CER-013 | Certificado já liberado não é retirado do aluno se o parceiro ficar fora de dia depois (proposta, decisão 9) | P1 |

### Requisitos de integração com o painel

| Código | Requisito | Prioridade |
|---|---|---|
| BR-INT-001 | Toda matrícula criada no AVA, de qualquer origem, é **espelhada** no painel de matrículas | P0 |
| BR-INT-002 | Matrícula criada ou aprovada no painel libera o acesso no AVA (origem "painel") | P1 |
| BR-INT-003 | Todo parceiro do AVA é vinculado a um parceiro ou polo do painel | P0 |
| BR-INT-004 | Falha ou demora do painel **nunca bloqueia o estudo**; só adia a liberação do certificado | P0 |
| BR-INT-005 | Conclusão, emissão e revogação de certificado são informadas ao painel | P0 |
| BR-INT-006 | Divergências entre AVA e painel (matrícula não espelhada, resposta pendente) ficam visíveis ao operador e ao parceiro | P1 |

### Regras de negócio

- **RN-CER-01:** a situação comercial do parceiro **nunca bloqueia o estudo**. (Isso é diferente de reembolso ou cancelamento de uma matrícula, que suspende o acesso, conforme BR-PAY-002.)
- **RN-CER-02:** o AVA **não replica** as regras de crédito e assinatura; pergunta ao painel e obedece à resposta.
- **RN-CER-03:** a situação é avaliada **no momento da liberação**. Sem resposta do painel, o certificado fica pendente; nunca é liberado por padrão.
- **RN-CER-04:** o hash de validação é gerado pelo painel, uma única vez, e nunca muda.
- **RN-CER-05:** o aluno nunca vê motivos comerciais (inadimplência, créditos); só o parceiro e o operador veem.
- **RN-CER-06:** sem CPF válido do aluno não há espelhamento nem certificado.

## 9. Painel do parceiro

| Código | Requisito | Prioridade |
|---|---|---|
| BR-ADM-001 | Gestão de cursos, módulos e aulas | P0 |
| BR-ADM-002 | Lista de alunos com status, origem da matrícula e progresso | P0 |
| BR-ADM-003 | Configuração de aparência e vitrines | P0 |
| BR-ADM-004 | Configuração de integrações de pagamento | P0 |
| BR-ADM-005 | Papéis dentro da equipe (administrador, instrutor, suporte) | P1 |
| BR-ADM-006 | Relatórios básicos: matrículas, conclusão, engajamento | P1 |
| BR-ADM-007 | Comunicados para alunos | P2 |

## 10. Painel do operador

| Código | Requisito | Prioridade |
|---|---|---|
| BR-OPR-001 | Criar e gerenciar parceiros | P0 |
| BR-OPR-002 | Definir planos e limites | P1 |
| BR-OPR-003 | Acompanhar uso por parceiro (alunos, armazenamento, tráfego) | P1 |
| BR-OPR-004 | Auditoria global e acesso de suporte com registro | P0 |
| BR-OPR-005 | Monitoramento de integrações com falha | P1 |

## 11. Requisitos não funcionais de negócio

| Código | Requisito | Prioridade |
|---|---|---|
| BR-NFR-001 | Páginas principais carregam rápido, mesmo em celular com conexão média | P0 |
| BR-NFR-002 | Vídeos protegidos contra download e compartilhamento simples de link | P1 |
| BR-NFR-003 | Backups automáticos e recuperação testada | P0 |
| BR-NFR-004 | Conformidade com a LGPD: consentimento, exportação e exclusão de dados do aluno | P0 |
| BR-NFR-005 | Registro de auditoria que não pode ser alterado por parceiros | P0 |
| BR-NFR-006 | Interface em português, com arquitetura preparada para outros idiomas | P1 |

## 12. Fora de escopo da versão 1

- Registro acadêmico, histórico escolar, assinatura ICP-Brasil e regras de pós-graduação
- Checkout próprio e processamento de pagamento
- Marketplace compartilhado entre parceiros
- App nativo
- IA aplicada ao conteúdo, incluindo assistente de IA no menu
- Comunidade (feed, espaços, ranking, calendário de eventos): candidata à versão 2, a decidir (UNY-DOC-007, seção 8.9)
- Transmissão ao vivo
- Verificação de identidade em provas (monitoramento)

### 12.1 Fases de entrega (proposta)

| Fase | Foco | O que entra |
|---|---|---|
| **1 · Lançamento** | Núcleo que já permite operar parceiros reais | Parceiros, domínios e isolamento; catálogo (cursos, módulos, aulas em vídeo, áudio e texto); ambiente do aluno (acesso, home, menu, player, progresso, marcar como visto); temas e personalização; matrículas manual, importação e código de acesso; webhook genérico e primeiro adaptador nativo; **ponte com o painel, CPF e certificado com hash**; painéis do parceiro e do operador básicos; auditoria e LGPD básica |
| **2 · Aprendizado ampliado** | O que enriquece o curso sem ser essencial para publicar | **Tarefas e provas** (opcionais por curso); comentários por aula e curtir; demais adaptadores de pagamento (ordem do UNY-DOC-006); vitrine "Módulos de um curso"; relatórios; avisos simples |
| **3 · Engajamento** | Recursos de comunidade e gamificação | Comunidade, ranking e troféu, calendário de eventos, assistente de IA, notificações avançadas |

Observações:

- A divisão é uma **proposta** e pode ser ajustada; o ponto firme é que **avaliações não são pré-requisito de lançamento**.
- O **modelo de dados já está desenhado** para as fases 2 e 3 (UNY-DOC-005). As tabelas de avaliações e comentários entram por migração na fase 2; na fase 1, o editor de aula só oferece os tipos vídeo, áudio e texto.
- Uma vez decidida a ordem final dos adaptadores de pagamento, o UNY-DOC-006 (seção 6) acompanha a fase 1 e a fase 2.

## 13. Decisões em aberto

| # | Decisão | Impacto |
|---|---|---|
| 1 | ~~Quais plataformas de venda recebem adaptador nativo~~ **Resolvida** (ver seção 7). A **ordem de implementação** está proposta no UNY-DOC-006 (seção 6) e deve ser validada com os parceiros | Cronograma do nível 1 |
| 2 | Onde ficam os vídeos (hospedagem própria, serviço especializado ou links externos) | Custo, segurança e player |
| 3 | Como o parceiro paga a plataforma. Hoje existe o sistema de assinatura e créditos do painel; a dúvida é se o AVA segue exatamente esse modelo | Planos, limites e retenção de certificados |
| 4 | Domínio próprio com SSL automático já na v1 | Infraestrutura |
| 5 | ~~Nome definitivo do produto~~ **Resolvida (09/10/2026):** **UnyEducation** | Marca e documentação |
| 6 | Regras exatas de "em dia" (assinatura, créditos, matrículas) e o que o painel responde | Retenção e liberação de certificados |
| 7 | ~~Dados do aluno que o painel exige~~ **Resolvida:** CPF obrigatório (BR-STU-010). Resta confirmar se o painel exige mais algum campo | Cadastro, checkout e banco de dados |
| 8 | A validação pública do certificado continua sendo a do painel/faculdade (assumido que sim) | Escopo do certificado no AVA |
| 9 | Certificado já liberado continua acessível se o parceiro ficar fora de dia depois (proposta: sim) | BR-CER-013 |
| 10 | ~~Tarefas e provas na versão 1~~ **Resolvida:** são **opcionais por curso** (BR-AVA-015) e **não bloqueiam o lançamento**; entram na fase 2 | Escopo e cronograma |
| 11 | **Comentários por aula** na fase 2 (proposta: P1) | BR-STU-013 |

## 14. Rastreabilidade

Cada requisito deste documento será referenciado nos próximos:

```text
BR-xxx (negócio)
   ↓
FS-xxx (especificação funcional, DOC-003)
   ↓
Arquitetura, banco, API (DOC-004 a 006)
   ↓
Telas e testes
```
