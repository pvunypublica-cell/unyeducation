# UNY-DOC-005 · Banco de Dados

**Projeto:** UnyEducation
**Versão:** 0.2 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Depende de:** UNY-DOC-002 (requisitos), UNY-DOC-003 (funcional), UNY-DOC-004 (arquitetura)

---

## 1. Como ler este documento

Este documento descreve o **modelo lógico** do banco do AVA: tabelas, colunas principais, relacionamentos, estados e regras de integridade. Ele **não** é o script SQL final: os tipos exatos e as migrações saem na implementação.

- Cada tabela indica o **módulo** de origem (UNY-DOC-004, seção 7) e os **requisitos** que ela atende.
- Só aparecem as colunas que importam para entender o modelo. Colunas comuns estão na seção 3.
- Itens marcados com **[A CONFIRMAR]** dependem de verificação ou decisão (lista na seção 17).

## 2. Decisões de base

| # | Decisão | Motivo |
|---|---|---|
| 1 | **Banco novo e separado** do banco do painel, com usuário de acesso próprio | Isolamento previsto no UNY-DOC-004, seção 4.2 |
| 2 | **Banco único para todos os parceiros**, com `tenant_id` em toda tabela de negócio | UNY-DOC-004, seção 6 |
| 3 | Mecanismo **InnoDB**, charset **utf8mb4**, uma única collation (**utf8mb4_unicode_ci**) em todas as tabelas e colunas de texto | O painel já sofreu com divergência de collation entre tabelas (caso do `idPolo`); aqui isso é proibido desde o início |
| 4 | Nomes de tabelas e colunas em **português, minúsculas, sem acento, com sublinhado** | Coerente com a documentação e com o hábito do ecossistema |
| 5 | Chave primária numérica interna (`id`) **mais** um `uuid` para tudo que aparece em endereço ou integração externa | Evita expor sequências e permite mudar infraestrutura sem quebrar links |
| 6 | Datas e horas gravadas em **UTC**; conversão para o fuso do usuário só na tela | Evita erros de horário de verão e de servidor |
| 7 | **Nada de apagar** matrículas, certificados, eventos de pagamento e auditoria | FS-REG-002, FS-REG-010, BR-NFR-005 |
| 8 | Colunas com valores fixos (estados, tipos) são **texto controlado pela aplicação**, não `ENUM` do banco | Alterar `ENUM` exige migração pesada; texto com validação é mais fácil de evoluir |
| 9 | **Compatível com MySQL 5.7.42** (versão medida no servidor, UNY-DOC-008 seção 3): sem CTE nem funções de janela; `CHECK` não é aplicado, então toda regra de domínio é validada pela aplicação e por testes; sem `SKIP LOCKED` na fila; charset e collation declarados **em cada tabela**, pois o padrão do servidor pode ser `latin1` | Evita depender de recursos do MySQL 8.0 que o servidor não tem |
| 10 | **Auditoria em schema separado** (`ava_audit`), onde o usuário da aplicação só tem `INSERT` e `SELECT`. O MySQL 5.7 não permite revogar parte de um privilégio (UNY-DOC-008, seção 9) | Torna a auditoria realmente de apenas inclusão |

## 3. Colunas comuns

| Coluna | Presente em | Significado |
|---|---|---|
| `id` | Todas | Chave primária numérica |
| `uuid` | Tabelas que aparecem em endereços ou integrações | Identificador público |
| `tenant_id` | **Toda tabela de negócio** | Parceiro dono do registro (aponta para `parceiros.id`) |
| `criado_em` | Todas | Data e hora de criação (UTC) |
| `atualizado_em` | Tabelas que sofrem alteração | Data e hora da última alteração (UTC) |

## 4. Mapa de relacionamentos

```text
operadores ─── (global)           planos ─── parceiros ─┬── dominios
                                                        ├── parceiro_painel
                                                        ├── membros_equipe
                                                        └── (tudo abaixo leva tenant_id)

alunos ──< matriculas >── cursos ──< modulos ──< aulas ──< materiais
              │             │  └──< curso_categoria >── categorias
              │             └──< trilha_cursos >── trilhas
              ├──< matricula_historico
              ├──< progresso_aulas >── aulas
              ├──── certificados >── modelos_certificado
              └──< painel_mensagens

integracoes_pagamento ──< produtos_integracao >── cursos
          └──< eventos_pagamento ──▶ matriculas

codigos_acesso ──< resgates_codigo      importacoes ──< importacao_linhas
configuracoes_visuais · paginas · arquivos · auditoria · consentimentos · fila_tarefas
```

## 5. Tenancy e plataforma

### 5.1 `operadores` (global)
Equipe da plataforma. *(BR-OPR)*

| Coluna | Tipo | Regras |
|---|---|---|
| nome, email | texto | email único |
| senha_hash | texto | nunca em texto aberto |
| papel | texto | ex.: `administrador`, `suporte` |
| ativo | booleano | |

### 5.2 `planos` (global)
| Coluna | Tipo | Regras |
|---|---|---|
| nome | texto | |
| limite_alunos, limite_cursos | inteiro | nulo = sem limite |
| limite_armazenamento_mb | inteiro | |
| ativo | booleano | |

### 5.3 `parceiros` (é o tenant)
| Coluna | Tipo | Regras |
|---|---|---|
| uuid | uuid | único |
| slug | texto(59) | único, minúsculo, regras do UNY-DOC-004 seção 5.2, **imutável** na prática |
| nome | texto | |
| plano_id | referência | para `planos` |
| estado | texto | `ativo`, `suspenso`, `encerrado` |
| criado_por | referência | para `operadores` |

*(BR-TEN-001, 003, 006)*

### 5.4 `dominios`
| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| host | texto | **único em toda a plataforma** |
| tipo | texto | `subdominio_padrao`, `proprio` |
| estado | texto | `aguardando_dns`, `verificado`, `certificado_emitido`, `ativo`, `erro` |
| principal | booleano | só um por parceiro |
| ultimo_erro | texto | visível ao parceiro |
| verificado_em | data-hora | |

*(BR-TEN-004, FS-ADM-080)*

### 5.5 `parceiro_painel`
Vínculo obrigatório do parceiro do AVA com o parceiro ou polo do painel. *(BR-INT-003)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | **único** (um vínculo por parceiro) |
| polo_painel_id | texto | identificador do polo no painel |
| situacao_certificacao | texto | `habilitado`, `retido`, `desconhecido` (cópia da última resposta do painel, não a fonte da verdade) |
| situacao_motivo | texto | motivo devolvido pelo painel |
| situacao_atualizada_em | data-hora | |

### 5.6 `membros_equipe`
Pessoas da equipe do parceiro. *(BR-ADM-005)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| nome, email | texto | **único por parceiro** (`tenant_id` + `email`) |
| senha_hash | texto | |
| papel | texto | `administrador`, `instrutor`, `suporte` |
| pode_publicar | booleano | vale para instrutor (suposição 3 do UNY-DOC-003) |
| estado | texto | `convidado`, `ativo`, `inativo` |

Regra: o último administrador ativo de um parceiro não pode ser removido ou desativado (FS-ADM-060).

## 6. Identidade

### 6.1 `alunos`
Conta do aluno, **pertencente a um parceiro**. *(BR-TEN-005, BR-STU-007, BR-STU-010 a 012)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| uuid | uuid | |
| nome | texto | |
| email | texto | **único por parceiro** |
| senha_hash | texto | nulo enquanto o aluno só usa link mágico |
| cpf_cript | binário | CPF criptografado (reversível, necessário para enviar ao painel). Ver seção 7 |
| cpf_hash | texto | CPF protegido por hash com chave secreta (**índice cego**), usado para busca e unicidade |
| cpf_mascara | texto | forma mascarada para telas (ex.: `***.***.*45-**`) |
| cpf_chave_versao | inteiro | qual chave de criptografia foi usada (permite rotacionar) |
| cpf_informado_em | data-hora | nulo = CPF ainda não informado |
| telefone | texto | opcional |
| foto_arquivo_id | referência | opcional |
| estado | texto | `ativo`, `inativo`, `anonimizado` |
| ultimo_acesso_em | data-hora | |
| email_verificado_em | data-hora | |

Índices e regras:

- Único: (`tenant_id`, `email`)
- Único: (`tenant_id`, `cpf_hash`), aceitando valor nulo enquanto o CPF não foi informado
- O CPF é **único por parceiro**, não na plataforma inteira, para que a mesma pessoa possa estudar em parceiros diferentes **[A CONFIRMAR: se o painel exige unicidade global]**

### 6.2 `tokens_autenticacao`
Links mágicos, redefinição de senha e convites. *(BR-STU-007)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| tipo | texto | `link_magico`, `redefinir_senha`, `convite_equipe`, `primeiro_acesso` |
| titular_tipo | texto | `aluno` ou `equipe` |
| titular_id | referência | |
| token_hash | texto | só o hash é guardado, nunca o token |
| expira_em | data-hora | |
| usado_em | data-hora | uso único |

Tokens expirados ou usados são removidos periodicamente.

**Sessões** ficam na estrutura do próprio framework. Cada sessão carrega o `tenant_id` e nunca vale em outro parceiro (UNY-DOC-004, seção 5.7).

## 7. Tratamento do CPF

O CPF é **dado pessoal** e **obrigatório** para espelhar a matrícula e liberar o certificado (BR-STU-010 a 012). Regras:

| # | Regra |
|---|---|
| 1 | **Nunca em texto aberto no banco.** Guardado em `cpf_cript`, com criptografia de coluna |
| 2 | A busca e a unicidade usam `cpf_hash` (hash com **chave secreta**, não um hash simples, porque o espaço de CPFs é pequeno e um hash simples seria quebrado por força bruta) |
| 3 | **Chaves** de criptografia e de hash ficam **fora do banco e fora do repositório**; `cpf_chave_versao` permite rotação |
| 4 | **Validação** dos dígitos verificadores antes de gravar; sequências repetidas (ex.: `111.111.111-11`) são rejeitadas |
| 5 | Telas e listas usam **`cpf_mascara`**. O CPF completo só é descriptografado em: envio ao painel, exibição para quem tem permissão e exportação autorizada |
| 6 | **Toda visualização completa ou exportação gera registro de auditoria** (quem, quando, motivo) |
| 7 | **Logs da aplicação nunca contêm CPF.** Mensagens de erro e rastreamentos também não |
| 8 | Conteúdo bruto de webhooks pode conter CPF: a coluna é **criptografada** e tem **prazo de retenção** (seção 15) |
| 9 | O CPF **não é copiado** para tabelas de fila ou de mensagens; o envio ao painel monta a mensagem a partir do dado atual no momento do envio |
| 10 | Pedido de exclusão do titular **anonimiza** o aluno (nome, e-mail, CPF); os efeitos sobre certificados já emitidos e sobre o registro no painel exigem decisão jurídica **[A CONFIRMAR]** |

### Como o CPF chega

| Origem | Como |
|---|---|
| Compra em plataforma de venda | Se o evento trouxer o CPF e ele for válido, é gravado no aluno |
| Primeiro acesso | Tela "Complete seu cadastro" (FS-STU-004) |
| Matrícula manual, importação, código de acesso | Campo de CPF; se faltar, o aluno é solicitado depois (FS-ADM-034) |

Matrícula sem CPF: **ativa para estudo**, com `espelhamento_estado = aguardando_cpf` (seção 9).

## 8. Catálogo

### 8.1 `categorias`
| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, nome, slug | | slug único por parceiro |
| ordem | inteiro | |
| ativa | booleano | |

### 8.2 `cursos`
*(BR-CAT-001, 004, 005, 007; BR-CER-001)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, uuid | | |
| titulo, slug | texto | slug único por parceiro |
| descricao | texto longo | conteúdo tratado e limpo antes de exibir |
| capa_arquivo_id | referência | capa vertical 2:3 (padrão) |
| capa_horizontal_arquivo_id | referência | capa 16:9, opcional |
| trailer_provedor, trailer_ref | texto | vídeo no provedor externo |
| instrutor_id | referência | para `membros_equipe` |
| estado | texto | `rascunho`, `publicado`, `arquivado` |
| gratuito | booleano | |
| carga_horaria_min | inteiro | estimada |
| certificado_ativo | booleano | |
| certificado_percentual_minimo | inteiro | padrão 100 (regra de emissão) |
| avaliacoes_ativas | booleano | **padrão falso**; habilita provas e tarefas no curso (BR-AVA-015) |
| certificado_exige_avaliacoes | booleano | exige aprovação em todas as avaliações obrigatórias; só vale com `avaliacoes_ativas` ligada |
| modelo_certificado_id | referência | opcional; vazio = modelo padrão do parceiro |
| publicado_em | data-hora | |

### 8.3 `curso_categoria`
Relação muitos-para-muitos (um curso em várias categorias, sem duplicar o curso). Chave: (`curso_id`, `categoria_id`).

### 8.4 `modulos`
| Coluna | Tipo |
|---|---|
| tenant_id, curso_id, titulo, ordem | |
| capa_arquivo_id | referência (capa vertical do módulo, usada na vitrine "Módulos de um curso") |

### 8.5 `aulas`
*(BR-CAT-002, 006)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, modulo_id, curso_id | referências | `curso_id` repetido para acelerar consultas |
| titulo, ordem | | |
| tipo | texto | `video`, `audio`, `texto`, `tarefa`, `prova` |
| conteudo_texto | texto longo | para aulas em texto |
| video_provedor, video_ref | texto | identificador no provedor (o AVA não guarda o vídeo) |
| duracao_seg | inteiro | |
| publicada | booleano | só aulas publicadas contam no progresso (FS-REG-021) |
| liberacao_tipo | texto | `imediata`, `data`, `apos_aula`, `apos_aprovacao` |
| liberacao_data | data-hora | quando `data` |
| liberacao_aula_id | referência | quando `apos_aula` ou `apos_aprovacao` (aula ou avaliação de referência) |

### 8.6 `materiais`
Arquivos para download vinculados a uma aula: `tenant_id`, `aula_id`, `arquivo_id`, `titulo`, `ordem`.

### 8.7 `trilhas` e `trilha_cursos`
`trilhas`: `tenant_id`, `titulo`, `descricao`, `ordem`, `ativa`. `trilha_cursos`: (`trilha_id`, `curso_id`, `ordem`). *(BR-CAT-003)*

### 8.8 `arquivos`
Registro de todo arquivo enviado. *(UNY-DOC-004, seção 10.2)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, uuid | | |
| caminho | texto | caminho interno, **fora da pasta pública**, incluindo o tenant |
| nome_original, mime | texto | tipo verificado pelo conteúdo, não só pela extensão |
| tamanho_bytes | inteiro | conta para o limite do plano |
| hash_sha256 | texto | integridade |
| publico | booleano | `true` só para logo, banners e capas; materiais exigem matrícula |
| enviado_por_tipo, enviado_por_id | | |

## 9. Matrículas e aprendizado

### 9.1 `matriculas`
Coração do sistema. *(BR-PAY, BR-INT, FS-REG-001 a 004)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, uuid | | |
| aluno_id, curso_id | referências | |
| estado | texto | `pendente`, `ativa`, `suspensa`, `expirada`, `cancelada` |
| origem | texto | `nativo`, `generico`, `manual`, `codigo`, `importacao`, `painel` |
| integracao_id | referência | quando a origem for uma integração |
| compra_ref | texto | identificador da compra na plataforma de venda |
| motivo | texto | **obrigatório** quando a origem é `manual` (FS-ADM-030) |
| responsavel_id | referência | quem liberou, quando manual |
| expira_em | data-hora | opcional |
| concluida_em | data-hora | quando cumpriu a regra do curso |
| percentual_concluido | inteiro | cache do progresso |
| **painel_matricula_id** | texto | identificador devolvido pelo painel |
| **espelhamento_estado** | texto | `pendente`, `aguardando_cpf`, `enviado`, `confirmado`, `erro` |
| espelhamento_atualizado_em | data-hora | |

Regras:

- Único: (`tenant_id`, `aluno_id`, `curso_id`). **Uma matrícula por aluno e curso**; nova compra após expiração **reativa a mesma** (progresso preservado, FS-REG-003).
- `estado` (acesso) e `espelhamento_estado` (painel) são **independentes**: matrícula ativa pode estar `aguardando_cpf`.
- Matrícula **nunca é excluída**.
- Matrícula manual sem `motivo` ou `responsavel_id` é rejeitada.

### 9.2 `matricula_historico`
Toda mudança de estado. *(FS-REG-001)*

| Coluna | Tipo |
|---|---|
| tenant_id, matricula_id | |
| estado_anterior, estado_novo | texto |
| causa | texto (ex.: `compra_aprovada`, `reembolso`, `expiracao`, `acao_manual`) |
| ator_tipo | `sistema`, `equipe`, `operador` |
| ator_id | referência |
| evento_pagamento_id | referência, quando a causa for um evento |

### 9.3 `progresso_aulas`
*(BR-STU-002, 003; FS-STU-040 a 042)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, matricula_id, aula_id | | **único** por (`matricula_id`, `aula_id`) |
| posicao_seg | inteiro | ponto em que parou |
| percentual_assistido | inteiro | |
| concluida | booleano | |
| concluida_em | data-hora | |
| atualizado_em | data-hora | usado em "continuar assistindo" |

O progresso pertence à **matrícula**, por isso sobrevive à suspensão e à reativação.

### 9.4 `favoritos`
(`tenant_id`, `aluno_id`, `curso_id`), único. *(BR-STU-005)*

### 9.5 `codigos_acesso` e `resgates_codigo`
*(BR-PAY-009)*

`codigos_acesso`: `tenant_id`, `curso_id`, `codigo` (único por parceiro), `limite_usos`, `usos`, `expira_em`, `ativo`, `criado_por`.
`resgates_codigo`: `codigo_id`, `aluno_id`, `matricula_id`, `criado_em`. O contador `usos` é atualizado dentro de uma transação para evitar passar do limite.

### 9.6 `importacoes` e `importacao_linhas`
*(BR-PAY-008, FS-ADM-032)*

`importacoes`: `tenant_id`, `enviado_por`, `arquivo_id`, `estado` (`prévia`, `confirmada`, `processando`, `concluida`, `erro`), `total`, `validas`, `com_erro`.
`importacao_linhas`: `importacao_id`, `linha`, `dados_json` (**sem CPF em claro**: o CPF fica criptografado ou apenas indicado como informado), `resultado`, `mensagem`.

### 9.7 Avaliações (provas e tarefas)

*(BR-AVA; FS-AVA-001 a 020)*

> **Entrega na fase 2.** As tabelas desta seção entram por migração na fase 2 (UNY-DOC-002, seção 12.1). Na fase 1, o editor só oferece aulas de tipo `video`, `audio` e `texto`, e `cursos.avaliacoes_ativas` permanece falso.

Uma prova ou tarefa é uma **aula de tipo especial** (`aulas.tipo` = `prova` ou `tarefa`), então reaproveita ordem, módulo, liberação e progresso.

**`avaliacoes`** (configuração, uma por aula do tipo prova ou tarefa)

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, aula_id | referências | `aula_id` **único** |
| tipo | texto | `prova`, `tarefa` |
| obrigatoria | booleano | entra na regra de conclusão |
| enunciado | texto longo | principalmente para tarefas |
| nota_maxima | decimal | padrão 10 |
| nota_aprovacao_percentual | inteiro | mínimo para aprovar |
| tentativas_max | inteiro | nulo = ilimitadas (prova) ou reenvios (tarefa) |
| tempo_limite_min | inteiro | só prova; nulo = sem limite |
| abre_em, fecha_em | data-hora | janela opcional |
| aceita_atraso | booleano | só tarefa |
| mostrar_gabarito | texto | `nunca`, `apos_aprovacao`, `apos_esgotar` |
| embaralhar_questoes, embaralhar_alternativas | booleano | só prova |
| entrega_tipos_json | json | `arquivo`, `texto`, `link` (tarefa) |
| entrega_max_arquivos, entrega_max_mb | inteiro | limites da tarefa |
| extensoes_permitidas_json | json | tipos de arquivo aceitos |

**`questoes`**

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, avaliacao_id | referências | |
| tipo | texto | `unica`, `multipla`, `verdadeiro_falso` (`dissertativa` fica para P2) |
| enunciado | texto longo | |
| peso | decimal | |
| explicacao | texto | exibida no gabarito |
| ordem | inteiro | |
| arquivada | booleano | questão com respostas registradas é **arquivada**, não excluída (FS-AVA-017) |

**`alternativas`:** `tenant_id`, `questao_id`, `texto`, `correta` (booleano), `ordem`.

**`tentativas_prova`**

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, uuid | | |
| avaliacao_id, matricula_id | referências | |
| numero | inteiro | único por (`avaliacao_id`, `matricula_id`, `numero`) |
| estado | texto | `em_andamento`, `enviada`, `expirada` |
| iniciada_em, expira_em, enviada_em | data-hora | `expira_em` calculado ao iniciar |
| nota, percentual | decimal | preenchidos após a correção |
| aprovada | booleano | |
| ordem_questoes_json | json | ordem sorteada para esta tentativa |

**`respostas_prova`:** `tenant_id`, `tentativa_id`, `questao_id`, `alternativas_marcadas_json`, `correta` (booleano), `pontos` (decimal). Único: (`tentativa_id`, `questao_id`).

**`entregas_tarefa`**

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, uuid | | |
| avaliacao_id, matricula_id | referências | |
| numero | inteiro | reenvios numerados |
| estado | texto | `enviada`, `em_correcao`, `devolvida`, `aprovada`, `reprovada` |
| texto, link | texto | conforme tipos permitidos |
| atrasada | booleano | |
| nota | decimal | opcional |
| feedback | texto longo | |
| corrigida_por | referência | para `membros_equipe` |
| enviada_em, corrigida_em | data-hora | |

**`entrega_arquivos`:** `entrega_id`, `arquivo_id`. Os arquivos ficam em `arquivos`, **fora da pasta pública**, com verificação de vírus e acesso só para o aluno e a equipe do curso (RN-AVA-03 e RN-AVA-04).

**`resultados_avaliacao`** (resumo por matrícula, usado pelo progresso e pela regra do certificado)

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, matricula_id, avaliacao_id | | **único** |
| estado | texto | `pendente`, `em_andamento`, `aguardando_correcao`, `aprovada`, `reprovada` |
| melhor_nota | decimal | |
| tentativas_usadas | inteiro | |
| concluida_em | data-hora | |

Regra de conclusão do curso: `matriculas.concluida_em` só é preenchida quando a regra de aulas é cumprida **e**, se `cursos.avaliacoes_ativas` e `cursos.certificado_exige_avaliacoes`, todas as avaliações **obrigatórias** do curso têm `resultados_avaliacao.estado = aprovada`.

Toda alteração de nota ou decisão de correção gera registro em `auditoria` (RN-AVA-05).

### 9.8 Comentários e reações por aula (proposta, P1)

*(BR-STU-013, BR-STU-014; FS-STU-046, 049; FS-ADM-090 a 092)*

**`comentarios_aula`**

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, aula_id | referências | |
| autor_tipo, autor_id | | `aluno` ou `equipe` |
| pai_id | referência | resposta a outro comentário; **no máximo dois níveis** |
| texto | texto | limpo antes de exibir |
| estado | texto | `visivel`, `oculto`, `removido_pelo_autor` |
| ocultado_por, motivo_ocultacao | | quando oculto pela equipe |
| criado_em, editado_em | data-hora | |

Índice: (`tenant_id`, `aula_id`, `criado_em`).

**`reacoes_aula`:** `tenant_id`, `aula_id`, `aluno_id`, `tipo` (`curtir`, `nao_curtir`). Único: (`aula_id`, `aluno_id`).

Comentários contêm dados pessoais: entram na **exportação** do titular e são **anonimizados** na exclusão (FS-STU-051).

## 10. Pagamentos

### 10.1 `adaptadores_pagamento` (global)
Catálogo de adaptadores: `codigo` (ex.: `hotmart`, `kiwify`, `generico`), `nome`, `nivel` (`nativo`, `generico`), `ativo`. *(BR-PAY-013)*

### 10.2 `integracoes_pagamento`
Uma integração configurada por um parceiro. *(FS-PAY-001, 002)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| uuid_publico | uuid | faz parte do endereço do webhook; **não adivinhável** |
| adaptador_id | referência | |
| nome | texto | |
| estado | texto | `ativa`, `com_erro`, `desativada` |
| segredos_cript | binário | segredo do webhook **e**, quando a plataforma exige (Asaas, Mercado Pago), a credencial de API do parceiro; tudo **criptografado**; nunca devolvido depois de salvo (RN-PAY-05) |
| mapeamento_json | json | mapeamento de campos e status do webhook genérico |
| ultimo_evento_em | data-hora | |

### 10.3 `produtos_integracao`
Vínculo entre produtos da plataforma de venda e cursos. *(BR-PAY-003, FS-ADM-012)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, integracao_id, curso_id | | |
| produto_externo_id | texto | identificador na plataforma de venda |

Único: (`integracao_id`, `produto_externo_id`, `curso_id`). Um produto pode liberar mais de um curso.

### 10.4 `eventos_pagamento`
Registro de tudo que chegou. *(BR-PAY-004, 005; FS-PAY-003, 005)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, integracao_id | | |
| id_externo | texto | identificador do evento na origem; **derivado** quando a plataforma não envia um estável (UNY-DOC-006, seção 3.4) |
| tipo_externo | texto | como a plataforma chamou |
| tipo_interno | texto | `compra_aprovada`, `compra_reembolsada`, `chargeback`, `compra_cancelada`, `compra_recusada`, `assinatura_renovada`, `assinatura_atrasada`, `assinatura_cancelada`, `disputa_aberta`, `informativo` |
| payload_bruto | binário | conteúdo recebido, **criptografado** (pode conter CPF) |
| estado | texto | `recebido`, `processado`, `ignorado`, `erro` |
| tentativas | inteiro | |
| erro_msg | texto | |
| matricula_id | referência | matrícula afetada |
| compra_ref | texto | identificador da compra ou assinatura na origem; usado para ordenar eventos da mesma compra (reembolso e chargeback são terminais) |
| recebido_em, processado_em | data-hora | |

**Idempotência:** único (`integracao_id`, `id_externo`). Evento repetido é reconhecido e ignorado.
Índices de apoio: (`tenant_id`, `estado`, `recebido_em`) para a tela de conciliação e (`tenant_id`, `compra_ref`) para ordenar eventos da mesma compra.

## 11. Certificados

### 11.1 `modelos_certificado`
*(BR-CER-002, 006; FS-CER-010)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, nome | | |
| padrao | booleano | um por parceiro |
| fundo_arquivo_id | referência | |
| definicao_json | json | elementos, posições e campos dinâmicos, **validado** antes de gravar |

Regra: a definição **precisa** conter o bloco do hash e do QR Code; o sistema recusa modelos sem eles (FS-REG-031).

### 11.2 `certificados`
*(BR-CER-003 a 013, BR-INT-005; FS-CER-011 a 019; FS-REG-010 a 012)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id, uuid | | |
| matricula_id | referência | **único**: um certificado por matrícula |
| aluno_id, curso_id | referências | |
| modelo_id | referência | |
| estado | texto | `pendente`, `emitido`, `revogado` |
| motivo_pendencia | texto | `parceiro_fora_de_dia`, `matricula_nao_regularizada`, `aguardando_painel`, `aguardando_cpf` |
| hash_painel | texto | **gerado pelo painel**, único, imutável; nulo enquanto `pendente` |
| url_validacao | texto | endereço de validação devolvido pelo painel |
| dados_json | json | **cópia congelada** do que aparece no certificado (nome, curso, carga horária, datas) |
| arquivo_id | referência | PDF gerado |
| liberacao_manual | booleano | liberado ou retido por ato do operador |
| tentativas_liberacao | inteiro | |
| ultima_tentativa_em | data-hora | |
| emitido_em, revogado_em | data-hora | |
| motivo_revogacao | texto | |

Regras:

- **Sem hash, sem certificado emitido**: `estado = emitido` exige `hash_painel` preenchido (FS-CER-019, FS-REG-012).
- `dados_json` é um **retrato do momento da emissão**; corrigir o nome do aluno depois não altera um certificado já emitido (a correção segue o fluxo de revogação e nova emissão).
- Nunca excluído.
- Índice para a tela de retidos: (`tenant_id`, `estado`, `motivo_pendencia`).

### 11.3 `certificado_vias`
Segundas vias (BR-CER-005): `certificado_id`, `arquivo_id`, `gerado_em`, `gerado_por`. O **hash é sempre o mesmo**.

## 12. Ponte com o painel

### 12.1 `painel_mensagens` (saída)
Fila de mensagens do AVA para o painel. *(UNY-DOC-004, seção 9; BR-INT-001, 005)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| msg_uuid | uuid | **único**; identificador de idempotência enviado ao painel |
| tipo | texto | `espelhar_matricula`, `atualizar_matricula`, `pedir_liberacao`, `confirmar_emissao`, `informar_revogacao` |
| referencia_tipo, referencia_id | | o que a mensagem trata (matrícula ou certificado) |
| estado | texto | `pendente`, `enviando`, `enviada`, `confirmada`, `erro` |
| tentativas | inteiro | |
| proxima_tentativa_em | data-hora | reenvio com intervalo crescente |
| ultimo_erro | texto | **sem CPF** |
| resposta_json | json | resposta do painel (identificador, habilitado ou retido, motivo) |
| enviada_em, confirmada_em | data-hora | |

**Não guarda o conteúdo do aluno.** A mensagem é montada no envio, a partir dos dados atuais.
Índice: (`estado`, `proxima_tentativa_em`).

### 12.2 `painel_entradas` (entrada, P1)
Mensagens do painel para o AVA (matrícula criada ou aprovada no painel, BR-INT-002): `msg_uuid` (único), `tipo`, `estado`, `resultado`, `recebida_em`. O `msg_uuid` único impede processar a mesma mensagem duas vezes.

### 12.3 Divergências
Não é uma tabela: é uma **consulta** que compara `matriculas` com `espelhamento_estado` diferente de `confirmado` há mais que um prazo, mais as mensagens em `erro` (BR-INT-006).

## 13. Tema, vitrine e páginas

### 13.1 `configuracoes_visuais`
Tema e home em **rascunho e publicado**. *(BR-CUS-001 a 006; FS-ADM-040 a 043; FS-REG-030)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | |
| tipo | texto | `tema`, `home`, `menu` |
| estado | texto | `rascunho`, `publicado` |
| versao | inteiro | incrementa a cada publicação |
| conteudo_json | json | tokens de design (cores, fontes, logos), banners e vitrines, **validado por esquema** antes de gravar |
| atualizado_por | referência | |
| publicado_em | data-hora | |

Único: (`tenant_id`, `tipo`, `estado`). O aluno **só lê** o `publicado`.
Os históricos de versões anteriores publicadas ficam em `configuracoes_visuais_historico` (mesma estrutura, apenas inclusão) para permitir voltar atrás.

Referências a cursos e arquivos dentro do JSON são **conferidas na hora de exibir**: curso arquivado ou removido da vitrine simplesmente não aparece.

### 13.2 `paginas`
Páginas institucionais (BR-CUS-007): `tenant_id`, `slug` (único por parceiro), `titulo`, `conteudo` (limpo antes de exibir), `publicada`.

### 13.3 `configuracoes_ambiente`
Pares chave/valor por parceiro: idioma, e-mail de suporte, regras de acesso, remetente e rodapé dos e-mails. Único: (`tenant_id`, `chave`).

## 14. Auditoria, LGPD e operação

### 14.1 `auditoria` (apenas inclusão)
*(BR-TEN-007, BR-NFR-005; FS-PER-002)*

| Coluna | Tipo | Regras |
|---|---|---|
| tenant_id | referência | nulo para ações globais |
| ator_tipo, ator_id | | `sistema`, `operador`, `equipe`, `aluno` |
| acao | texto | ex.: `matricula.criar`, `aluno.cpf_visualizar`, `certificado.revogar` |
| alvo_tipo, alvo_id | | |
| dados_json | json | o que mudou; **nunca CPF em claro** |
| ip, user_agent | texto | |
| hash_anterior, hash_registro | texto | **encadeamento opcional (P2)** para detectar adulteração |
| criado_em | data-hora | |

**Proteção:** o usuário do banco usado pela aplicação tem permissão de **inserir e ler**, mas **não** de alterar ou apagar esta tabela.
Índices: (`tenant_id`, `criado_em`), (`alvo_tipo`, `alvo_id`).

### 14.2 `acessos_suporte`
Entradas do operador no ambiente de um parceiro: `operador_id`, `tenant_id`, `motivo` (**obrigatório**), `inicio`, `fim`. *(FS-OPR-003)*

### 14.3 `consentimentos`
`tenant_id`, `aluno_id`, `tipo` (ex.: `termos`, `privacidade`), `versao_texto`, `aceito_em`, `ip`. *(BR-NFR-004)*

### 14.4 `solicitacoes_titular`
Pedidos de exportação e exclusão: `tenant_id`, `aluno_id`, `tipo` (`exportacao`, `exclusao`), `estado`, `solicitada_em`, `concluida_em`, `responsavel_id`. *(FS-STU-051)*

### 14.5 Fila de tarefas
Tabelas `fila_tarefas` e `fila_tarefas_falhas`, no formato do framework escolhido (UNY-DOC-004, seção 8.4). Cada tarefa leva o `tenant_id` no conteúdo para que o contexto seja restaurado ao processar.

## 15. Integridade, índices e retenção

### 15.1 Isolamento garantido no banco

Além do filtro automático na aplicação, o banco **impede referências entre parceiros**:

- Toda tabela de negócio tem um **índice único** (`tenant_id`, `id`).
- Chaves estrangeiras são **compostas**: por exemplo, em `matriculas`, (`tenant_id`, `curso_id`) aponta para `cursos` (`tenant_id`, `id`).
- Resultado: é **impossível** gravar uma matrícula do parceiro A apontando para um curso do parceiro B, mesmo que haja um erro no código.

### 15.2 Regras de remoção

| Tabela | Regra |
|---|---|
| `matriculas`, `certificados`, `eventos_pagamento`, `auditoria`, `matricula_historico` | **Nunca excluídas** |
| `cursos`, `aulas` | Arquivados, não excluídos, quando houver matrícula ou progresso |
| `alunos` | **Anonimizados** em pedido de exclusão; não removidos |
| `tentativas_prova`, `respostas_prova`, `entregas_tarefa`, `resultados_avaliacao` | **Nunca excluídas**; anonimizadas junto com o aluno |
| `comentarios_aula` | Ocultados ou anonimizados, não apagados |
| `tokens_autenticacao` | Removidos depois de expirar ou serem usados |
| `parceiros` | Nunca excluídos; passam a `encerrado` |

### 15.3 Índices das consultas críticas

| Consulta | Índice |
|---|---|
| Resolver o parceiro pelo endereço | `dominios.host` (único), `parceiros.slug` (único) |
| Home: cursos publicados | `cursos` (`tenant_id`, `estado`, `publicado_em`) |
| Continuar assistindo | `progresso_aulas` (`tenant_id`, `matricula_id`, `atualizado_em`) |
| Lista de alunos e matrículas | `matriculas` (`tenant_id`, `curso_id`, `estado`) |
| Login do aluno | `alunos` (`tenant_id`, `email`) |
| Busca por CPF | `alunos` (`tenant_id`, `cpf_hash`) |
| Idempotência de webhooks | `eventos_pagamento` (`integracao_id`, `id_externo`) |
| Fila de envio ao painel | `painel_mensagens` (`estado`, `proxima_tentativa_em`) |
| Certificados retidos | `certificados` (`tenant_id`, `estado`, `motivo_pendencia`) |
| Auditoria por parceiro | `auditoria` (`tenant_id`, `criado_em`) |

### 15.4 Retenção (propostas, a validar)

| Dado | Retenção proposta |
|---|---|
| `payload_bruto` de eventos de pagamento | 90 dias (depois, só o resumo) **[A CONFIRMAR]** |
| `painel_mensagens` confirmadas | 180 dias |
| `tokens_autenticacao` | Até expirar ou usar |
| Auditoria | Sem prazo na v1 (revisar com a LGPD) |
| Matrículas, progresso, certificados | Enquanto o parceiro existir |

## 16. Estados consolidados

| Tabela | Coluna | Valores |
|---|---|---|
| `parceiros` | estado | `ativo`, `suspenso`, `encerrado` |
| `dominios` | estado | `aguardando_dns`, `verificado`, `certificado_emitido`, `ativo`, `erro` |
| `membros_equipe` | estado | `convidado`, `ativo`, `inativo` |
| `alunos` | estado | `ativo`, `inativo`, `anonimizado` |
| `cursos` | estado | `rascunho`, `publicado`, `arquivado` |
| `matriculas` | estado | `pendente`, `ativa`, `suspensa`, `expirada`, `cancelada` |
| `matriculas` | espelhamento_estado | `pendente`, `aguardando_cpf`, `enviado`, `confirmado`, `erro` |
| `matriculas` | origem | `nativo`, `generico`, `manual`, `codigo`, `importacao`, `painel` |
| `integracoes_pagamento` | estado | `ativa`, `com_erro`, `desativada` |
| `eventos_pagamento` | estado | `recebido`, `processado`, `ignorado`, `erro` |
| `certificados` | estado | `pendente`, `emitido`, `revogado` |
| `certificados` | motivo_pendencia | `parceiro_fora_de_dia`, `matricula_nao_regularizada`, `aguardando_painel`, `aguardando_cpf` |
| `painel_mensagens` | estado | `pendente`, `enviando`, `enviada`, `confirmada`, `erro` |
| `configuracoes_visuais` | estado | `rascunho`, `publicado` |
| `tentativas_prova` | estado | `em_andamento`, `enviada`, `expirada` |
| `entregas_tarefa` | estado | `enviada`, `em_correcao`, `devolvida`, `aprovada`, `reprovada` |
| `resultados_avaliacao` | estado | `pendente`, `em_andamento`, `aguardando_correcao`, `aprovada`, `reprovada` |
| `comentarios_aula` | estado | `visivel`, `oculto`, `removido_pelo_autor` |

## 17. Decisões e verificações pendentes

| # | Item | Tipo | Impacto |
|---|---|---|---|
| 1 | ~~Versão do MySQL~~ **Medida: 5.7.42.** Tem tipo JSON e colunas geradas; não tem CTE, janelas nem `CHECK` efetivo. Resta confirmar o charset padrão do servidor | Verificação | Seções 2, 10, 11, 13 |
| 2 | CPF único **por parceiro** ou global (o painel exige unicidade global?) | Verificação | Seção 6.1 |
| 3 | O painel exige **outros campos** do aluno além de nome, e-mail e CPF? | Verificação | `alunos`, UNY-DOC-004 9.4 |
| 4 | O painel já trava **CPF + curso** em outros fluxos (como nos formulários)? O espelhamento recebe a mesma trava? Se recusar, como o AVA trata a resposta? | Verificação | `matriculas`, `painel_mensagens` |
| 5 | Pedir o CPF **no primeiro acesso sem bloquear as aulas** (proposta) ou bloquear até informar | Decisão | FS-STU-004 e 005 |
| 6 | **Brecha comercial:** se o painel consumir crédito ao espelhar, matrículas sem CPF ficam fora da contagem até o aluno informar. Avaliar prazo ou regra | Decisão | Retenção e cobrança |
| 7 | Efeito do pedido de exclusão (LGPD) sobre certificados já emitidos e sobre o registro no painel | Decisão jurídica | Seção 7, regra 10 |
| 8 | Prazo de retenção do conteúdo bruto de webhooks e demais itens da seção 15.4 | Decisão | Seção 15.4 |
| 9 | Quem pode ver o **CPF completo**: só administrador do parceiro ou também suporte | Decisão | FS-ADM-022 |
| 10 | O catálogo **exibe preço**? Hoje a plataforma não guarda preço, só o vínculo com o produto do checkout | Decisão | `cursos` |
| 11 | Reaproveitar a criptografia de credenciais já usada no painel ou usar a do framework | Decisão | Seções 7 e 10.2 |
| 12 | Encadeamento de hash na auditoria já na v1 ou depois | Decisão | `auditoria` |

## 18. Dados iniciais (seed)

Ao criar o banco: planos padrão, catálogo de adaptadores (genérico primeiro), operador inicial, modelo de certificado padrão da plataforma, textos padrão de e-mail e de termos. Em **homologação**, mais um parceiro fictício com cursos e alunos de teste (sem CPF real).

## 19. Testes de banco obrigatórios

- Tentar gravar referência entre parceiros diferentes **deve falhar** (seção 15.1)
- Tentar ler dados de outro parceiro pelo caminho normal da aplicação **deve retornar vazio**
- Evento de pagamento repetido **não cria** segunda matrícula
- Mensagem repetida do painel **não processa** duas vezes
- `certificados` com `estado = emitido` e `hash_painel` vazio **deve ser recusado**
- O usuário do banco da aplicação **não consegue** alterar nem apagar `auditoria`
- CPF **nunca** aparece em texto aberto no banco, nos logs ou na auditoria (verificação automatizada)

## 20. Rastreabilidade

| Módulo (UNY-DOC-004) | Tabelas | Requisitos |
|---|---|---|
| Tenancy | `parceiros`, `dominios`, `planos`, `operadores`, `parceiro_painel` | BR-TEN, BR-OPR, BR-INT-003 |
| Identidade | `alunos`, `membros_equipe`, `tokens_autenticacao` | BR-STU-007, 010 a 012 |
| Catálogo | `cursos`, `modulos`, `aulas`, `categorias`, `trilhas`, `materiais`, `arquivos` | BR-CAT |
| Aprendizado | `progresso_aulas`, `favoritos` | BR-STU |
| Matrículas | `matriculas`, `matricula_historico`, `codigos_acesso`, `importacoes` | BR-PAY-006 a 009 |
| Pagamentos | `integracoes_pagamento`, `produtos_integracao`, `eventos_pagamento`, `adaptadores_pagamento` | BR-PAY |
| Certificados | `certificados`, `modelos_certificado`, `certificado_vias` | BR-CER |
| Ponte com o painel | `painel_mensagens`, `painel_entradas` | BR-INT |
| Tema e vitrine | `configuracoes_visuais`, `paginas`, `configuracoes_ambiente` | BR-CUS |
| Avaliações | `avaliacoes`, `questoes`, `alternativas`, `tentativas_prova`, `respostas_prova`, `entregas_tarefa`, `entrega_arquivos`, `resultados_avaliacao` | BR-AVA |
| Interação | `comentarios_aula`, `reacoes_aula` | BR-STU-013, 014 |
| Auditoria e LGPD | `auditoria`, `acessos_suporte`, `consentimentos`, `solicitacoes_titular` | BR-TEN-007, BR-NFR |

## 21. Próximo documento

**UNY-DOC-006 · API e webhooks:** endpoints do AVA, formato dos webhooks de cada plataforma de venda (consultando a documentação oficial atual de cada uma), contrato das mensagens entre AVA e painel e o que o painel precisa expor.
