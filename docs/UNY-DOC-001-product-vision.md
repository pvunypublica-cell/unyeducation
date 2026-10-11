# UNY-DOC-001 · Product Vision

**Projeto:** UnyEducation
**Versão:** 0.3 · Foco: plataforma de Cursos Livres
**Status:** Rascunho para validação
**Substitui:** versão 0.1 (focada em pós-graduação e registro acadêmico)

---

## 1. O que é

Uma plataforma de cursos online em que **cada parceiro tem o seu próprio ambiente**, com marca, domínio, visual, cursos e preços próprios, e o **aluno tem uma experiência de streaming**: abre, escolhe, assiste, continua de onde parou.

A plataforma é a infraestrutura. O parceiro é o dono do curso e da experiência. O aluno nunca precisa saber que a plataforma existe.

## 2. Problema

- Quem produz cursos hoje escolhe entre plataformas genéricas, com visual padronizado e pouca personalização, e sistemas feitos sob medida, caros e lentos de manter.
- Plataformas de ensino tradicionais parecem sistemas acadêmicos: menus, listas, módulos em texto. O aluno de curso livre espera algo mais próximo de um streaming.
- Parceiros já têm um jeito de vender (checkout, gateway, página de vendas) e não querem trocar tudo para usar uma nova plataforma.

## 3. Proposta

> Hospedar, entregar e apresentar cursos com a cara do parceiro, integrando-se ao meio de pagamento que ele já usa.

## 4. Quem usa

| Papel | O que quer |
|---|---|
| **Operador da plataforma** | Criar e manter parceiros, garantir isolamento, estabilidade e evolução do produto |
| **Parceiro** (cliente principal) | Ter um ambiente próprio e bonito, controlar cursos, vitrines, preços e alunos, sem depender de desenvolvedor |
| **Aluno** | Encontrar, assistir e concluir cursos com uma experiência fluida, em qualquer dispositivo |
| **Equipe do parceiro** (instrutores, suporte) | Publicar conteúdo, acompanhar alunos, responder dúvidas |

## 5. Princípios

1. **O parceiro manda na experiência.** Tudo o que o aluno vê pode ser configurado pelo parceiro.
2. **Isolamento total.** Dados de um parceiro nunca aparecem para outro.
3. **Netflix, não ERP.** Cada tela do aluno deve parecer um streaming, não um sistema.
4. **Integrar, não substituir.** A plataforma se conecta ao checkout que o parceiro já tem.
5. **Simples primeiro.** Cursos livres não precisam de burocracia acadêmica. Complexidade entra só quando um parceiro realmente precisar.
6. **Tudo rastreável.** Todo requisito liga a uma decisão, tabela, API, tela e teste.
7. **Estudo livre, certificação condicionada.** O aluno estuda sem restrição; a liberação do certificado depende da situação do parceiro no painel de matrículas.

## 6. Escopo da versão 1

### Dentro

- Ambientes multi-parceiro com domínio e marca próprios
- Catálogo de cursos com módulos e aulas (vídeo, texto, material para download)
- Home estilo streaming: banner, vitrines, "continuar assistindo", trilhas, busca
- Progresso do aluno, favoritos e retomada de aula
- Painel do parceiro: cursos, alunos, vitrines, aparência, preços, relatórios básicos
- Integração de pagamento por **gateway/checkout do parceiro** (ver seção 7)
- Certificado de conclusão com hash e QR Code gerados pelo painel de matrículas, liberado conforme a situação do parceiro
- Espelhamento das matrículas no painel de matrículas da Faculdade Unypública
- **Fase 2:** tarefas e provas, **opcionais por curso**: cada parceiro decide se o curso terá avaliações (UNY-DOC-002, seção 12.1)
- Painel do operador: criar parceiros, planos, limites e auditoria

### Fora (por enquanto)

- Registro acadêmico, histórico escolar, XML, assinatura ICP-Brasil, regras de pós-graduação
- Checkout próprio da plataforma
- Marketplace comum entre parceiros
- App nativo (a versão 1 é web responsiva)
- IA aplicada ao conteúdo (arquitetura prevê, implementação depois)

## 7. Modelo de pagamento

**Decisão:** o parceiro vende no checkout que já usa. A plataforma **não processa pagamentos**, ela **reage a eles**.

```text
Parceiro vende no checkout dele
        ↓
Gateway confirma o pagamento
        ↓
Webhook chega à plataforma
        ↓
Adaptador do gateway traduz para um evento padrão
        ↓
Matrícula criada / liberada / suspensa
        ↓
Aluno recebe acesso
```

Princípios do modelo:

- **Adaptadores por gateway.** Cada integração traduz o formato do gateway para um evento interno padrão (compra aprovada, reembolso, chargeback, assinatura cancelada, etc.).
- **Fonte da verdade do acesso é a plataforma**, a do dinheiro é o gateway.
- **Conciliação.** Todo webhook é registrado, e eventos duplicados ou fora de ordem não podem gerar acesso errado.
- **Evolução possível.** Um checkout nativo pode ser adicionado depois, como mais um adaptador, sem mudar o restante.
- **Três níveis.** Nativo (plataformas de venda mais usadas), genérico (webhook configurável) e manual (liberação pela equipe do parceiro, com registro), para atender quem não usa uma plataforma integrada.

## 8. Experiência do aluno

- Entrada rápida (login por e-mail, link mágico ou acesso vindo da compra)
- Home com vitrines horizontais, destaque principal e **continuar de onde parou**
- Página do curso com capa, trailer, módulos e progresso
- Player focado: próxima aula automática, velocidade, anotações
- Funciona bem em celular, tablet e TV (navegador)
- Tudo com o visual do parceiro: cores, fontes, logo e tom de comunicação

## 9. Personalização pelo parceiro

| Nível | O que o parceiro controla |
|---|---|
| **Marca** | Logo, cores, fontes, favicon, domínio próprio |
| **Home** | Banner, ordem e tipo das vitrines, textos |
| **Catálogo** | Cursos, categorias, trilhas, destaques |
| **Comercial** | Preços, ofertas, vínculo com produtos do gateway |
| **Comunicação** | E-mails e mensagens com a identidade do parceiro |
| **Certificado** | Modelo visual do certificado de conclusão |

O editor visual (estilo Canva) entra principalmente para **home** e **certificado**. Sempre com blocos obrigatórios protegidos (por exemplo, QR Code de validação).

## 10. Multi-parceiro

- Cada parceiro é um **tenant** com dados isolados
- Domínio próprio ou subdomínio da plataforma
- Permissões por papel dentro do parceiro
- Limites por plano (alunos, armazenamento, cursos)
- Nada compartilhado entre parceiros, exceto o que o operador definir explicitamente

## 11. Certificado de conclusão e conexão com o painel

O aluno **estuda sem restrição**. O **certificado**, porém, depende da situação do parceiro, como já funciona hoje no sistema de créditos e assinatura da Faculdade Unypública.

- Emitido ao cumprir a regra do curso definida pelo parceiro (ex.: 100% das aulas)
- **Liberado somente se o parceiro estiver em dia** (assinatura ou matrículas, conforme as regras do painel)
- Cada matrícula do AVA é **espelhada no painel de matrículas**, que gera o **hash e o QR Code de validação** usados no certificado
- Parceiro fora de dia: o aluno conclui normalmente e o certificado fica **retido**; ao regularizar, é liberado automaticamente
- Modelo visual definido pelo parceiro; revogável, com histórico

```text
Aluno conclui o curso
        ↓
Parceiro em dia? ── sim ──▶ hash/QR do painel ──▶ certificado liberado
        │
        └── não ──▶ certificado retido ──▶ parceiro regulariza ──▶ liberado automaticamente
```

A fonte da verdade da situação do parceiro é o **painel**. O AVA pergunta e obedece; não duplica as regras de crédito e assinatura.

## 12. Como saberemos que deu certo

- Um parceiro novo publica o ambiente e o primeiro curso **sem ajuda técnica**
- A compra no gateway libera o acesso ao aluno em **poucos segundos**, sem intervenção manual
- Alunos conseguem **retomar** qualquer aula em dois toques
- **Zero** vazamento de dados entre parceiros
- Parceiros avaliam o ambiente como "a minha marca", não "uma plataforma de terceiros"

## 13. Decisões em aberto

| # | Decisão | Impacto |
|---|---|---|
| 1 | ~~Quais plataformas de venda recebem adaptador nativo~~ **Resolvida:** três níveis de integração (nativo, webhook genérico e matrícula manual). Nativo para Hotmart, Kiwify, Eduzz, Monetizze, Greenn e Braip; gateways Asaas, Stripe, Mercado Pago e Pagar.me. Detalhes no UNY-DOC-002 | Define os adaptadores |
| 2 | Onde ficam os vídeos (hospedagem própria, serviço especializado ou links externos) | Custo, segurança e qualidade do player |
| 3 | Modelo de cobrança do parceiro pela plataforma: segue o sistema atual de assinatura e créditos do painel? | Planos, limites e retenção de certificados |
| 4 | Domínio próprio com SSL automático desde a v1 | Infraestrutura e onboarding |
| 5 | ~~Nome definitivo do produto~~ **Resolvida (09/10/2026):** **UnyEducation** | Marca e documentação |
| 6 | Regras exatas de "em dia" (assinatura, créditos, matrículas) e o que o painel responde | Retenção e liberação de certificados |
| 7 | ~~Dados do aluno exigidos pelo painel~~ **Resolvida:** o painel exige o **CPF** do aluno para espelhar a matrícula e gerar o hash. O CPF é dado obrigatório para a certificação | Cadastro e banco de dados |

## 14. Próximos documentos

1. **UNY-DOC-002** · Business Requirements
2. **UNY-DOC-003** · Functional Specification (telas, fluxos, permissões)
3. **UNY-DOC-004** · Arquitetura multi-parceiro
4. **UNY-DOC-005** · Banco de dados
5. **UNY-DOC-006** · API e webhooks de pagamento
6. **UNY-DOC-007** · Design System
7. **UNY-DOC-008** · Infraestrutura, segurança e deploy
