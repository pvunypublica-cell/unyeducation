# UNY-DOC-008 · Infraestrutura, Segurança e Deploy

**Projeto:** UnyEducation
**Versão:** 0.6 · Foco: Cursos Livres
**Status:** Rascunho para validação
**Servidor medido em:** 07/10/2026 (saída de comandos fornecida pelo responsável)
**Pesquisa de suporte de software feita em:** 07/10/2026
**Verificação do PHP 8 para o Ubuntu 20.04 feita em:** 08/10/2026 (repositório Sury do `focal` vazio)
**Medições adicionais de 08/10/2026:** Docker, firewall, portas abertas, Ubuntu Pro e socket do MySQL
**Decisão de hospedagem:** 08/10/2026, opção A2 (Docker na VPS atual), com migração futura para VPS própria
**Depende de:** UNY-DOC-004 (arquitetura), UNY-DOC-005 (banco), UNY-DOC-006 (webhooks e ponte), UNY-DOC-007 (arquivos e mídia)

---

## 1. Como ler este documento

Este documento diz **onde e como o sistema roda**: servidor, rede, banco, processos, segurança, backup, deploy e monitoramento. Ele fecha o conjunto de documentos antes do código.

Etiquetas usadas:

| Etiqueta | Significa |
|---|---|
| **[MEDIDO]** | Veio da saída dos comandos executados no servidor |
| **[ESTIMADO]** | Cálculo ou ordem de grandeza; precisa ser confirmado com medição |
| **[A CONFIRMAR]** | Depende de decisão sua ou de uma verificação no servidor |

Trechos de configuração e de script são **ilustrativos**: mostram a intenção e os pontos delicados. A versão final fica no repositório e é testada em homologação antes de tocar produção.

O documento assume a stack proposta no UNY-DOC-004 (PHP 8 com Laravel). Se a stack mudar, mudam as seções 6, 10 e 16.

## 2. Resumo

1. O servidor é **mais antigo do que o AVA precisa**: o Ubuntu 20.04 já saiu do suporte padrão, o PHP instalado é o 7.2 e o MySQL é o 5.7. O AVA vai guardar **CPF**, então isso pesa.
2. A **verificação do PHP foi feita e deu negativa**: o repositório Sury do Ubuntu 20.04 está **vazio** (arquivo `Release` de 09/06/2025, lista de pacotes com 0 bytes). O **PHP 8.3 não pode ser instalado no sistema**. Decisão (08/10/2026): o AVA roda em **contêiner Docker na VPS atual (opção A2)**. O contêiner traz o próprio PHP 8.3 e **não toca no PHP 7.2** do legado. O Nginx e o MySQL 5.7 continuam sendo os do servidor.
3. A opção A2 só entra em produção com dado real se os pré-requisitos da seção 4.4 forem cumpridos. Os mais importantes: **Docker instalável e suportado neste servidor** (seção 6.2), **atualizações de segurança do sistema (Ubuntu Pro/ESM)**, **swap**, **backup fora do servidor**, **isolamento por contêiner e por usuário** e **imagem do PHP reconstruída com frequência**.
4. O AVA conversa com o painel **por API**, então a escolha do servidor **não é irreversível**: dá para mudar de máquina depois sem reescrever nada (seção 18).
5. Há **três armadilhas concretas** neste servidor, todas tratadas aqui: o Docker mexe nas regras de rede do servidor e **publica portas ignorando o firewall** (seção 6.2); o MySQL 5.7 **não tem como retirar permissão parcial** de uma tabela, então a auditoria vai para um schema separado (seção 9.3); e o **charset padrão do MySQL 5.7** costuma ser `latin1` (seção 9.5).
6. A **memória disponível** é folgada para começar (margem estimada de cerca de 2,6 GiB, já contando o Docker), mas **não há swap** e o **disco tem 32 GB livres**. Arquivos pesados (áudio, PDFs) precisam sair do disco cedo (seção 5.3).
7. **Nenhuma mudança deste documento foi aplicada no servidor.** Tudo aqui é plano; a ordem de implantação, com testes e reversão, está na seção 19.
8. **Achado urgente (medido em 08/10/2026), independente do AVA:** o servidor está **sem firewall** (`ufw` inativo, `iptables` sem regras) e o **MySQL escuta em `0.0.0.0:3306`**, ou seja, está alcançável pela internet. A seção 4.6 traz o roteiro para ativar o firewall e fechar essa porta com segurança, depois de confirmar que ninguém usa o MySQL de fora. Isso deve ser feito **antes** de instalar o Docker.
9. **ModSecurity já existe neste servidor** (módulo do Nginx compilado à mão), com `modsecurity on` em `/etc/nginx/nginx.conf` e `modsecurity off` nos hosts do painel. Os hosts novos do AVA **podem herdar esse filtro**, que já barrou nomes como "Walter" e com apóstrofo. A decisão está na seção 7.7.

---

## 3. Diagnóstico do servidor **[MEDIDO]**

| Item | Medido | Situação |
|---|---|---|
| Sistema operacional | Ubuntu 20.04.6 LTS (focal) | Suporte padrão encerrado em 31/05/2025. Sem assinatura Ubuntu Pro (ESM), **não há atualizações de segurança do sistema**. Com Pro, o suporte vai até maio de 2030 |
| Nginx | 1.18.0 (pacote do Ubuntu) | Compartilhado com todos os sites do servidor |
| PHP | 7.2.34, repositório Sury; **só a 7.2 instalada** | Sem suporte oficial desde nov/2020. Continua apenas por causa do legado |
| MySQL | 5.7.42 | Sem suporte desde out/2023 (informação geral; confirmar em dev.mysql.com) |
| CPU | 4 núcleos | Adequado para o início |
| Memória | 5,8 GiB total; 1,0 GiB em uso; 4,4 GiB disponíveis; 525 MiB de cache | Folga razoável, mas **uma medição só** |
| Swap | **Nenhuma** | Se a memória acabar, o sistema encerra processos à força (pode ser o MySQL) |
| Disco `/` | 145 GB; 106 GB usados; **32 GB livres (78%)** | Apertado para arquivos de parceiros |
| PHP 8.x para este sistema (verificado em 08/10/2026) | Release do `focal` datado de 09/06/2025; `Packages.gz` com 20 bytes; `Packages` com 0 bytes | **Repositório vazio.** Nenhum PHP 8 instalável pelo sistema |
| Docker | **Não instalado.** Candidatos: `docker.io` 26.1.3 e `docker-compose-v2` 2.27.1, do repositório `focal-updates/universe`; não há `/etc/docker/daemon.json` | Instalável por pacote do Ubuntu |
| Firewall | `ufw` **inativo**; `iptables` com política `ACCEPT` e **nenhuma regra** | **Sem firewall.** Qualquer porta que escute está exposta |
| Portas escutando | `0.0.0.0` e `::`: 22 (SSH), 80 e 443 (Nginx). `0.0.0.0`: **3306 (MySQL)**. Apenas em loopback: 53 (DNS local) | **MySQL exposto à internet** |
| Socket do MySQL | `/var/run/mysqld/mysqld.sock` | Usado pelo AVA (seção 6.3) |
| Encaminhamento de pacotes | `net.ipv4.ip_forward = 0` | O Docker vai ativar (esperado) |
| Ubuntu Pro | **Não vinculado** (`esm-infra` disponível) | Sem atualizações estendidas |
| Certificados | Certbot instalado via snap | Renovação automática já existe |
| Grupos de controle | `tmpfs` em `/sys/fs/cgroup` | Indica cgroups v1 (afeta a sintaxe de limite de memória, seção 10.1) |
| Sessão de acesso | Como `root` | A rotina diária deve passar para usuário comum (SEC-001) |

Limites desta medição: foi um instante, sem carga conhecida. Antes do go-live é preciso medir **uma semana** (seção 5.6).

## 4. O que isso muda

### 4.1 Achados e respostas

| # | Achado | Consequência | Resposta |
|---|---|---|---|
| 1 | Ubuntu 20.04 sem suporte padrão | Falhas de segurança do sistema **não são corrigidas** sem ESM. **E o ESM não cobre pacotes de repositórios de terceiros**, como o PHP do Sury: as correções do PHP dependem de o repositório continuar publicando para o 20.04 | Ubuntu Pro como pré-requisito; PHP 8.3 em **contêiner**, já que o repositório não publica para este sistema (seção 6.1); plano de saída datado (seção 18) |
| 2 | PHP 7.2 e outros sistemas legados no mesmo servidor | Uma falha num sistema antigo pode virar porta de entrada para o servidor | Usuário, pool e banco próprios do AVA; permissões estritas (seção 8). **Isolamento por usuário reduz o risco, não elimina** |
| 3 | MySQL 5.7 compartilhado | Faltam recursos do 8.0; charset padrão pode ser `latin1` | Seção 9: compatibilidade, charset explícito, schema separado para auditoria |
| 4 | Sem swap | Pico de memória pode derrubar o MySQL | Swap de 2 GiB e limites de memória por processo (seções 5.5 e 10) |
| 5 | 32 GB livres | Arquivos de parceiros enchem o disco | Teto para o AVA, armazenamento de arquivos desacoplado, backup **fora** do servidor |
| 6 | Servidor único | Um erro no Nginx ou um disco cheio afeta **todos** os sites | Teste de configuração antes de recarregar, janelas de mudança, snapshot antes de cada etapa |
| 7 | Instalar o Docker altera `iptables`, ativa `ip_forward` e publica portas **sem passar pelo `ufw`** | Pode afetar o firewall e a rede dos sistemas existentes, ou expor o PHP-FPM à internet | Snapshot e janela; comparar `iptables-save` antes e depois; publicar portas só em `127.0.0.1` (seção 6.2) |
| 8 | Acesso como `root` | Erro de digitação tem efeito máximo | Usuário `deploy` para a rotina (SEC-001) |
| 9 | **Sem firewall** e **MySQL em `0.0.0.0:3306`** | Banco dos sistemas atuais alcançável pela internet (força bruta, ou exploração de um MySQL 5.7 sem suporte) | Ativar o firewall e fechar a 3306 (seção 4.6), depois de confirmar que ninguém conecta de fora. **Não** trocar o `bind-address`: exige reinício do MySQL e afeta todos os sistemas |

### 4.2 Decisão de hospedagem

**Decisão registrada (ADR-001):** o AVA roda na VPS Contabo atual, como aplicação separada do painel.

O motivo apresentado foi a **conexão com o painel de matrículas**. Essa conexão, porém, é **por API assinada** (UNY-DOC-004, seção 9; UNY-DOC-006, seção 7): nenhum sistema lê ou grava no banco do outro. Por isso o AVA funciona **igual** em qualquer servidor. Hospedar junto é uma escolha de **custo e simplicidade**, não uma exigência técnica da conexão.

**Decisão complementar (08/10/2026):** o PHP 8.3 não pode ser instalado no sistema (seção 3). Decidiu-se rodar o AVA em **contêiner Docker na VPS atual (opção A2)** e migrar para uma VPS própria quando o projeto estiver mais estruturado. Como o AVA já nasce em contêiner e fala com o painel por API, a migração futura é trocar de servidor e subir o mesmo contêiner (seção 18).

### 4.3 Comparação

| Critério | **A2 · Mesma VPS, com Docker** (decidido) | **B · VPS própria do AVA** |
|---|---|---|
| Custo | Nenhum custo novo | Mensalidade adicional (a cotar) |
| Isolamento | Por contêiner (usuário, arquivos e memória próprios), mas **kernel, Nginx e MySQL compartilhados**. **Parcial** | Total |
| Software | Ubuntu 20.04 e MySQL 5.7 do servidor; **PHP 8.3 dentro do contêiner** | Livre: Ubuntu 24.04, MySQL 8, PHP 8.3 ou mais novo |
| Segurança do CPF | Depende de ESM, do repositório do PHP e da saúde dos sistemas vizinhos | Superfície bem menor |
| Falha de um sistema | Pode afetar os outros (CPU, disco, Nginx) | Contida |
| Operação | Um servidor, mais o Docker para manter | Dois servidores |
| Migração futura | Possível (seção 18) | Não se aplica |

**Recomendação:** seguir com **A2**, como decidido, **sujeito aos pré-requisitos da seção 4.4**, e migrar para **B** no primeiro gatilho da seção 4.5 ou quando o projeto estiver estruturado. Sem a restrição de custo, **B seria a escolha mais segura** para dados pessoais.

### 4.4 Pré-requisitos de go-live (para a opção A2)

Nenhum parceiro real e nenhum CPF real entram antes de tudo isto estar feito e testado:

| # | Pré-requisito | Referência |
|---|---|---|
| 1 | **Docker instalável e com correções de segurança neste servidor**, firewall ativo antes da instalação (seção 4.6) e imagem do PHP 8.3 funcionando. Se o Docker não for instalável ou suportado, **a opção A2 cai** | 6.2 |
| 2 | Ubuntu Pro (ESM) ativo, ou data fixada para migrar de servidor | SEC-005 |
| 3 | Swap criada e limites de memória aplicados | 5.5, 10.1 |
| 4 | **Firewall ativo e porta 3306 fechada** (hoje inexistentes, seção 4.6), SSH por chave e usuário `deploy` | SEC-001, SEC-002 |
| 5 | phpMyAdmin (se exposto) restrito | SEC-004 |
| 6 | AVA com usuário, contêiner, pastas e banco próprios, nas permissões da seção 8 | SEC-008 |
| 7 | Auditoria em schema separado e só de inserção | 9.3 |
| 8 | CPF criptografado e chaves fora do banco e do repositório | SEC-017 |
| 9 | **Backup criptografado fora do servidor e restauração testada** | 15 |
| 10 | Alertas de memória, disco, fila, backup e certificado funcionando | 17 |
| 11 | Testes de isolamento entre parceiros passando no CI | SEC-009 |
| 12 | Plano de resposta a incidentes escrito | 14.3 |
| 13 | Medição de uma semana feita e dentro dos limites | 5.6 |

### 4.5 Gatilhos de saída para servidor próprio **[ESTIMADO]**

Números propostos, a ajustar com a medição real:

| Gatilho | Limite proposto |
|---|---|
| Docker não instalável, ou sem correções de segurança para o Ubuntu 20.04 | Migrar **antes** do go-live (ou imediatamente) |
| Primeiro parceiro real com CPF de alunos em produção **[proposta]** | Reavaliar a migração para VPS própria em até 90 dias |
| Memória disponível abaixo de 1,5 GiB em horário de pico, por 7 dias seguidos | Migrar ou aumentar o plano |
| Uso de swap acima de 25% de forma recorrente | Migrar ou aumentar o plano |
| Espaço livre em disco abaixo de 15 GB | Mover arquivos para armazenamento externo e, se não bastar, migrar |
| Tempo de resposta da home (p95) acima de 800 ms por 7 dias | Investigar; migrar se for competição por recursos |
| Parceiro que exija isolamento contratual | Migrar o parceiro ou o AVA inteiro |
| Incidente de segurança em outro sistema do servidor | Migrar o AVA antes de reabrir |
| Ubuntu Pro indisponível ou inelegível | Migrar antes do go-live |

### 4.6 Firewall e porta do MySQL (urgente, antes do Docker)

Procedimento padrão, **ilustrativo**; fazer com snapshot, em janela de baixo movimento e **com uma segunda sessão SSH aberta** durante todo o processo (se a regra do SSH estiver errada, a segunda sessão permite desfazer).

1. **Ver quem usa o MySQL de fora.** Conexões em andamento: `ss -tnp state established '( sport = :3306 )'`. Contas com acesso remoto (pede a senha do MySQL): `mysql -u root -p -e "SELECT user,host FROM mysql.user WHERE host NOT IN ('localhost','127.0.0.1','::1');"`. Se alguém precisa de acesso externo (outro servidor, ferramenta de administração), definir antes uma alternativa: liberar só o IP dessa pessoa ou usar túnel SSH.
2. **Preparar as regras sem ativar:** `ufw default deny incoming`, `ufw default allow outgoing`, `ufw allow 22/tcp`, `ufw allow 80/tcp`, `ufw allow 443/tcp`. Conferir com `ufw show added` e confirmar em `/etc/default/ufw` que `IPV6=yes` (o servidor escuta também em IPv6).
3. **Ativar:** `ufw enable`, na janela, com a segunda sessão aberta. Testar em seguida: login por SSH em uma **nova** sessão, o painel, o site público e uma tarefa agendada conhecida.
4. **Conferir de fora** (de outra máquina) que a 3306 não responde, por exemplo `nc -vz <IP do servidor> 3306`.
5. **Reversão:** `ufw disable` pela segunda sessão.

**O que este firewall faz e não faz.** O `ufw` filtra **portas e endereços** (quais conexões podem chegar). Ele **não lê o conteúdo** do que é enviado, então não bloqueia um nome como "Walter" nem um apóstrofo em um formulário. Esse tipo de bloqueio vem de um filtro de **conteúdo** (um WAF, como o ModSecurity, ou um serviço na frente do site), ou de uma regra dentro do próprio código do painel. Ativar o `ufw` não deve repetir o problema dos nomes; a causa real do bloqueio ainda precisa ser identificada (pendência 29).

Não alterar o `bind-address` do MySQL: exigiria reiniciar o banco que todos os sistemas usam. O firewall resolve o risco sem tocar nele. O contêiner do AVA conecta pelo socket, não pela rede, então não depende dessa porta.

### 4.7 Comparação das duas VPS da Contabo **[MEDIDO em 09/10/2026]**

A segunda VPS hospeda o Moodle (UNY-DOC-001 não a cobre; histórico de ataques e de um incidente com webshell). Medições das duas:

| Item | VPS 1 (painel e sites) | VPS 2 (Moodle) |
|---|---|---|
| Sistema | Ubuntu 20.04.6 | Ubuntu 20.04.5 |
| PHP no sistema | 7.2.34 | 7.4.33 (sem suporte desde nov/2022) |
| PHP 8.x instalável | **Não** (repositório vazio) | **Não confirmado**; o repositório é o mesmo, então o resultado esperado é o mesmo |
| MySQL | 5.7.42 | 5.7.41 |
| Memória | 5,8 GiB (4,4 disponíveis) | 7,8 GiB (6,7 disponíveis) |
| Disco | 145 GB; 32 GB livres (78%) | 785 GB; 45 GB livres (**95%**) |
| Firewall | Nenhum (`ufw` inativo, sem regras) | `ufw` inativo; `fail2ban` e alguns endereços bloqueados por `iptables` |
| MySQL | **Aberto: `0.0.0.0:3306`** | Somente `127.0.0.1:3306` |
| Outras portas | 22, 80, 443 | 22, 80, 443 e **25 (e-mail) em todas as interfaces** |
| Ubuntu Pro | Não vinculado | Não vinculado |
| Histórico de ataques | Sem registro | **Ataques recorrentes; incidente anterior com webshell** |

**Leitura**

1. A VPS 2 **não oferece vantagem de software**: mesmo Ubuntu, mesmo MySQL, PHP 8 igualmente indisponível. Quem quisesse o PHP 8 ali também precisaria de Docker.
2. Tem **mais memória**, mas o **disco está em 95%** e o histórico de ataques é recorrente. Hospedar o AVA (que guarda CPF) ali traria o risco de um servidor já comprometido antes.
3. As duas rodam **Ubuntu 20.04 sem suporte** e sem Ubuntu Pro. O problema de fundo é o sistema, não a escolha entre as duas.
4. A porta 25 aberta na VPS 2 deve ser conferida (relé de e-mail aberto é risco). Fora do escopo do AVA, registrado aqui por ser a mesma infraestrutura.

**Decisão:** o AVA **não vai para a VPS 2**. Mantém-se a opção A2 (Docker na VPS 1) como solução temporária, e a opção B (**VPS nova com Ubuntu 24.04**) como destino. Se o orçamento permitir, contratar a VPS nova já na Fase 1 evita fazer a migração depois; é uma decisão de custo, não técnica. A VPS nova também pode receber o Moodle reinstalado de forma limpa, resolvendo os dois problemas.

---

## 5. Orçamento de recursos **[ESTIMADO]**

### 5.1 Memória

Base: 4,4 GiB disponíveis na medição (≈ 4.506 MiB).

| Consumidor | Cálculo | Reserva |
|---|---|---|
| Pool PHP-FPM do AVA (produção) | 8 processos × 100 MiB | 800 MiB |
| Pool PHP-FPM de homologação | 2 processos × 100 MiB | 200 MiB |
| Workers de fila (produção) | 2 × 100 MiB | 200 MiB |
| Worker de homologação | 1 × 100 MiB | 100 MiB |
| OPcache do PHP 8 (compartilhado) | configuração de 128 MiB | 128 MiB |
| MySQL: conexões e tabelas temporárias do AVA | estimativa | 256 MiB |
| Docker (serviços do Docker) e contêiner do agendador | estimativa | 200 MiB |
| **Total do AVA** | | **1.884 MiB (≈ 1,84 GiB)** |
| **Margem restante** | 4.506 − 1.884 MiB | **≈ 2.622 MiB (≈ 2,6 GiB)** |

Regra: manter **no mínimo 1,5 GiB de memória disponível** para o servidor inteiro.

**Efeito de um antivírus residente:** o daemon do ClamAV costuma ocupar de 1 a 1,5 GiB (a medir na instalação). A margem cairia para **1,1 a 1,6 GiB**, no limite da regra ou abaixo dela. Por isso **não instalar o ClamAV como serviço residente neste servidor** (decisão 11, seção 20).

Os 100 MiB por processo PHP são estimativa para uma aplicação Laravel; medir em homologação com carga. Cada contêiner tem **limite de memória próprio** (seção 6.3), de modo que um vazamento no AVA não consome a memória dos sistemas vizinhos.

### 5.2 CPU

4 núcleos. Os 8 processos PHP do pool de produção são adequados. Tarefas longas (PDF de certificado, importações) vão para a **fila**, nunca dentro de requisição web.

### 5.3 Disco

| Item | Valor |
|---|---|
| Livre hoje | 32 GB **[MEDIDO]** |
| Teto proposto para o AVA na fase 1 (código, releases, banco, arquivos, logs, homologação, imagens Docker) | **10 GB** |
| Reserva mínima do servidor inteiro | 15 GB livres |

Exemplos de por que o teto aperta rápido (cálculo com números das referências visuais):

| Exemplo | Conta | Resultado |
|---|---|---|
| PDF de material como o da referência (38,8 MB), 100 aulas | 38,8 MB × 100 | ≈ 3,9 GB |
| Aula em áudio de 24 min a 128 kbps | 1.454 s × 16.000 B/s | ≈ 23 MB |
| Curso de 155 aulas (o tamanho do curso da referência) todo em áudio de 24 min | 155 × 23 MB | ≈ 3,6 GB |
| Quantos cursos desse tamanho cabem no teto de 10 GB | 10 ÷ 3,6 | **menos de 3** |

Conclusão: **arquivos pesados de parceiros não podem morar no disco da VPS por muito tempo.** Medidas:

1. O código usa uma **camada de armazenamento** desde o início, de modo que passar do disco local para armazenamento externo seja **configuração**, não reescrita.
2. **Limite de armazenamento por plano** (BR-TEN-006), com aviso ao parceiro em 80%.
3. Na **fase 1**, disco local com o teto acima. Na **fase 2**, ou **antes**, se o primeiro parceiro trouxer muito material, armazenamento externo compatível com S3 (decisão 7, seção 20).
4. **Alerta por espaço livre**, não por percentual (seção 17): os 78% atuais já são dos sistemas existentes.

### 5.4 Rede

Vídeo **fora** da VPS (UNY-DOC-004, seção 10). Conferir o **limite de banda e de tráfego do plano** da Contabo **[A CONFIRMAR]**. Áudio e PDFs servidos pelo Nginx consomem banda, o que reforça a ida para armazenamento externo.

### 5.5 Swap

Criar um arquivo de swap de **2 GiB** como rede de segurança (não para uso contínuo), com `vm.swappiness` baixo:

```bash
# ilustrativo; executar em janela, com snapshot feito
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
sysctl -w vm.swappiness=10
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
```

### 5.6 Medição de uma semana antes do go-live

Coletar a cada 5 minutos, por 7 dias: memória disponível, uso de swap, carga e espaço livre; registrar os **picos** (o horário de maior movimento dos sites existentes é o que importa). Sem isso, a margem de 2,6 GiB é só uma estimativa.

---

## 6. Pilha de software alvo

### 6.1 Versões

| Componente | Alvo | Observação |
|---|---|---|
| **PHP** | **8.3** (ou 8.4), **dentro de contêiner** (imagem oficial `php:8.3-fpm` como base) | O repositório do sistema não oferece PHP 8 (seção 3). O PHP 7.2 do legado **não é tocado**. Suporte de segurança do 8.3 até 31/12/2027 e do 8.4 até 31/12/2028 (fontes secundárias; conferir em php.net). Trocar de 8.3 para 8.4 é trocar a imagem, sem mexer no servidor. **Não usar o 8.2**: o suporte de segurança acaba em 31/12/2026 |
| **Contêiner** | Docker Engine com Compose; um projeto por ambiente | Seções 6.2 e 6.3 |
| **Framework** | **Laravel 13** (decidido em 09/10/2026) | Exige PHP 8.3 ou mais novo (o contêiner atende). Correções comuns até o 3º trimestre de 2027 e de segurança até 17/03/2028; o Laravel 12 já saiu das correções comuns em 13/08/2026 (documentação oficial, página de lançamentos). **Não confirmado:** se o Laravel 13 aceita o MySQL 5.7; o teste no CI do marco M0 mostra. Se não aceitar, o AVA usa um MySQL 8 em contêiner próprio (UNY-DOC-009, seção 10) |
| **Banco** | MySQL 5.7.42 existente, **bancos novos e separados** | Restrições na seção 9 |
| **Nginx** | 1.18 existente, **no servidor** (fora do contêiner) | Fala com o PHP-FPM do contêiner por `127.0.0.1` |
| **Composer e Node** | **Não instalar em produção** | O build acontece no CI; o servidor só recebe o pacote pronto (menos memória e menos superfície de ataque) |
| **Fila** | No banco (UNY-DOC-004, 8.4) | Funciona no 5.7 (seção 9.4) |
| **Cache e sessão** | Arquivos, com prefixo por parceiro | Sem Redis na v1 |
| **Processos** | Contêineres com política de reinício (`unless-stopped`) | Workers e agendador viram serviços do Compose (seção 10) |

Extensões PHP previstas: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` (ou `imagick`), `intl`, `mbstring`, `mysql`, `opcache`, `xml`, `zip`.

### 6.2 Docker no servidor atual

Em vez de instalar o PHP 8 no sistema, o AVA roda numa **imagem Docker** com o próprio PHP. O `php` do servidor continua sendo o 7.2, e nenhuma rotina do legado muda.

Riscos próprios do Docker neste servidor e como tratá-los:

| # | Risco | Tratamento |
|---|---|---|
| 1 | O Docker cria regras de `iptables` e ativa `ip_forward` (hoje `0`). Hoje o servidor **não tem firewall nem regras** [MEDIDO] | Ativar o firewall **antes** (seção 4.6); depois da instalação guardar `iptables-save` e `ufw status verbose` e conferir que as regras do `ufw` continuam; teste de fumaça do legado |
| 2 | Portas publicadas pelo Docker **ignoram o `ufw`** | Publicar **somente** em `127.0.0.1` (`127.0.0.1:9083:9083`). Conferir com `ss -tlnp` e `docker ps` que nada escuta em `0.0.0.0` |
| 3 | Estar no grupo `docker` equivale a ser `root` | **Ninguém** entra no grupo, nem `deploy`. O deploy usa dois scripts de `root` com comandos fixos (seção 16.3) |
| 4 | Reiniciar o Docker (atualização) derrubaria o AVA | `live-restore: true` no `daemon.json`, para os contêineres seguirem rodando |
| 5 | Imagem desatualizada acumula falhas de segurança | Reconstruir a imagem **toda semana** no CI e a cada aviso de segurança, com varredura de vulnerabilidades; trocar a tag em produção por deploy |
| 6 | Disco: imagens e logs ficam em `/var/lib/docker`, na raiz | Contam no teto de 10 GB; rotação de log por contêiner; alerta de `docker system df` |
| 7 | Origem do pacote Docker no Ubuntu 20.04 | **Resolvido:** usar `docker.io` 26.1.3 e `docker-compose-v2` 2.27.1, do `focal-updates/universe` **[MEDIDO]**. **Não usar o `snap` do Docker** (o isolamento do snap atrapalha montar `/var/www` e `/var/run/mysqld`). Pacotes `universe` só têm correções contínuas com o Ubuntu Pro (ESM apps): **[A CONFIRMAR]** quando o servidor for vinculado, já que hoje o `pro status` lista só o `esm-infra` |
| 8 | Saída de rede do contêiner (chamadas à API do painel e ao e-mail) | Funciona por NAT; incluir no teste de fumaça |

### 6.3 Contêineres do AVA

**Imagem** (construída no CI, publicada em registro privado, **[A CONFIRMAR]** qual): base `php:8.3-fpm` com a versão fixada por resumo (digest); extensões `bcmath`, `gd`, `intl`, `pdo_mysql`, `zip`, `opcache`; sem Composer, sem Node e sem ferramentas de compilação. **O código não vai dentro da imagem**: ele continua em releases no disco (seção 16.3) e é montado no contêiner, o que preserva a troca atômica de versão.

**Montagens** (os caminhos são **iguais** dentro e fora do contêiner, para o link `current` e o `$realpath_root` do Nginx valerem nos dois lados):

| Montagem | Modo | Para quê |
|---|---|---|
| `/var/www/ava` | somente leitura | Código, releases e `.env` |
| `/var/www/ava/shared/storage` | leitura e escrita | Sessões, cache, logs |
| `/var/ava-storage` | leitura e escrita | Arquivos dos parceiros |
| `/var/run/mysqld/mysqld.sock` | leitura e escrita | Conexão com o MySQL pelo **socket Unix** (caminho medido: `/var/run/mysqld/mysqld.sock`) |
| `/etc/ava/php/ava.conf` e `ava.ini` | somente leitura | Pool do PHP-FPM e opções do PHP (seção 8.4) |

Conectar pelo socket evita reabrir o MySQL para a rede do Docker: o `bind-address` continua em `127.0.0.1`, as contas `@'localhost'` seguem valendo e **nenhuma configuração global do MySQL muda**.

```yaml
# /etc/ava/compose-prod.yaml   (ilustrativo; dono root, modo 640)
name: ava
x-php: &php
  image: ${AVA_IMAGE}                      # registro privado; tag fixa por deploy
  user: "${AVA_UID}:${AVA_GID}"            # usuário "ava" do servidor
  restart: unless-stopped
  read_only: true
  tmpfs: [/tmp]
  cap_drop: [ALL]
  security_opt: ["no-new-privileges:true"]
  logging: { driver: json-file, options: { max-size: "10m", max-file: "3" } }
  environment: { TZ: UTC }
  volumes:
    - /var/www/ava:/var/www/ava:ro
    - /var/www/ava/shared/storage:/var/www/ava/shared/storage
    - /var/ava-storage:/var/ava-storage
    - /var/run/mysqld/mysqld.sock:/var/run/mysqld/mysqld.sock
    - /etc/ava/php/ava.conf:/usr/local/etc/php-fpm.d/www.conf:ro
    - /etc/ava/php/ava.ini:/usr/local/etc/php/conf.d/zz-ava.ini:ro
services:
  fpm:
    <<: *php
    ports: ["127.0.0.1:9083:9083"]         # NUNCA 0.0.0.0: o Docker ignora o ufw
    mem_limit: 1g
  worker:
    <<: *php
    command: ["php", "/var/www/ava/current/artisan", "queue:work",
              "--queue=webhooks,painel,default,emails", "--sleep=3", "--tries=3",
              "--max-time=3600", "--memory=128"]
    mem_limit: 300m
    deploy: { replicas: 2 }                # se a versão do Compose ignorar, usar --scale worker=2
  scheduler:
    <<: *php
    command: ["php", "/var/www/ava/current/artisan", "schedule:work"]
    mem_limit: 150m
```

Notas:

- **Homologação** é outro projeto do Compose (`name: ava-homolog`), com usuário `avah`, porta `127.0.0.1:9084`, 2 processos no pool, 1 worker, e pastas e banco próprios.
- O contêiner **não** monta o Docker, o `/etc` do servidor nem pastas dos outros sistemas. Ele só enxerga as montagens da tabela.
- Com `read_only: true`, o PHP só escreve em `shared/storage`, `/var/ava-storage` e `/tmp`. **Validar em homologação** que a aplicação não tenta escrever em outro lugar.
- O `mem_limit` usa os grupos de controle da versão 1 (`cgroups v1`) do Ubuntu 20.04, que aplicam limite de memória.
- Atualizar a imagem: o CI publica a tag nova, `ava-ctl update <ambiente> <tag>` baixa a imagem e recria os contêineres. Voltar é repetir com a tag anterior.

---

## 7. Nginx

### 7.1 Princípios

- O Nginx é **compartilhado**. Um erro de configuração seguido de `reload` pode derrubar **todos** os sites.
- Toda mudança segue: gerar o arquivo → `nginx -t` → só então `systemctl reload nginx`. Se o teste falhar, **nada é recarregado**.
- Os arquivos do AVA ficam **separados**, com nomes próprios. **Nenhum vhost existente é editado.** O `default_server` atual não é alterado.
- O AVA **nunca** responde por hosts que não estejam cadastrados (seção 7.5).

### 7.2 Arquivos

| Arquivo | Função |
|---|---|
| `/etc/nginx/conf.d/ava-limites.conf` | Zonas de limite de taxa (contexto `http`) e formato de log |
| `/etc/nginx/snippets/ava-app.conf` | Configuração comum da aplicação (raiz, cabeçalhos, locais) |
| `/etc/nginx/snippets/ava-tls.conf` | Protocolos e cifras modernos |
| `/etc/nginx/sites-available/ava-<host>.conf` | **Um arquivo por host**, gerado por script e ligado em `sites-enabled` |

### 7.3 Limites e log

```nginx
# /etc/nginx/conf.d/ava-limites.conf   (ilustrativo)
limit_req_zone  $binary_remote_addr zone=ava_login:10m   rate=10r/m;
limit_req_zone  $binary_remote_addr zone=ava_api:10m     rate=20r/s;
limit_req_zone  $binary_remote_addr zone=ava_webhook:10m rate=30r/s;

# Log SEM query string: tokens de link mágico viajam na query e não devem ir para o log
log_format ava '$remote_addr [$time_iso8601] $host "$request_method $uri" '
               '$status $body_bytes_sent $request_time "$http_user_agent"';
```

### 7.4 Um arquivo por host

Cada parceiro (subdomínio padrão ou domínio próprio) tem um arquivo gerado por script. A emissão do certificado exige primeiro o bloco da porta 80, por isso o script trabalha em duas etapas (seção 11.2).

```nginx
# /etc/nginx/sites-available/ava-exemplo.faculdadeunypublica.edu.br.conf   (gerado, ilustrativo)
server {
    listen 80;
    server_name ava-exemplo.faculdadeunypublica.edu.br;
    location ^~ /.well-known/acme-challenge/ { root /var/www/ava-acme; }
    location / { return 301 https://$host$request_uri; }
}

server {
    listen 443 ssl http2;
    server_name ava-exemplo.faculdadeunypublica.edu.br;

    ssl_certificate     /etc/letsencrypt/live/ava-exemplo.faculdadeunypublica.edu.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ava-exemplo.faculdadeunypublica.edu.br/privkey.pem;
    include snippets/ava-tls.conf;

    access_log /var/log/nginx/ava_access.log ava;
    error_log  /var/log/nginx/ava_error.log warn;

    # ModSecurity: o servidor tem filtro global ativo (seção 7.7). Definir ANTES do primeiro host real.
    include snippets/ava-app.conf;
}
```

```nginx
# /etc/nginx/snippets/ava-app.conf   (ilustrativo)
root /var/www/ava/current/public;
index index.php;
charset utf-8;
server_tokens off;
client_max_body_size 2m;          # envios de arquivo (fase 2) usarão um location próprio com limite maior

add_header X-Content-Type-Options nosniff always;
add_header Referrer-Policy strict-origin-when-cross-origin always;
add_header X-Frame-Options SAMEORIGIN always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Strict-Transport-Security "max-age=300" always;   # sobe aos poucos, sem includeSubDomains
# A política CSP é montada pela aplicação, com nonce

location ~ /\.(?!well-known) { deny all; }

location ^~ /webhooks/ {
    client_max_body_size 1m;
    limit_req zone=ava_webhook burst=100 nodelay;
    try_files $uri /index.php?$query_string;
}

# Limite de login. É um location de EXPRESSÃO REGULAR e o /api/ abaixo é de prefixo simples
# (sem ^~): se o /api/ usasse ^~, o Nginx não avaliaria esta regra e o limite nunca valeria.
location ~ ^/api/v1/[a-z]+/acesso/ {
    limit_req zone=ava_login burst=10 nodelay;
    try_files $uri /index.php?$query_string;
}

location /api/ {
    limit_req zone=ava_api burst=40 nodelay;
    try_files $uri /index.php?$query_string;
}

location ^~ /midia/ {                        # ativos públicos do parceiro (logo, capas, banners)
    alias /var/ava-storage/publico/;
    expires 7d;
    add_header Cache-Control "public";
}

location ^~ /protegido/ {                    # conteúdo de curso, liberado pela aplicação (X-Accel-Redirect)
    internal;
    alias /var/ava-storage/conteudo/;
}

location / { try_files $uri /index.php?$query_string; }

location = /index.php {
    include fastcgi_params;
    fastcgi_pass 127.0.0.1:9083;               # PHP-FPM no contêiner (homologação: 9084)
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;   # necessário com releases por link simbólico
    fastcgi_param DOCUMENT_ROOT   $realpath_root;
    fastcgi_read_timeout 60s;
}

location ~ \.php$ { return 404; }            # só o index.php executa
```

Notas:

- O arquivo de host de **homologação** acrescenta `auth_basic` (senha) e `X-Robots-Tag: noindex`, com `auth_basic off;` apenas em `/webhooks/` (para as plataformas de venda conseguirem enviar eventos de teste).
- Os limites de taxa agem **uma vez**, no primeiro `location` que atende a requisição, antes do redirecionamento interno para `index.php`. O teste de carga em homologação confirma isso.

### 7.5 Efeito do registro curinga no DNS

Com `*.faculdadeunypublica.edu.br` apontando para a VPS (UNY-DOC-004, 5.3), **qualquer nome inexistente passa a chegar a este servidor** e cai no `default_server` que já existe, com o comportamento atual dele (que **ainda não foi verificado**: Anexo A). Como o AVA só responde pelos arquivos de host gerados, nomes desconhecidos **não** chegam ao AVA. Se o comportamento do servidor padrão for indesejado para nomes desconhecidos, a correção é avaliada à parte, **sem** mexer nos sites existentes. Alternativa **sem curinga**: criar um registro A por parceiro pela API do provedor de DNS, se disponível (seção 11.1).

### 7.6 HSTS gradual

O HSTS trava o navegador em HTTPS. Como todos os parceiros e os sistemas atuais compartilham o domínio-pai, a política é: **sem `includeSubDomains`** e **sem `preload`**; começar com 5 minutos, subir para 1 semana e depois para 1 ano, somente quando o HTTPS estiver estável em todos os hosts do AVA.

### 7.7 ModSecurity já presente no servidor **[MEDIDO em parte]**

O histórico de comandos do servidor e a configuração mostram:

| Achado | Fonte |
|---|---|
| Módulo `ngx_http_modsecurity_module` **compilado a partir do código-fonte** e carregado em `/etc/nginx/modules-enabled/50-mod-http-modsecurity.conf` | Histórico de comandos; `grep` em `/etc/nginx` |
| `modsecurity on;` e `modsecurity_rules_file /etc/nginx/nginx-modsecurity.conf;` em `/etc/nginx/nginx.conf` (linhas 23 e 24) | `grep` em `/etc/nginx` |
| `modsecurity off;` em `server_name` do painel (`painel.unyboss.com.br`, `painel.unypos.com.br`) e em `/etc/nginx/sites-available/painel.faculdadeunypublica.edu.br` | `grep` e histórico de comandos |
| Nenhum pacote de WAF no `dpkg`; o módulo não vem de pacote do Ubuntu | `dpkg -l` |

**Consequências para o AVA**

1. Um host novo do AVA **herda** `modsecurity on` e as regras globais, **a menos que** declare o contrário. A regra que barrou "Walter" e nomes com apóstrofo é de um conjunto de regras contra injeção de SQL e **valeria para o cadastro de alunos do AVA**.
2. Os **webhooks** das plataformas de venda (JSON com dados do comprador) também podem ser barrados por essas regras. A proteção deles é a **assinatura do pedido** (UNY-DOC-006), não o filtro de conteúdo.
3. O módulo foi compilado à mão para o Nginx 1.18 do Ubuntu 20.04: **não recebe atualização automática**. Isso é um item de manutenção do servidor.

**Decisão proposta (a confirmar em homologação)**

| Host | Configuração |
|---|---|
| Homologação | `modsecurity on` em modo **somente detecção** (`SecRuleEngine DetectionOnly`), para coletar o registro do que seria bloqueado, sem bloquear |
| `/webhooks/` (todos os hosts) | `modsecurity off`; a defesa é a assinatura e o limite de taxa |
| Produção | Só passa a bloquear depois de ajustar as regras com o registro da homologação, usando **exceções por campo** (por exemplo, nomes e e-mail), nunca desligando a família de regras inteira |

O modo exato depende de pontos ainda não vistos (pendência 29): em que bloco do `nginx.conf` está a diretiva, e o que há no arquivo de regras. Ainda **não foi verificado** se um arquivo de regras próprio do AVA se **soma** ao global ou o **substitui**; isso se testa em homologação, nunca em produção. Enquanto não houver decisão, **nenhum host real do AVA entra no ar**.

---

## 8. PHP-FPM, usuários e permissões

### 8.1 Usuários

| Usuário | Papel |
|---|---|
| `www-data` | Já existe: usuário do Nginx (e provavelmente do pool legado; **verificar**, Anexo A) |
| `ava` | Usuário (UID e GID) com que os **contêineres** de produção rodam o PHP-FPM, os workers e o agendador. **Não pode alterar o código** |
| `avah` | O mesmo papel, para **homologação** (pastas `/var/www/ava-homolog` e `/var/ava-storage-homolog`) |
| `deploy` | Dono do código e das releases; faz o deploy. Não roda o site |
| `root` | Só para administração do sistema |

Separação de funções: **o processo que atende a internet (`ava`) não consegue modificar o próprio código.** Uma falha de execução remota na aplicação não permite plantar um arquivo PHP no diretório do site. O código é montado **somente leitura** no contêiner, e ninguém participa do grupo `docker` (seção 6.2, risco 3).

### 8.2 Estrutura e permissões (produção)

| Caminho | Dono | Modo | Observação |
|---|---|---|---|
| `/var/www/ava/` | `deploy:ava` | 711 | Fora de `/var/www/html` |
| `/var/www/ava/releases/` | `deploy:ava` | 711 | O `o+x` permite ao Nginx **atravessar** até `public/` |
| `…/releases/<data>/` | `deploy:ava` | 711 | Idem |
| `…/<release>/public/` | `deploy:ava` | 755 (arquivos 644) | **Único** ponto legível pelo Nginx |
| `…/<release>/` (demais pastas: `app`, `vendor`, `config`, `bootstrap`…) | `deploy:ava` | 750 | Legível por `ava`, **invisível** para `www-data` |
| `…/<release>/bootstrap/cache/` | `deploy:ava` | 750 | Caches de configuração, rotas e views, **gerados no deploy**; `ava` só lê |
| `/var/www/ava/current` | link simbólico | | Aponta para a release ativa |
| `/var/www/ava/shared/.env` | `deploy:ava` | 640 | Lido pelo app, **não editável** por ele |
| `/var/www/ava/shared/.migrate-pass` | `deploy:deploy` | 600 | Senha do usuário de migração: **`ava` nunca a lê** |
| `/var/www/ava/shared/storage/` | `ava:ava` | 750 | Sessões, cache, logs |
| `/var/ava-storage/` | `ava:ava` | 750 | Arquivos dos parceiros (seção 8.3) |
| `/var/ava-storage/tmp/` | `ava:ava` | 700 | Temporários de upload |
| `/var/www/ava-acme/` | `deploy:deploy` | 755 | Só o desafio do Let's Encrypt |

Ponto de atenção: como `deploy` **não escreve** em `shared/storage`, os comandos de cache do deploy gravam dentro da release (seção 16.3). **Validar em homologação** que a aplicação não precisa compilar nenhuma view em tempo de execução; se precisar, ajustar o caminho de views compiladas.

### 8.3 Classes de arquivo

O Nginx roda como `www-data`, que provavelmente é **o mesmo usuário do pool PHP 7.2 legado**. Tudo que o Nginx lê, o legado também consegue ler. Por isso os arquivos têm **classes**:

| Classe | Exemplos | Quem serve | Permissão |
|---|---|---|---|
| **Pública** | Logo, favicon, capas, banners | Nginx direto, em `/midia/<uuid-do-parceiro>/…` | Legível por `www-data`. Conteúdo não sensível; o caminho tem identificador não adivinhável |
| **Conteúdo de curso** | Áudio, PDFs, materiais | Nginx via `X-Accel-Redirect`, **depois** de a aplicação verificar a matrícula | `ava:www-data`, modo 640. Aceita-se que o legado possa ler (é conteúdo comercial, **sem dados pessoais**) |
| **Pessoal** | Entregas de tarefas, exportações da LGPD, backups | **Somente a aplicação** (`ava`) | 600/700 e **nunca legível pelo Nginx** |

Se o armazenamento passar para fora do servidor (seção 5.3), as classes "pública" e "conteúdo de curso" deixam de depender de permissão local.

### 8.4 Pool PHP-FPM do AVA

```ini
; /etc/ava/php/ava.conf   (montado como pool do contêiner; ilustrativo)
[www]                                   ; o nome do pool na imagem oficial; este arquivo substitui o padrão
; sem user/group: o contêiner já roda como "ava" (UID/GID do Compose)
listen = 0.0.0.0:9083                   ; dentro do contêiner; fora, a porta só existe em 127.0.0.1

pm = dynamic
pm.max_children      = 8
pm.start_servers     = 2
pm.min_spare_servers = 2
pm.max_spare_servers = 4
pm.max_requests      = 500
pm.status_path       = /fpm-status        ; não exposto pelo Nginx

php_admin_value[memory_limit]        = 256M
php_admin_value[upload_tmp_dir]      = /var/ava-storage/tmp
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size]       = 100M
php_admin_value[open_basedir]        = /var/www/ava/:/var/ava-storage/
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.cookie_samesite] = Lax
php_admin_flag[expose_php]           = off
php_admin_flag[display_errors]       = off
php_admin_flag[log_errors]           = on
php_admin_value[error_log]           = /var/www/ava/shared/storage/logs/php-fpm.log
php_admin_value[disable_functions]   = exec,passthru,shell_exec,system,proc_open,popen
```

Notas:

- O pool de **homologação** é um segundo arquivo (`ava-homolog.conf`), montado no projeto de homologação, com 2 processos, usuário `avah` e pastas próprias.
- O `open_basedir` abaixo não impede a conexão com o MySQL pelo socket (ela não é um acesso a arquivo do PHP).
- `disable_functions` e `open_basedir` são **propostas**: testar em homologação, porque bibliotecas de PDF e de imagem às vezes exigem exceções. A geração do **PDF do certificado roda na fila (linha de comando)**, que não é afetada por este arquivo.
- O cookie de sessão **não define domínio** (fica preso ao host exato), conforme UNY-DOC-004, 5.7.

### 8.5 OPcache

`opcache.enable=1`, `opcache.memory_consumption=128` e `opcache.validate_timestamps=0` em produção (o deploy recarrega o FPM, seção 16.3).

### 8.6 O que o isolamento por usuário **não** resolve

Usuários e permissões distintos impedem que um sistema legado comprometido, **sem privilégios de administrador**, leia o `.env` ou o código do AVA. **Não protegem** contra falhas do sistema operacional que permitam virar `root`. Por isso o suporte de segurança do sistema é pré-requisito, e não um luxo.

---

## 9. Banco de dados (MySQL 5.7)

### 9.1 Princípio

O AVA usa **bancos novos**, na instância MySQL que já existe, com **usuários próprios e limitados**. Nenhuma configuração **global** do MySQL é alterada na versão 1 (a instância é compartilhada com os sistemas atuais). Tudo que o AVA precisa é configurado **na conexão** ou **no próprio banco**.

### 9.2 Bancos e usuários

```sql
-- ilustrativo
CREATE DATABASE ava_prod  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ava_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'ava_app'@'localhost' IDENTIFIED BY '<senha longa e única>' WITH MAX_USER_CONNECTIONS 40;
GRANT SELECT, INSERT, UPDATE, DELETE ON ava_prod.*  TO 'ava_app'@'localhost';
GRANT SELECT, INSERT                 ON ava_audit.* TO 'ava_app'@'localhost';   -- só inclusão

CREATE USER 'ava_migrate'@'localhost' IDENTIFIED BY '<outra senha>';
GRANT ALL PRIVILEGES ON ava_prod.*  TO 'ava_migrate'@'localhost';
GRANT ALL PRIVILEGES ON ava_audit.* TO 'ava_migrate'@'localhost';

CREATE USER 'ava_backup'@'localhost' IDENTIFIED BY '<outra senha>';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON ava_prod.*  TO 'ava_backup'@'localhost';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON ava_audit.* TO 'ava_backup'@'localhost';
```

Homologação repete o desenho com `ava_homolog` e `ava_homolog_audit`, usuários e senhas **diferentes**.

| Usuário | Uso | Pode alterar estrutura? |
|---|---|---|
| `ava_app` | Aplicação em funcionamento | **Não** |
| `ava_migrate` | **Somente** durante o deploy (migrações) | Sim |
| `ava_backup` | Somente leitura, para o backup | Não |

A aplicação, dentro do contêiner, conecta pelo **socket Unix** do MySQL (seção 6.3). Para o MySQL, a conexão vem de `localhost`, então as contas acima servem sem mudança. Nada é aberto para a rede do Docker.

### 9.3 Auditoria em schema separado

O UNY-DOC-005 (seção 14.1) exige que a aplicação **não consiga alterar nem apagar** a auditoria. No MySQL 5.7, uma permissão concedida no nível do banco (`ava_prod.*`) **não pode ser retirada de uma só tabela** (a revogação parcial só existe no 8.0). A alternativa seria conceder permissões **tabela a tabela**, o que é frágil: cada tabela nova criada por uma migração exigiria uma concessão manual, e um esquecimento deixaria a tabela inacessível ou, pior, mal protegida.

A solução adotada é guardar `auditoria` e `acessos_suporte` em **outro schema** (`ava_audit`), onde a aplicação só tem `SELECT` e `INSERT`.

Consequência: **sem chave estrangeira entre os dois schemas**. A referência a parceiro e a ator fica como valor, validado pela aplicação.

### 9.4 Compatibilidade com o MySQL 5.7

| Recurso | Situação no 5.7 | O que fazer |
|---|---|---|
| Tipo `JSON` | Existe (desde o 5.7.8) | Usar. Sem valor `DEFAULT` na coluna; para indexar, coluna gerada |
| Expressões de tabela (`WITH`) e funções de janela | **Não existem** (são do 8.0) | Relatórios com subconsultas ou cálculo na aplicação |
| `CHECK` | A sintaxe é aceita, mas **não é aplicada** | Não confiar; validar na aplicação (já é a regra do UNY-DOC-005) |
| `SKIP LOCKED` (fila) | **Não existe** (8.0.1 em diante) | A fila no banco funciona, mas com **mais disputa de bloqueio**: manter poucos workers (2 na v1) e acompanhar |
| Revogação parcial de permissões | Não existe (8.0.16 em diante) | Schema separado (seção 9.3) |
| Chaves estrangeiras compostas | Existem | Usar (UNY-DOC-005, 15.1) |
| Autenticação | `mysql_native_password`, compatível com PHP 7.2 e 8.3 | Sem ajuste |
| Charset padrão do servidor | **Costuma ser `latin1`** se ninguém mudou | Explícito em tudo (seção 9.5) |

### 9.5 Charset, collation, modo estrito e fuso

O servidor provavelmente usa `latin1` por padrão **[A CONFIRMAR: Anexo A]**, e o legado já sofreu com collations diferentes entre tabelas. Portanto, **nada depende do padrão do servidor**:

- Banco criado com `utf8mb4` e `utf8mb4_unicode_ci` (seção 9.2)
- **Toda tabela e coluna de texto** herda isso, sem exceção, verificado por um teste automático do CI
- A **conexão** da aplicação declara `utf8mb4`, `utf8mb4_unicode_ci`, **modo estrito** e fuso **UTC**, definidos **na conexão** (não no servidor), para não alterar o comportamento dos sistemas legados
- Conferir `innodb_large_prefix` e o formato de linha padrão: a chave única (`tenant_id`, `email`) em `utf8mb4` precisa de índice de até 1.020 bytes, o que o 5.7 padrão aceita

### 9.6 Conexões

`ava_app` limitado a **40 conexões simultâneas**. Uso previsto: 8 do pool + 2 workers + agendador + margem. Conferir o `max_connections` global **[A CONFIRMAR]** para que o AVA e os sistemas legados não se sufoquem.

### 9.7 Configuração do servidor MySQL

| Tema | Decisão |
|---|---|
| `innodb_buffer_pool_size` | Conferir; **não alterar** na v1 sem janela e sem medir |
| Log binário (recuperação até um instante) | **Não ativar na v1.** Exige reinício do MySQL e afeta todos os sistemas. Sem ele, a perda máxima é de 12 horas (seção 15.1) |
| Log de consultas lentas | Avaliar; é global |
| Acesso remoto | **Hoje o MySQL escuta em `0.0.0.0:3306` [MEDIDO].** Meta: inacessível de fora. Resolver pelo **firewall** (seção 4.6), sem alterar o `bind-address` |

### 9.8 Caminho para o MySQL 8

Não atualizar a instância atual no mesmo lugar: o legado em PHP 7.2 depende de autenticação e de comportamentos do 5.7. O AVA migra para o MySQL 8 **junto com a mudança de servidor** (seção 18). Até lá, o código evita qualquer recurso exclusivo do 8.0, e o **CI roda os testes contra MySQL 5.7** (seção 16.2).

---

## 10. Fila, agendador e processos

### 10.1 Workers

Os workers são o serviço `worker` do Compose (seção 6.3), com política de reinício `unless-stopped`.

| Item | Valor |
|---|---|
| Instâncias na v1 | 2 (`replicas: 2`) |
| Filas, por prioridade | `webhooks` (pagamentos) › `painel` (ponte) › `default` › `emails` |
| Limite de memória | 300 MiB por contêiner (o worker se reinicia sozinho ao passar de 128 MiB) |
| Reinício a cada deploy | `ava-ctl workers <ambiente>` (seção 16.3). O `deploy` não escreve no cache da aplicação, então não usa `queue:restart` |
| Reinício automático por tempo e memória | 1 hora ou 128 MiB |
| Logs | `docker logs`, com rotação de 3 arquivos de 10 MB; a aplicação grava o essencial em `shared/storage/logs` |

### 10.2 Agendador

Serviço `scheduler` do Compose, rodando `php artisan schedule:work` (substitui a linha de `cron`, que não existe mais para o AVA).

| Frequência | Tarefa |
|---|---|
| A cada minuto | Reenvio de mensagens ao painel (`painel_mensagens` com `proxima_tentativa_em`) |
| A cada 15 minutos | Consulta da situação dos parceiros com certificados retidos (UNY-DOC-004, 9.7, opção B); nova tentativa dos certificados `aguardando_painel` |
| A cada hora | Expirar matrículas; verificar domínios `aguardando_dns`; verificar certificados a vencer |
| Diária | Limpar tokens expirados; **reconciliação AVA ↔ painel**; expurgo de conteúdo bruto de webhooks acima do prazo; rotação de logs da aplicação |
| Mensal | Lembrete de teste de restauração e de revisão de acessos |

### 10.3 PDF do certificado

Roda **na fila**, com um worker, nunca na requisição web. Imagens grandes de fundo consomem memória: limitar o tamanho do fundo no envio (UNY-DOC-007, 9) e dar a esse tipo de tarefa um limite de memória maior que o dos demais.

### 10.4 Falhas

Tarefas que esgotam as tentativas vão para a tabela de falhas e geram **alerta** (seção 17). Eventos de pagamento com erro podem ser reprocessados pelo parceiro ou pelo operador (UNY-DOC-006, 3.7).

---

## 11. DNS, domínios e certificados

### 11.1 Registro curinga

Uma única mudança manual na zona do domínio: `*.faculdadeunypublica.edu.br  A  <IP da VPS>`. Efeito colateral e alternativa na seção 7.5. O acesso à **API de DNS do provedor** (GoDaddy) pode ter restrições **[A CONFIRMAR]**; por isso o desenho **não depende dela**.

### 11.2 Provisionar um host (script, por parceiro)

```text
1. Validar o slug (regras do UNY-DOC-004, 5.2) e a lista de reservados
2. Gerar o arquivo do host só com o bloco da porta 80 (desafio ACME)
3. nginx -t  →  reload
4. Emitir o certificado:  certbot certonly --webroot -w /var/www/ava-acme -d <host>
5. Acrescentar o bloco 443 ao arquivo do host
6. nginx -t  →  reload
7. Testar:  curl -I https://<host>/saude
8. Marcar o domínio como ativo no banco; se qualquer passo falhar, desfazer e registrar o erro (visível ao parceiro)
```

### 11.3 Certificados

- Um certificado por host, com validação pela porta 80 (não exige API de DNS)
- O Let's Encrypt limita a quantidade de certificados por **domínio registrado** por semana: **conferir o valor vigente** antes de escalar o número de parceiros
- Renovação: o timer do Certbot (snap) já existe; criar um **gancho de renovação** (`--deploy-hook`) que roda `nginx -t` e recarrega o Nginx
- Alerta quando faltarem 14 dias ou menos (seção 17)
- Migrar para certificado **curinga** só se a API de DNS se mostrar utilizável (UNY-DOC-004, 5.4)

### 11.4 Domínio próprio do parceiro

- Na v1, **somente subdomínios** do parceiro (ex.: `cursos.parceiro.com.br`), apontados por **CNAME** para o endereço `ava-<slug>…`. Um domínio na **raiz** (sem subdomínio) não aceita CNAME e dependeria de apontar para o IP, que é frágil: **fora da v1**
- Estados e verificação conforme UNY-DOC-004, 5.6
- Se o parceiro tiver registro **CAA** restritivo, ele precisa autorizar o Let's Encrypt

### 11.5 Slugs reservados adicionais

Além dos já definidos no UNY-DOC-004, 5.2: `homolog`, `staging`, `mail`, `smtp`, `status`, `docs`, `app`, `cdn`, `static`, `suporte`, `sistema`.

---

## 12. E-mail transacional

O servidor **não envia e-mail diretamente**. Usa um **provedor externo** de envio por SMTP ou API (decisão 9, seção 20), por entregabilidade e para proteger a reputação do IP.

| Tema | Decisão |
|---|---|
| Domínio remetente | **Dedicado ao AVA** (ex.: um subdomínio de envio), separado do e-mail institucional da Faculdade, para que a reputação de um não contamine o outro |
| Remetente padrão | Nome do parceiro, endereço do domínio do AVA, **Reply-To** apontando para o suporte do parceiro. **Nunca** usar o domínio do parceiro como remetente sem verificação |
| Autenticação | SPF, DKIM e DMARC configurados no domínio de envio |
| Remetente próprio do parceiro | **Fase 2.** Exige que o parceiro publique registros DKIM e SPF no domínio dele |
| Devoluções e reclamações | O provedor informa; a aplicação marca o e-mail como inválido e avisa a equipe do parceiro |
| Fila | Todo envio passa pela fila `emails`, com reenvio em caso de falha |
| Homologação | **Nunca** envia para endereços reais (apenas registro em arquivo ou caixa de teste) |

---

## 13. Segurança: lista de controles

Prioridade **P0** = obrigatório antes do go-live; **P1** = até 90 dias depois.

| ID | Controle | Pri. |
|---|---|---|
| **Acesso e rede** | | |
| SEC-001 | SSH apenas por chave; senha e login de `root` por senha desativados; rotina com usuário `deploy`, sem `root` | P0 |
| SEC-002 | Firewall com somente 22, 80 e 443 abertas; MySQL inacessível de fora. **Hoje não atendido [MEDIDO]:** firewall inativo e MySQL em `0.0.0.0:3306` (seção 4.6) | P0 |
| SEC-003 | `fail2ban` para SSH e excesso de tentativas no Nginx | P1 |
| SEC-004 | phpMyAdmin, **se estiver exposto à internet**: restringir por IP, VPN ou túnel SSH; **não usar** para o banco do AVA | P0 |
| **Atualizações** | | |
| SEC-005 | Ubuntu Pro (ESM) ativo, **ou** data fixa para migrar de servidor. A assinatura gratuita vale para uso pessoal e comercial de pequena escala, até 5 máquinas: **verificar a elegibilidade** | P0 |
| SEC-006 | Atualizações automáticas de segurança ativas | P0 |
| SEC-007 | `composer audit` no CI e revisão mensal de dependências | P0 |
| **Isolamento** | | |
| SEC-008 | Usuário, pool, pastas e banco próprios; permissões da seção 8 | P0 |
| SEC-009 | Testes de isolamento entre parceiros no CI (UNY-DOC-005, 19) | P0 |
| SEC-010 | Cookies presos ao host; proteção CSRF e verificação de `Origin`. Os ambientes dos parceiros são **do mesmo site** (mesmo domínio-pai), então `SameSite` **não** os separa | P0 |
| **Aplicação e deploy** | | |
| SEC-011 | Somente `public/` acessível pela web; `vendor`, `.env`, `.git` e código fora do alcance | P0 |
| SEC-012 | **Sem dependências de desenvolvimento em produção** (ex.: PHPUnit, uma porta de entrada conhecida para execução remota), `APP_DEBUG=false` | P0 |
| SEC-013 | Nenhum arquivo de diagnóstico, teste ou cópia de segurança na raiz web | P0 |
| SEC-014 | Somente `index.php` executa PHP; os outros `.php` devolvem 404 | P0 |
| SEC-015 | HTML enviado por parceiros é sanitizado; sem CSS ou JavaScript livre (ADR-008) | P0 |
| SEC-016 | CSP, cabeçalhos de segurança e HSTS gradual | P1 |
| **Dados e segredos** | | |
| SEC-017 | CPF criptografado; **chaves fora do banco e do repositório**; rotação possível (`cpf_chave_versao`) | P0 |
| SEC-018 | `.env` modo 640 fora do repositório; **segredos distintos por ambiente**; a senha de migração **nunca** legível pelo usuário da aplicação | P0 |
| SEC-019 | Logs sem CPF e sem tokens; CPF **nunca** em endereço ou query string; log do Nginx por `$uri` | P0 |
| SEC-020 | Auditoria em schema separado, só inserção (seção 9.3) | P0 |
| SEC-021 | Backups criptografados e **fora do servidor** | P0 |
| **Contas** | | |
| SEC-022 | Autenticação em dois fatores: **operadores P0**, administradores de parceiro P1 | P0/P1 |
| SEC-023 | Limite de tentativas de login e de link mágico, com bloqueio progressivo | P0 |
| SEC-024 | Ver o CPF completo exige permissão e é auditado (FS-ADM-022) | P0 |
| **Webhooks e ponte** | | |
| SEC-025 | Autenticidade, registro bruto criptografado e limite de taxa (UNY-DOC-006, 3) | P0 |
| SEC-026 | Segredo da ponte por ambiente, com **rotação sem parada** (dois segredos válidos ao mesmo tempo) | P0 |
| **Arquivos** | | |
| SEC-027 | Tipo real, tamanho e extensão validados; arquivos fora da raiz web | P0 |
| SEC-028 | Verificação de vírus nos envios **de alunos** (a partir da fase 2, ligada às entregas de tarefa) | P1 |
| SEC-029 | Dados pessoais (entregas, exportações) **nunca legíveis pelo Nginx** (seção 8.3) | P0 |
| **Operação** | | |
| SEC-030 | Acesso de suporte do operador com motivo e registro (FS-OPR-003) | P0 |
| SEC-031 | Plano de resposta a incidentes escrito e ensaiado (seção 14.3) | P0 |
| SEC-032 | Revisão trimestral de chaves SSH, usuários do banco e contas de operador | P1 |
| SEC-033 | **Nenhuma lista de palavras proibidas** nos campos de texto. A proteção contra injeção de SQL vem de **consultas parametrizadas** (padrão do framework), não de filtro de conteúdo. O servidor **já tem ModSecurity** (seção 7.7): nos hosts do AVA ele entra em modo de observação antes de bloquear, com exceções por campo para nomes (apóstrofo, "Walter", "Alter") | P0 |
| SEC-034 | Teste automático de cadastro com nomes difíceis: `Walter`, `Alterio`, `D'Ávila`, `O'Connor`, `Santa'Ana`, aspas, hífen, acentos e `Robert'); DROP TABLE alunos;--`. Todos devem **ser gravados e exibidos exatamente como digitados**, sem erro e sem execução | P0 |

---

## 14. LGPD e resposta a incidentes (operacional)

> Esta seção descreve **controles técnicos e operacionais**. Papéis legais, bases legais e prazos precisam de **validação jurídica** **[A CONFIRMAR]**. Isto não é aconselhamento jurídico.

### 14.1 Mapa de dados

| Dado | Onde | Proteção | Retenção proposta |
|---|---|---|---|
| Nome, e-mail | `alunos` | Senha em hash; acesso filtrado por parceiro | Enquanto a conta existir; anonimizados na exclusão |
| **CPF** | `alunos.cpf_cript` | Criptografia de coluna, hash cego, máscara nas telas, auditoria de visualização | Enquanto necessário para matrícula e certificado |
| Progresso, notas, entregas, comentários | Tabelas do aprendizado | Filtro por parceiro; entregas só para aluno e equipe do curso | Enquanto a matrícula existir; anonimizados na exclusão |
| Conteúdo bruto de webhooks (pode ter CPF e e-mail) | `eventos_pagamento.payload_bruto` | Criptografado | 90 dias, depois só o resumo |
| Logs da aplicação e do Nginx | Disco | Sem CPF e sem tokens | 30 dias |
| Auditoria | `ava_audit` | Só inserção | Sem prazo na v1 (revisar) |
| Backups | Fora do servidor | Criptografados | 7 diários, 4 semanais, 6 mensais |

Exclusão do titular e backups: os dados **continuam nos backups até o fim do ciclo de retenção**. Isso precisa constar na política de privacidade e ser validado pelo jurídico.

### 14.2 Papéis e documentos

Pontos a definir com o jurídico: quem é **controlador** e quem é **operador** (a plataforma, a Faculdade e cada parceiro) e o **contrato de tratamento de dados** com cada parceiro; modelo de política de privacidade e de termos por parceiro (FS-ADM-052); canal do titular; registro das operações de tratamento; necessidade de relatório de impacto.

### 14.3 Resposta a incidentes

| Etapa | Ação | Responsável |
|---|---|---|
| 1. Detectar | Alerta do monitoramento, relato de parceiro ou de aluno | A definir |
| 2. Conter | Suspender o parceiro ou o ambiente afetado (FS-OPR-002); revogar chaves e sessões; isolar o servidor se preciso | Operador |
| 3. Preservar | Guardar logs e cópia do estado **antes** de mexer | Operador |
| 4. Avaliar | Quais dados, de quantos titulares, de quais parceiros; houve acesso ao CPF? | Operador + jurídico |
| 5. Comunicar | Titulares, parceiros e a autoridade, dentro do prazo legal. A regulamentação da ANPD define prazos curtos **[A CONFIRMAR o prazo vigente com o jurídico]** | Jurídico |
| 6. Corrigir | Eliminar a causa; **rotacionar chaves e segredos**; restaurar de backup se necessário | Operador |
| 7. Revisar | Relatório do que aconteceu e do que muda | Todos |

Ensaiar o plano **uma vez antes do go-live**, simulando a perda de um segredo e a suspensão de um parceiro.

---

## 15. Backup e recuperação

### 15.1 Metas **[ESTIMADO]**

| Meta | Valor proposto | Observação |
|---|---|---|
| Perda máxima de dados (RPO) | **12 horas** | Dois backups do banco por dia. Para menos, é preciso log binário (seção 9.7) |
| Tempo para voltar (RTO) | **8 horas** | Para um servidor novo, com roteiro escrito |

Pagamentos: parte dos eventos perdidos pode ser **reprocessada** a partir das plataformas de venda (o Asaas guarda 14 dias de eventos; as outras precisam ser verificadas). A idempotência (UNY-DOC-006, 3.4) permite reprocessar sem duplicar matrículas.

### 15.2 O que entra

| Item | Frequência |
|---|---|
| Bancos `ava_prod` e `ava_audit` | 2 vezes ao dia |
| Arquivos em `/var/ava-storage` | Diária, incremental |
| Configurações do AVA (Nginx, pool PHP, unidades systemd, `.env` criptografado) | A cada mudança e semanal |
| `/etc/letsencrypt` | Semanal |
| **Chaves de criptografia do CPF** | Cópia **separada**, offline, fora do servidor e fora do backup comum |

### 15.3 Como

- Banco: `mysqldump --single-transaction --quick --routines --triggers --events --default-character-set=utf8mb4` com o usuário `ava_backup`
- Envio com uma ferramenta de backup **criptografada e incremental** (ex.: restic) para **armazenamento fora da VPS** (decisão 10)
- **A chave do backup fica fora do servidor.** Se ela morrer junto com a VPS, o backup é inútil
- Retenção: 7 diários, 4 semanais, 6 mensais
- Sem armazenamento **local** de backups além do mínimo temporário: o disco não comporta (seção 5.3)
- Se o plano da Contabo oferecer **snapshots**, usar antes de qualquer mudança grande (como as etapas da seção 19); o snapshot **não substitui** o backup

### 15.4 Teste de restauração

**Mensal**, em homologação: restaurar o último backup em um banco e uma pasta limpos, subir a aplicação e conferir uma amostra (alunos, matrículas, certificado com hash, abertura de um arquivo). Registrar a data e o tempo gasto. **Backup que nunca foi restaurado não conta.**

### 15.5 Cenários

| Cenário | Resposta |
|---|---|
| Arquivo apagado por engano | Restaurar do backup de arquivos |
| Banco corrompido ou apagado | Restaurar o último dump; reprocessar eventos de pagamento do intervalo perdido; rodar a reconciliação com o painel |
| Servidor comprometido | Seção 14.3; **não reaproveitar o servidor**; subir em outra VPS limpa, restaurar, rotacionar todos os segredos |
| VPS perdida | Nova VPS pelo roteiro; restaurar bancos, arquivos e configurações; refazer DNS e certificados; reconciliar com o painel |

---

## 16. Ambientes e deploy

### 16.1 Ambientes

| Ambiente | Onde | Banco | Observação |
|---|---|---|---|
| **Local** | Máquina de desenvolvimento (Windows, 8 GB de RAM) | MySQL local | Preferir PHP e MySQL **nativos** (ex.: Laragon) ou WSL2 com limite de memória, em vez de pilhas pesadas. A semelhança exata com a produção vem do **CI**, não da máquina local |
| **CI** | GitHub Actions | MySQL **5.7** em contêiner | Roda testes, análise e monta o pacote de deploy |
| **Homologação** | Na mesma VPS, host `ava-homolog…`, pool, usuário `avah`, banco e segredos próprios | `ava_homolog` | Protegida por senha, sem indexação, **dados fictícios e nenhum CPF real**; e-mail sem envio real; webhooks de teste das plataformas |
| **Produção** | Na mesma VPS | `ava_prod` | |

### 16.2 Fluxo do código

1. Trabalho em ramos; **ramo principal protegido** quando o plano do GitHub permitir (senão, por disciplina); mudança entra por revisão (pull request)
2. O CI executa: testes **contra MySQL 5.7**, teste de charset das tabelas, testes de isolamento, análise estática, `composer audit`, verificação de que nenhum recurso exclusivo do MySQL 8 foi usado
3. O CI gera um **pacote pronto** (código, dependências de produção, arquivos compilados do front-end) e, toda semana ou a cada aviso de segurança, a **imagem do PHP** com varredura de vulnerabilidades
4. Deploy do pacote para homologação (automático) e para produção (**manual, com aprovação**)

### 16.3 Deploy por releases

```bash
#!/usr/bin/env bash
# deploy.sh <homolog|prod> <pacote.tar.gz>   (ilustrativo; roda como usuário "deploy")
set -euo pipefail
case "$1" in
  prod)    APP=/var/www/ava;         HEALTH="https://ava-sistema.faculdadeunypublica.edu.br/saude" ;;
  homolog) APP=/var/www/ava-homolog; HEALTH="https://ava-homolog.faculdadeunypublica.edu.br/saude" ;;
  *) echo "uso: deploy.sh <homolog|prod> <pacote>"; exit 2 ;;
esac
REL="$APP/releases/$(date +%Y%m%d%H%M%S)"
PREV="$(readlink -f "$APP/current" || true)"

mkdir -p "$REL" && tar -xzf "$2" -C "$REL"
ln -sfn "$APP/shared/.env" "$REL/.env"
rm -rf "$REL/storage" && ln -sfn "$APP/shared/storage" "$REL/storage"

# permissões (seção 8.2)
chmod -R o-rwx "$REL"; chmod 711 "$REL"
find "$REL/public" -type d -exec chmod 755 {} +; find "$REL/public" -type f -exec chmod 644 {} +
mkdir -p "$REL/bootstrap/cache/views"

/usr/local/bin/ava-backup-rapido "$1"                  # dump antes de migrar; aborta o deploy se falhar

# migração e caches rodam em um contêiner descartável (sem rede), pelo script de root com comandos fixos
sudo /usr/local/sbin/ava-run "$1" "$REL" migrate       # usa o usuário ava_migrate; a senha só o script lê
sudo /usr/local/sbin/ava-run "$1" "$REL" cache         # config:cache, route:cache, view:cache DENTRO da release
chmod -R o-rwx "$REL/bootstrap/cache"

ln -sfn "$REL" "$APP/current.novo" && mv -Tf "$APP/current.novo" "$APP/current"   # troca atômica
sudo /usr/local/sbin/ava-ctl reload  "$1"              # recarrega o PHP-FPM do contêiner (limpa o OPcache)
sudo /usr/local/sbin/ava-ctl workers "$1"              # reinicia workers e agendador

if ! curl -fsS --max-time 10 "$HEALTH" >/dev/null; then
  [ -n "$PREV" ] && ln -sfn "$PREV" "$APP/current.novo" && mv -Tf "$APP/current.novo" "$APP/current"
  sudo /usr/local/sbin/ava-ctl reload "$1"; sudo /usr/local/sbin/ava-ctl workers "$1"
  echo "FALHOU: voltei para $PREV"; exit 1
fi
ls -1dt "$APP"/releases/* | tail -n +6 | xargs -r rm -rf     # mantém as 5 últimas
```

Os dois scripts de `root` têm **comandos fixos** e validam cada argumento (o ambiente só aceita `prod` ou `homolog`; a release só aceita caminhos sob `releases/`, sem `..`):

```bash
#!/usr/bin/env bash
# /usr/local/sbin/ava-ctl <reload|workers|update> <prod|homolog> [tag]   (root:root, 755; ilustrativo)
set -euo pipefail
case "${2:-}" in prod) P=ava ;; homolog) P=ava-homolog ;; *) exit 2 ;; esac
F="/etc/ava/compose-$2.yaml"
case "${1:-}" in
  reload)  docker compose -p "$P" -f "$F" kill -s USR2 fpm ;;               # reinício gracioso do FPM
  workers) docker compose -p "$P" -f "$F" restart worker scheduler ;;
  update)  [[ "${3:-}" =~ ^[A-Za-z0-9._-]+$ ]] || exit 2
           echo "AVA_IMAGE=registro/ava-php:$3" > "/etc/ava/image-$2.env"
           docker compose -p "$P" -f "$F" --env-file "/etc/ava/image-$2.env" pull
           docker compose -p "$P" -f "$F" --env-file "/etc/ava/image-$2.env" up -d ;;
  *) exit 2 ;;
esac
```

```bash
#!/usr/bin/env bash
# /usr/local/sbin/ava-run <prod|homolog> <release> <migrate|cache>   (root:root, 755; ilustrativo)
set -euo pipefail
case "${1:-}" in prod) APP=/var/www/ava ;; homolog) APP=/var/www/ava-homolog ;; *) exit 2 ;; esac
REL="${2:-}"; [[ "$REL" == "$APP"/releases/[0-9]* && "$REL" != *..* && -d "$REL" ]] || exit 2
IMG="$(sed 's/^AVA_IMAGE=//' "/etc/ava/image-$1.env")"
COMUM=(docker run --rm --network none --read-only --cap-drop ALL --security-opt no-new-privileges:true
       --user "$(id -u deploy):$(id -g ava)" -v "$REL:$REL" -v /var/run/mysqld/mysqld.sock:/var/run/mysqld/mysqld.sock
       -w "$REL" -e LOG_CHANNEL=stderr)
case "${3:-}" in
  migrate) export DB_PASSWORD="$(cat "$APP/shared/.migrate-pass")"      # não vai na linha de comando
           "${COMUM[@]}" -e DB_USERNAME=ava_migrate -e DB_PASSWORD "$IMG" php artisan migrate --force ;;
  cache)   "${COMUM[@]}" -e VIEW_COMPILED_PATH="$REL/bootstrap/cache/views" "$IMG" \
             sh -c 'php artisan config:cache && php artisan route:cache && php artisan view:cache' ;;
  *) exit 2 ;;
esac
```

O usuário `deploy` recebe, via `sudoers`, **apenas** a execução de `/usr/local/sbin/ava-ctl` e `/usr/local/sbin/ava-run`, com os argumentos permitidos **escritos por extenso**, sem curingas. Ambos pertencem a `root`, não podem ser alterados pelo `deploy` e são **revisados como código crítico**: um erro de validação neles equivale a dar `root` ao `deploy`. O recarregamento do Nginx fica no script de provisionamento de domínio, com permissão própria e restrita.

Os caminhos de homologação seguem a mesma estrutura sob `/var/www/ava-homolog` e `/var/ava-storage-homolog`, com o usuário `avah`.

### 16.4 Migrações sem parada

- Padrão **expandir e depois contrair**: primeiro só **adicionar** (colunas e tabelas novas, compatíveis com o código antigo); remover o que ficou sem uso só **em um deploy posterior**. Assim, voltar à release anterior **funciona** mesmo com o banco já migrado.
- Migração que apaga ou renomeia dados exige **revisão extra** e backup verificado.
- O `ava_migrate` é o único usuário com permissão de alterar estrutura.

### 16.5 Reversão

| Situação | Ação |
|---|---|
| Teste de saúde falhou no deploy | O script volta o link `current` à release anterior |
| Defeito descoberto depois | Repetir o deploy do pacote anterior |
| Migração destrutiva deu errado | Restaurar o backup feito logo antes (seção 15), aceitando a perda desde então |

---

## 17. Observabilidade e alertas

### 17.1 Logs

| Log | Onde | Retenção |
|---|---|---|
| Aplicação | `shared/storage/logs`, formato estruturado, **sem CPF e sem tokens** | 30 dias, com rotação |
| Nginx do AVA | `/var/log/nginx/ava_*.log`, com `$uri` | 30 dias |
| PHP-FPM | `shared/storage/logs/php-fpm.log` | 30 dias |
| Tarefas e falhas | Tabelas de fila e falhas | Até tratar |
| Auditoria | `ava_audit` | Seção 14.1 |

A rotação (`logrotate`) é obrigatória: **log sem rotação enche o disco**.

### 17.2 Verificação de saúde

Endereço `/saude`, sem dados sensíveis: banco responde, espaço livre, atraso da fila, última mensagem bem-sucedida da ponte com o painel. Uma versão **pública e mínima** (apenas "ok") serve ao monitor externo. Responde em um **host técnico** (`ava-sistema…`), independente de qualquer parceiro.

### 17.3 Alertas **[ESTIMADO]**

| Condição | Aviso | Crítico |
|---|---|---|
| Memória disponível | Abaixo de 1,5 GiB por 10 min | Abaixo de 800 MiB |
| Swap em uso | Acima de 25% | Acima de 50% |
| **Espaço livre em disco** | Abaixo de 20 GB | Abaixo de 10 GB |
| Carga (média de 5 min) | Acima de 3,5 por 15 min | Acima de 6 |
| Pool PHP-FPM (no contêiner) | Mensagem de limite de processos atingido | Repetida |
| Fila de webhooks | Tarefa esperando mais de 5 min | Mais de 30 min |
| Tarefas com falha | Qualquer uma | Mais de 10 |
| Ponte com o painel | Mensagem em `erro` ou pendente há mais de 30 min | Mais de 4 h |
| Certificado SSL | Vence em 14 dias | Vence em 3 dias |
| Domínio de parceiro preso em `aguardando_dns` | Mais de 72 h | |
| **Backup** | Último sucesso há mais de 14 h | Mais de 26 h |
| Teste de restauração | Atrasado mais de 35 dias | |
| Erros 5xx | Acima de 2% por 5 min | Acima de 10% |
| Disponibilidade (monitor externo, a cada minuto) | 2 falhas seguidas | 5 falhas |

Canal: e-mail e o mensageiro da equipe. **Quem recebe e quem atende fora do horário** precisa ser definido **[A CONFIRMAR]**.

---

## 18. Modernização e saída

| Caminho | Quando | Observação |
|---|---|---|
| **Ubuntu Pro (ESM)** | **Agora**, como pré-requisito (SEC-005) | Mantém correções de segurança do sistema; **não cobre a imagem base do contêiner nem repositórios de terceiros**, e não resolve o MySQL 5.7 |
| **Novo servidor para o AVA** (Ubuntu 24.04, MySQL 8, PHP 8.3 ou mais novo) | No primeiro gatilho da seção 4.5, ou por decisão | Preferido. Como a conexão com o painel é por API e o AVA já roda em contêiner, a mudança é de infraestrutura: o mesmo Compose sobe no servidor novo |
| Atualizar o sistema atual no mesmo lugar (20.04 → 22.04 → 24.04) | **Não recomendado** em produção compartilhada | A atualização é sequencial (não pula versões) e mexe nos pacotes do PHP 7.2 e do MySQL 5.7 que o legado usa |

Roteiro de mudança para um servidor novo:

1. Provisionar a VPS pelo roteiro; Docker, Nginx e MySQL 8 (o Compose é o mesmo; muda só a forma de conectar ao MySQL, que passa a ser o MySQL 8 local)
2. Restaurar o último backup; **conferir charset e collations**; rodar as migrações
3. Subir homologação e testar com a restauração
4. Congelar escritas, fazer um último dump e restaurar
5. Apontar o DNS (os registros por parceiro ou o curinga) e reemitir os certificados
6. Atualizar, nos dois lados, o endereço usado pela ponte com o painel (o segredo de assinatura é mantido, ou rotacionado com os dois válidos)
7. Manter o servidor antigo por alguns dias, só leitura, até a reconciliação fechar sem divergências

---

## 19. Plano de implantação em etapas

Cada etapa tem **teste de aceite** e **reversão**. **Nada é aplicado sem snapshot e janela de baixo movimento.**

| # | Etapa | Aceite | Reversão |
|---|---|---|---|
| 0 | Snapshot; rodar o Anexo A (itens 1 e 9); começar a medição de 7 dias. **PHP 8 no sistema: verificado e indisponível (08/10/2026). Se o Docker também não for instalável e suportado, parar aqui e ir para a opção B** | Saída completa em mãos | n/a |
| 1 | **Firewall e fechamento da porta 3306 (seção 4.6)**; usuário `deploy` e SSH por chave; swap; Ubuntu Pro | Acesso por chave funciona **antes** de fechar a senha; sites e painel continuam no ar; a 3306 não responde de fora | `ufw disable`; reabrir a regra; `swapoff` |
| 2 | Instalar o Docker e subir um contêiner PHP 8.3 de teste (seção 6.2) | `php -v` do servidor ainda é 7.2; `iptables-save` antes e depois só difere nas regras do Docker; nada escuta em `0.0.0.0`; teste de fumaça do legado passa | Remover o Docker e restaurar as regras de firewall salvas |
| 3 | Criar usuários `ava` e `avah`, pastas, Compose, scripts `ava-ctl` e `ava-run`, bancos e usuários do banco | Contêineres sobem; conexão com o banco por `ava_app` (via socket) funciona; `ava_app` **não** consegue alterar `ava_audit` | Remover contêineres, usuários e bancos |
| 4 | Nginx: limites e snippets; vhost de **homologação** | `nginx -t` passa; legado intacto; homologação responde | Remover os arquivos do AVA e recarregar |
| 5 | DNS curinga; primeiro certificado; homologação no ar | `https://ava-homolog…/saude` ok | Remover o registro curinga |
| 6 | Backup fora do servidor e **primeira restauração** | Restauração completa feita em homologação | n/a |
| 7 | Monitoramento e alertas | Alerta de teste chega ao canal | Desligar alertas |
| 8 | Medição de 7 dias dentro dos limites | Seção 5.6 atendida | Rever gatilhos |
| 9 | Checklist de go-live (seção 4.4) com **parceiro piloto** | Todos os itens marcados | Suspender o parceiro |

---

## 20. Decisões e verificações pendentes

| # | Item | Tipo | Impacto |
|---|---|---|---|
| 1 | ~~Opção A ou B~~ **Decidido em 08/10/2026:** opção A2 (Docker na VPS atual), com migração futura para VPS própria; pré-requisitos da seção 4.4 | Decisão | Toda a infraestrutura |
| 2 | ~~PHP 8.3 ou 8.4 disponível para o Ubuntu 20.04~~ **Verificado em 08/10/2026: não existe** (repositório Sury do `focal` vazio) | Verificação | Seção 6.1 |
| 3 | **Ubuntu Pro**: elegibilidade da assinatura gratuita para a organização, ou assinatura paga | Verificação | SEC-005 |
| 4 | ~~Laravel 12 ou 13~~ **Decidido: Laravel 13.** Resta confirmar a compatibilidade com o MySQL 5.7 (teste no CI do M0) | Decisão | Seção 6.1 |
| 5 | Aprovar o **swap de 2 GiB** | Decisão | Seção 5.5 |
| 6 | Medição de 7 dias do servidor | Verificação | Seção 5.6 |
| 7 | **Armazenamento de arquivos**: disco local com teto ou armazenamento externo desde cedo; qual fornecedor | Decisão | Seção 5.3 |
| 8 | Provedor de vídeo (UNY-DOC-004, 15, item 8) e hospedagem do áudio | Decisão | Custo e banda |
| 9 | Provedor de **e-mail transacional** | Decisão | Seção 12 |
| 10 | **Destino do backup** fora da VPS e guarda da chave de criptografia | Decisão | Seção 15 |
| 11 | **Verificação de vírus** nos envios de alunos (fase 2): serviço externo, ou processamento sob demanda sem daemon residente | Decisão | SEC-028; memória |
| 12 | Quem recebe os alertas e quem atende fora do horário | Decisão | Seção 17 |
| 13 | CI no GitHub Actions com deploy por SSH para o usuário `deploy`, ou deploy manual por quem opera o servidor | Decisão | Seção 16 |
| 14 | phpMyAdmin exposto? Restringir | Verificação | SEC-004 |
| 15 | Usuário do pool PHP legado (é `www-data`?) | Verificação | Seção 8.3 |
| 16 | Comportamento do `default_server` do Nginx para nomes desconhecidos | Verificação | Seção 7.5 |
| 17 | Charset padrão, `max_connections` e `innodb_buffer_pool_size` do MySQL | Verificação | Seção 9 |
| 18 | Existem **backups dos sistemas atuais** que já rodam neste servidor? (fora do escopo do AVA, mas é a mesma máquina) | Verificação | Risco geral |
| 19 | Papéis da LGPD, contrato com parceiros e prazo de comunicação de incidentes | Decisão jurídica | Seção 14 |
| 20 | Proteção na frente do servidor (proxy, filtro de ataques) | Decisão opcional | Segurança |
| 21 | Limites de banda e de tráfego do plano da Contabo; snapshots oferecidos | Verificação | Seções 5.4 e 15.3 |
| 22 | ~~Docker disponível no Ubuntu 20.04~~ **Verificado:** `docker.io` 26.1.3 e `docker-compose-v2` 2.27.1 em `focal-updates/universe`. Resta o suporte contínuo (Ubuntu Pro, ESM apps) | Verificação | Seção 6.2 |
| 23 | ~~Efeito nas regras de `iptables`/`ufw`~~ **Verificado:** não há regras nem firewall; `ip_forward = 0`. O firewall passa a ser a primeira etapa | Verificação | Seções 4.6 e 6.2 |
| 24 | Registro privado de imagens (por exemplo o do GitHub) e guarda da credencial | Decisão | Seção 6.3 |
| 25 | ~~Caminho do socket do MySQL~~ **Medido:** `/var/run/mysqld/mysqld.sock`. Resta conferir as permissões do socket | Verificação | Seções 6.3 e 9.2 |
| 26 | O que significa "mais estruturado" para migrar para VPS própria (critério e data) | Decisão | Seções 4.5 e 18 |
| 27 | **Alguém conecta ao MySQL por fora (porta 3306)?** Quem e de onde; contas com host diferente de `localhost` | **Verificação (urgente)** | Seção 4.6 |
| 28 | Vincular o servidor ao **Ubuntu Pro** e conferir se aparece o `esm-apps` | Verificação | SEC-005 e seção 6.2, risco 7 |
| 29 | ~~Quem bloqueava os nomes?~~ **ModSecurity** (módulo compilado à mão), `modsecurity on` em `nginx.conf` e `off` nos hosts do painel. Resta: em que bloco está a diretiva, o conteúdo de `/etc/nginx/nginx-modsecurity.conf` (sem segredos) e **qual regra** barrava (ID no registro de auditoria) | Verificação | Seção 7.7 |
| 30 | Decisão sobre o ModSecurity nos hosts do AVA (detecção, exceções por campo, `/webhooks/` sem filtro) e quem mantém o módulo compilado | Decisão | Seção 7.7 |

---

## 21. Rastreabilidade

| Requisito ou decisão | Onde está atendido |
|---|---|
| BR-NFR-001 (desempenho) | Seções 5, 7, 17 |
| BR-NFR-003 (backup e recuperação testada) | Seção 15 |
| BR-NFR-004 (LGPD) | Seção 14 |
| BR-NFR-005 (auditoria imutável) | Seção 9.3 |
| BR-TEN-004 (domínio próprio) | Seção 11 |
| BR-TEN-006 (limites por plano) | Seção 5.3 |
| ADR-001 (mesma VPS) e ADR-015 (Docker) | Seções 4 e 6 |
| ADR-003 (host e cookies) | Seções 7 e 8.4, SEC-010 |
| ADR-007 e ADR-009 (vídeo fora, fila no banco) | Seções 5.3 e 10 |
| ADR-014 (CPF criptografado) | SEC-017 |

---

## Anexo A. Comandos de verificação (somente leitura)

Rode no servidor e cole a saída. **Antes de colar, confira se não aparece nenhuma senha, chave ou token.** Não cole o `crontab`, que pode conter segredos. Os itens 1, 9, 10 e 11 são os mais importantes.

```bash
# 1. Docker: já existe? Qual pacote está disponível?  (decide a viabilidade da opção A2)
docker --version 2>&1; docker compose version 2>&1
apt-cache policy docker.io docker-compose-v2 docker-compose-plugin | grep -E "^[a-z]|Candidate"
cat /etc/docker/daemon.json 2>&1

# 2. Alternativas do PHP hoje
update-alternatives --display php | head -5

# 3. Ubuntu Pro e atualizações
pro status 2>/dev/null || ua status
systemctl is-active unattended-upgrades fail2ban

# 4. Portas abertas e firewall
ss -tlnp
ufw status

# 5. Nginx: servidor padrão e nomes atendidos
nginx -T 2>/dev/null | grep -nE "default_server|server_name" | head -60
nginx -V 2>&1 | tr ' ' '\n' | grep http_v2_module

# 6. Usuários do Nginx e do PHP-FPM
ps -eo user,comm | grep -E "php-fpm|nginx|mysqld" | sort | uniq -c

# 7. MySQL: configuração relevante
mysql -e "SELECT @@version, @@character_set_server, @@collation_server, @@sql_mode,
          @@max_connections, @@innodb_buffer_pool_size, @@innodb_large_prefix,
          @@innodb_default_row_format, @@log_bin, @@bind_address, @@socket\G"

# 8. Onde está o espaço em disco
du -xh --max-depth=2 /var /home /opt 2>/dev/null | sort -h | tail -20

# 9. Regras de rede ANTES do Docker (guardar a saída para comparar depois)
iptables -S | head -60
sysctl net.ipv4.ip_forward
ufw status verbose

# 10. Quem usa o MySQL de fora? (somente leitura; o segundo comando pede a senha do MySQL)
ss -tnp state established '( sport = :3306 )'
mysql -u root -p -e "SELECT user,host FROM mysql.user WHERE host NOT IN ('localhost','127.0.0.1','::1');"

# 11. Existe filtro de conteúdo (WAF) na frente ou dentro do Nginx? (somente leitura)
nginx -V 2>&1 | grep -ioE 'modsecurity|naxsi' | sort -u
nginx -T 2>/dev/null | grep -inE 'modsecurity|naxsi|waf' | head
curl -sI https://painel.faculdadeunypublica.edu.br | grep -iE '^(server|cf-ray|x-sucuri|x-waf)'
ls /etc/modsecurity /usr/share/modsecurity-crs 2>&1 | head
```

## Anexo B. Fontes consultadas

| Tema | Fonte | Observação |
|---|---|---|
| Fim do suporte padrão do Ubuntu 20.04, Ubuntu Pro e ESM | Blog da Canonical/Ubuntu ("Ubuntu 20.04 LTS Standard Support has ended") | Oficial |
| PHP 8.3 no Ubuntu 20.04 pelo repositório Sury | php.watch; discussão da DigitalOcean (mais cautelosa quanto ao 8.3 em 20.04) | Secundária. **Contrariada pela medição de 08/10/2026:** o repositório do `focal` está vazio |
| Datas de suporte do PHP 8.2 a 8.5 | endoflife.date; tabelas de terceiros que citam php.net/supported-versions | Secundária; php.net é a origem |
| Requisitos de PHP e MySQL do Laravel 12 e 13 | Resumos de terceiros | **Secundária: conferir na documentação oficial do Laravel** |
| Fim de vida do PHP 7.2 (nov/2020) | Tabela de imagens de contêiner PHP | Secundária |
| Fim de vida do MySQL 5.7 (out/2023) | Conhecimento geral | Confirmar em dev.mysql.com |
| Limites do MySQL 5.7 (CHECK, permissões parciais, SKIP LOCKED, charset padrão) | Conhecimento técnico do produto | **Confirmar na documentação do MySQL 5.7** antes da implementação |
| ESM não cobrir repositórios de terceiros | Conhecimento técnico do produto | Confirmar com a Canonical |
| Repositório Sury (PPA) do `focal` vazio | Saída de `curl` no servidor em 08/10/2026 (`Release` de 09/06/2025, `Packages.gz` com 20 bytes, `Packages` com 0 bytes) | **Medido** |
| Docker publica portas ignorando o `ufw`; grupo `docker` equivale a `root`; `live-restore` | Conhecimento técnico do produto | Confirmar na documentação do Docker |

## 22. Fechamento do conjunto e próximo passo

Com este documento, o conjunto de documentação base está completo:

| Doc | Assunto |
|---|---|
| 001 | Visão do produto |
| 002 | Requisitos de negócio |
| 003 | Especificação funcional |
| 004 | Arquitetura |
| 005 | Banco de dados |
| 006 | API e webhooks |
| 007 | Design system |
| 008 | Infraestrutura, segurança e deploy |

**Próximo passo sugerido:** um **plano de construção da fase 1**: ordem dos módulos, dependências entre eles, o que dá para entregar e mostrar primeiro (por exemplo, um ambiente de aluno navegável com dados fictícios) e a lista de tarefas inicial para começar o código, já preparada para o repositório.
