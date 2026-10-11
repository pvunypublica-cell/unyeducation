# UNY-DOC-009 · Plano de Construção da Fase 1

**Projeto:** UnyEducation
**Versão:** 0.2 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Data-base:** 09/10/2026
**Depende de:** UNY-DOC-001 a UNY-DOC-008

---

## 1. Como ler este documento

Este documento transforma a documentação em **ordem de trabalho**: o que construir primeiro, o que depende de quê, o que dá para **mostrar** em cada etapa e como saber que a etapa terminou.

Regras de leitura:

- **Marco (M)** é uma entrega que se pode ver funcionando. **Trilha (I)** é o trabalho de servidor, que corre em paralelo.
- Os tamanhos (**P**, **M**, **G**) são **relativos** e **[ESTIMADO]**. Não há datas: elas só fazem sentido depois de definir quantas pessoas trabalham e em que ritmo.
- Nada aqui foi codificado. Este é o plano para o **primeiro código**.

## 2. Pontos de partida (decididos)

| # | Decisão | Origem |
|---|---|---|
| 1 | Hospedagem inicial na **VPS 1** (a do painel), com o AVA em **contêiner Docker** (PHP 8.3); destino futuro: VPS nova com Ubuntu 24.04 | UNY-DOC-008, seções 4.2 e 4.7 (decisão de 09/10/2026) |
| 2 | **Fase 1 = lançamento**: parceiros, catálogo, ambiente do aluno, matrículas, pagamento (genérico + 1 adaptador), CPF, ponte com o painel, certificado, painéis básicos | UNY-DOC-002, seção 12.1 |
| 3 | **Avaliações** (provas e tarefas) e **comentários** ficam na **fase 2** | UNY-DOC-002, BR-AVA-015 |
| 4 | **Dado real de aluno (CPF) só entra em produção** depois do checklist da seção 4.4 do UNY-DOC-008 | UNY-DOC-008 |
| 5 | Até lá, **somente dados fictícios**, em homologação | UNY-DOC-008, 16.1 |
| 6 | Pilha: PHP 8.3 + **Laravel 13**, MySQL 5.7 (compatível com 8.0 no futuro), fila no banco, sem Redis | UNY-DOC-004, 008 |
| 7 | Nome do produto: **UnyEducation** | Decisão de 09/10/2026 |
| 8 | Equipe: Otavio e Claude. **Piloto: Unyboss**, com **Asaas** | Decisão de 09/10/2026 |

## 3. Duas trilhas em paralelo

```text
Trilha I (servidor, você)            Trilha P (produto, código)
─────────────────────────            ──────────────────────────
I0 Firewall e porta 3306             M0 Fundação do repositório e CI
I1 Docker + contêiner de teste       M1 Parceiros, domínios e acesso
I2 Homologação no ar                 M2 Catálogo + ambiente do aluno  ◄─ primeira demonstração
I3 Backup e restauração              M3 Painel do parceiro
I4 Alertas e medição de 7 dias       M4 Matrículas
I5 Checklist de go-live              M5 Pagamentos
                                     M6 CPF e ponte com o painel
                                     M7 Certificados
                                     M8 Painel do operador e LGPD
                                     M9 Endurecimento e piloto
```

A trilha P **não espera** a trilha I até o **M2**: roda no computador local e no CI. A homologação (I2) precisa existir **antes do M3**, porque a partir dali há coisas para testar fora da máquina local.

## 4. Trilha I · Servidor (VPS 1)

Detalhe e comandos em UNY-DOC-008, seções 4.6, 6 e 19.

| Etapa | O que | Aceite | Depende de |
|---|---|---|---|
| **I0** | Firewall (`ufw`: 22, 80, 443) e fechamento da porta **3306**, depois de confirmar que ninguém usa o MySQL de fora (4.6) | A 3306 não responde de fora; painel e sites continuam no ar | Nada. **Concluída em 11/10/2026** (firewall ativo, 3306 fechada, `root@%` → `root@localhost`) |
| **I0b** | Usuário `deploy` e SSH por chave; swap de 2 GiB; Ubuntu Pro vinculado | Acesso por chave testado antes de fechar a senha | I0 |
| **I1** | Instalar `docker.io` e `docker-compose-v2`; contêiner PHP 8.3 de teste; conferir `iptables-save` antes e depois | Nada escuta em `0.0.0.0` além de 22, 80, 443; legado intacto | I0 |
| **I2** | Homologação: Compose, scripts `ava-ctl` e `ava-run`, bancos `ava_homolog` e `ava_homolog_audit`, vhost com senha, DNS curinga, primeiro certificado | `https://ava-homolog…/saude` responde | I1 |
| **I2b** | Decisão do **ModSecurity** nos hosts do AVA (UNY-DOC-008, 7.7) | Regras de nome testadas (SEC-034) | I2 |
| **I3** | Backup criptografado **fora** do servidor e **primeira restauração** | Restauração completa feita em homologação | I2 |
| **I4** | Alertas e medição de 7 dias | Seção 5.6 do UNY-DOC-008 atendida | I2 |
| **I5** | Checklist de go-live (UNY-DOC-008, 4.4), com parceiro piloto | Todos os itens marcados | M9 |

## 5. Trilha P · Marcos de produto

### 5.1 Visão geral

| Marco | Entrega | Tam. | Mostra o quê |
|---|---|---|---|
| **M0** | Fundação do repositório e CI | P | Pipeline verde; testes de charset, de isolamento e de nomes difíceis |
| **M1** | Parceiros, domínios e acesso | M | Dois parceiros em dois endereços, sem enxergar um ao outro; login |
| **M2** | Catálogo e ambiente do aluno | **G** | **Primeira demonstração:** home estilo streaming com dados fictícios |
| **M3** | Painel do parceiro | G | Parceiro cria curso, monta a home e muda a aparência sozinho |
| **M4** | Matrículas | M | Matrícula manual, importação e código de acesso |
| **M5** | Pagamentos | G | Compra de teste libera o acesso sozinha |
| **M6** | CPF e ponte com o painel | G | Matrícula espelhada no painel de teste |
| **M7** | Certificados | G | Conclusão gera certificado com hash e QR (ou o retém) |
| **M8** | Painel do operador, LGPD, relatórios mínimos | M | Operador cria parceiro; titular pede seus dados |
| **M9** | Endurecimento e piloto | M | Checklist de go-live; parceiro piloto com dados reais |

### 5.2 Dependências

```text
M0 ─► M1 ─► M2 ─► M3 ─┬─► M4 ─► M5 ─► M6 ─► M7 ─► M8 ─► M9
                      │
                      └─ (home, aparência e cursos já usáveis)
```

- **M6 depende do painel de matrículas** ter os endpoints previstos (UNY-DOC-006, 7.2). Até lá, o AVA usa um **simulador do painel** (item T-050), para o desenvolvimento não parar.
- **M5** pode começar antes do M4 acabar, mas o pipeline só fecha depois que a matrícula existe.
- **M7** depende de M6 (hash e QR vêm do painel) e de uma regra de conclusão (M2, progresso).

### 5.3 Detalhe dos marcos

#### M0 · Fundação do repositório e CI

| Item | Conteúdo |
|---|---|
| Repositório | Um único repositório; mudança entra por *pull request* em ramo próprio. A **proteção do ramo `main`** no GitHub depende do plano (em repositório privado, costuma exigir plano pago): sem ela, a regra é **por disciplina** e os testes do CI continuam valendo |
| Esqueleto | Laravel 13 |
| CI | Testes **contra MySQL 5.7** em contêiner; análise estática; `composer audit`; empacotamento (UNY-DOC-008, 16.2) |
| Testes obrigatórios desde já | Charset `utf8mb4_unicode_ci` em todas as tabelas; chave estrangeira composta (`tenant_id`, `id`); **nomes difíceis** (SEC-034); nenhum recurso exclusivo do MySQL 8 |
| Base de tenancy | Escopo global por `tenant_id`, impossível de esquecer em consulta (UNY-DOC-004, 6.2) |
| **Pronto quando** | O CI passa, e um teste prova que uma consulta sem parceiro definido **falha** |

#### M1 · Parceiros, domínios e acesso

| Item | Conteúdo |
|---|---|
| Parceiro e domínio | Tabelas `parceiros` e `dominios`; resolução do parceiro pelo host; slugs reservados (UNY-DOC-004, 5.2) |
| Isolamento | Cookie preso ao host exato; teste de que a sessão de um parceiro não vale no outro |
| Identidade | Cadastro, login, link mágico, papéis e permissões (FS-PER); **CPF ainda não** |
| Auditoria | Schema `ava_audit`; usuário da aplicação só com `INSERT` e `SELECT` (UNY-DOC-008, 9.3) |
| **Pronto quando** | Dois parceiros de teste respondem em dois endereços, e o teste de isolamento passa |

#### M2 · Catálogo e ambiente do aluno (primeira demonstração)

| Item | Conteúdo |
|---|---|
| Catálogo | Cursos, módulos, aulas (vídeo por link externo, áudio, texto), categorias |
| Ambiente do aluno | Menu lateral, home com banner e vitrines, "continuar assistindo", página do curso, **página da aula** (player, áudio, materiais, barra de progresso do módulo), busca |
| Tema | **Cinema** como padrão, com os tokens do UNY-DOC-007; capas verticais 2:3; botão "Ajuda" |
| Aprendizado | Progresso, retomada em dois toques, marcar como visto, favoritos |
| Dados | Um curso fictício grande (como o de 155 aulas das referências) para testar desempenho |
| **Pronto quando** | Dá para navegar uma jornada completa no celular e no computador, com dados fictícios, e mostrar ao Frank e a um parceiro candidato |

> O M2 é o ponto de **decisão**: depois dele, o visual e a experiência estão visíveis, e ajustes de rumo custam pouco.

#### M3 · Painel do parceiro

Cursos (criar, publicar, ordenar), vitrines e banners, aparência (cores, fonte, logo), equipe e permissões, configurações do ambiente. Dashboard simples. **Pronto quando** um parceiro de teste monta uma home do zero, sem ajuda técnica.

#### M4 · Matrículas

Estados da matrícula (UNY-DOC-003, 8.1), matrícula manual, importação por planilha (com relatório de erros), códigos de acesso, expiração, e-mails transacionais com a marca do parceiro. **Pronto quando** uma importação de 500 linhas com erros propositais conclui e relata cada falha.

#### M5 · Pagamentos

| Item | Conteúdo |
|---|---|
| Pipeline | Webhook → registro bruto → fila → evento interno → decisão da matrícula (UNY-DOC-004, 8; UNY-DOC-006, 3 e 4) |
| Nível 2 | Webhook genérico configurável |
| Nível 1 | **Asaas** primeiro (UNY-DOC-006, seção 6); os seguintes só depois de saber o que os parceiros usam |
| Garantias | Idempotência, eventos fora de ordem, reembolso e chargeback suspendendo o acesso |
| Validação | Evento real de cada plataforma antes de liberar para parceiro (UNY-DOC-006, seção 11) |
| **Pronto quando** | A repetição do mesmo evento 10 vezes gera **uma** matrícula, e um reembolso a suspende |

#### M6 · CPF e ponte com o painel

CPF criptografado, índice de busca por hash e máscara (UNY-DOC-005, seção 7); pedido de CPF no primeiro acesso sem bloquear as aulas; espelhamento assíncrono por API assinada (HMAC, tolerância de 5 minutos); estados da matrícula espelhada; reconciliação diária. **Pronto quando** a matrícula criada no AVA aparece no painel de teste e uma divergência forçada é detectada.

#### M7 · Certificados

Regra de conclusão por curso, modelo padrão, geração do PDF na fila, **hash e QR do painel**, retenção quando o parceiro não está em dia e liberação automática ao regularizar (UNY-DOC-003, 7.5). **Pronto quando** os dois caminhos (parceiro em dia e fora de dia) passam em teste.

#### M8 · Painel do operador, LGPD e relatórios mínimos

Criar parceiro, planos e limites, suspensão, acesso de suporte com motivo e registro; pedidos do titular (acesso, correção, exclusão); relatórios básicos (alunos, conclusão). **Pronto quando** o operador cria um parceiro novo do zero em menos de 10 minutos.

#### M9 · Endurecimento e piloto

Checklist de go-live (UNY-DOC-008, 4.4); medição de 7 dias; restauração de backup ensaiada; plano de incidentes ensaiado; teste de carga na home e no webhook; revisão dos scripts `ava-ctl` e `ava-run`; **parceiro piloto** com poucos alunos reais. **Pronto quando** todos os itens do checklist estão marcados.

## 6. Qualidade em todos os marcos

| Regra | Como se verifica |
|---|---|
| Nenhuma consulta sem parceiro | Teste de isolamento no CI, por módulo novo |
| Charset único | Teste automático das tabelas e colunas |
| Nomes difíceis (`Walter`, `D'Ávila`, aspas, `Robert'); DROP TABLE…`) | Teste automático de cadastro (SEC-034) |
| CPF nunca em registro, endereço ou e-mail | Teste que procura o CPF de teste nos logs |
| Compatível com MySQL 5.7 | CI roda no 5.7 |
| Acessibilidade e contraste | Verificação do UNY-DOC-007, seção 8.11 |
| Cada requisito liga a um teste | Matriz de rastreabilidade atualizada a cada marco |

## 7. Ambiente de desenvolvimento

- Máquina local: Windows com 8 GB de memória. Preferir PHP e MySQL **nativos** (por exemplo Laragon), ou WSL2 com limite de memória, em vez de pilhas pesadas (UNY-DOC-008, 16.1).
- A semelhança exata com a produção vem do **CI**, não da máquina local.
- Dados de teste: **sempre fictícios**. CPF de teste gerado com dígitos válidos, nunca de pessoa real.

## 8. Lista inicial de tarefas (M0 a M2)

| ID | Tarefa | Marco | Módulo |
|---|---|---|---|
| T-001 | Criar o repositório e o modelo de revisão (proteção do `main` se o plano permitir) | M0 | Plataforma |
| T-002 | Esqueleto Laravel e configuração por ambiente | M0 | Plataforma |
| T-003 | CI: testes contra MySQL 5.7, análise estática, `composer audit` | M0 | Plataforma |
| T-004 | Teste de charset e collation de todas as tabelas | M0 | Banco |
| T-005 | Escopo global por parceiro e teste de falha sem parceiro | M0 | Tenancy |
| T-006 | Teste de cadastro com nomes difíceis (SEC-034) | M0 | Identidade |
| T-007 | Empacotamento do release e imagem de runtime (PHP 8.3) | M0 | Plataforma |
| T-010 | Migrações: parceiros, domínios, operadores, planos | M1 | Tenancy |
| T-011 | Resolução do parceiro pelo host e slugs reservados | M1 | Tenancy |
| T-012 | Cookie preso ao host e teste de isolamento de sessão | M1 | Identidade |
| T-013 | Cadastro, login e link mágico | M1 | Identidade |
| T-014 | Papéis e matriz de permissões (FS-PER) | M1 | Identidade |
| T-015 | Schema `ava_audit` e gravação de auditoria | M1 | Auditoria |
| T-020 | Migrações: categorias, cursos, módulos, aulas, materiais | M2 | Catálogo |
| T-021 | Seeder do curso fictício grande (155 aulas) | M2 | Catálogo |
| T-022 | Tokens de tema e tema Cinema | M2 | Tema |
| T-023 | Layout do aluno: menu lateral, botão "Ajuda" | M2 | Ambiente do aluno |
| T-024 | Home: banner, vitrines, "continuar assistindo" | M2 | Ambiente do aluno |
| T-025 | Página do curso (capa, módulos, progresso) | M2 | Ambiente do aluno |
| T-026 | Página da aula: player, áudio, materiais, barra do módulo | M2 | Ambiente do aluno |
| T-027 | Progresso, retomada e marcar como visto | M2 | Aprendizado |
| T-028 | Favoritos e busca | M2 | Aprendizado |
| T-029 | Teste de contraste e de desempenho da home | M2 | Qualidade |
| T-050 | **Simulador do painel de matrículas** (para o M6 não parar) | M6 | Ponte |

As tarefas dos marcos M3 a M9 são detalhadas **ao final do marco anterior**, com o que se aprendeu.

## 9. Riscos e dependências externas

| Risco | Efeito | Resposta |
|---|---|---|
| **Painel de matrículas ainda não tem os endpoints** da ponte | Atrasa o M6 e o M7 | Simulador (T-050); alinhar cedo com quem mantém o painel; verificar se o padrão da integração com a Pensar Cursos serve (UNY-DOC-004, pendência 17) |
| Regras de "em dia" ainda não definidas | Certificado retido ou liberado errado | Fechar a pendência 12 do UNY-DOC-004 antes do M7 |
| Plataformas dos parceiros desconhecidas | Adaptadores na ordem errada | Perguntar aos parceiros antes de começar o segundo adaptador |
| Provedor de vídeo indefinido | M2 usa link externo provisório | Decidir até o M3; a camada de provedor já isola a escolha |
| Servidor sem suporte de segurança | Risco com o CPF | Checklist do UNY-DOC-008, 4.4; VPS nova como destino |
| ModSecurity barrando cadastros e webhooks | Alunos sem conseguir se cadastrar | Decisão I2b e teste SEC-034 |
| Pouca memória na VPS 1 | Lentidão em todos os sistemas | Orçamento do UNY-DOC-008, seção 5; gatilhos de saída em 4.5 |
| Escopo crescendo (comunidade, IA, provas) | Lançamento atrasa | Fases 2 e 3 já definidas; a fase 1 não aceita item novo sem tirar outro |

## 10. Decisões para começar o M0 (09/10/2026)

| # | Decisão | Resultado |
|---|---|---|
| 1 | Versão do Laravel | **Laravel 13.** Exige PHP 8.3 ou mais novo (o contêiner atende), tem correções comuns até o 3º trimestre de 2027 e de segurança até 17/03/2028. O Laravel 12 já saiu das correções comuns em 13/08/2026 (documentação oficial). **Risco:** não foi possível confirmar se o Laravel 13 aceita o MySQL 5.7. O teste do CI (T-003) responde no M0; se não aceitar, o AVA usa um **MySQL 8 em contêiner próprio** (cerca de meio GiB de memória, dentro da margem do UNY-DOC-008, seção 5.1), sem tocar no MySQL dos outros sistemas |
| 2 | Nome do produto | **UnyEducation** (documentos atualizados). Nomes técnicos (`ava_prod`, `ava-<parceiro>…`) não mudam |
| 3 | Onde ficam o código e as imagens | **Proposta:** repositório **privado no GitHub**, na conta ou organização da empresa, com **GitHub Actions** para os testes e **GitHub Container Registry** para as imagens. Tudo no mesmo lugar, sem servidor extra. **Administrador: Otavio** (decidido em 10/10/2026), que cria a organização e o repositório. Boas práticas: autenticação em dois fatores na conta, proteção do ramo principal quando o plano permitir e nenhuma senha ou chave dentro do repositório |
| 4 | Quem faz o quê | **Claude** escreve código, testes e documentos. **Otavio** executa no servidor, aprova entregas e guarda as chaves. Atenção: os scripts `ava-ctl` e `ava-run` (rodam como `root`) merecem uma **segunda revisão humana** antes do go-live; até lá, o deploy pode ser feito à mão |
| 5 | Parceiro piloto | **Unyboss**, com **Asaas**. Coincide com o primeiro adaptador (M5). Como o piloto é da própria casa, convém também **um parceiro externo** antes de abrir para o público, para testar a personalização com outra marca |

## 11. Rastreabilidade

| Marco | Requisitos e documentos |
|---|---|
| M0 | UNY-DOC-004 (6.2), UNY-DOC-005 (2), UNY-DOC-008 (16) |
| M1 | BR-TEN, FS-PER, UNY-DOC-004 (5, 6), UNY-DOC-008 (9.3) |
| M2 | BR-CAT, BR-STU, UNY-DOC-003 (4), UNY-DOC-007 (8) |
| M3 | BR-CUS, BR-ADM, UNY-DOC-003 (5) |
| M4 | BR-PAY-006 a 009, UNY-DOC-003 (5.4, 8.1) |
| M5 | BR-PAY, UNY-DOC-004 (8), UNY-DOC-006 |
| M6 | BR-INT, BR-STU-010, UNY-DOC-004 (9), UNY-DOC-005 (7), UNY-DOC-006 (7) |
| M7 | BR-CER, UNY-DOC-003 (7.5, 8.2) |
| M8 | BR-OPR, BR-NFR-004, UNY-DOC-008 (14) |
| M9 | BR-NFR, UNY-DOC-008 (4.4, 15, 17, 19) |

## 12. Próximo passo

Fazer a **etapa I0** do servidor (firewall e porta 3306), que não depende do AVA, e criar o repositório privado no GitHub (decisão 3). Depois disso, o primeiro código é o **M0**.
