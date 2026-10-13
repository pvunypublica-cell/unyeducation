# UnyEducation

Plataforma de cursos livres multi-parceiro (white label), com experiência de aluno estilo streaming.

**Status:** documentação concluída (UNY-DOC-001 a 009). Código começa no marco M0 (ver `docs/UNY-DOC-009-plano-de-construcao-fase-1.md`).

## Documentos

| Doc | Assunto |
|---|---|
| UNY-DOC-001 | Visão do produto |
| UNY-DOC-002 | Requisitos de negócio |
| UNY-DOC-003 | Especificação funcional |
| UNY-DOC-004 | Arquitetura multi-parceiro |
| UNY-DOC-005 | Banco de dados |
| UNY-DOC-006 | API e webhooks |
| UNY-DOC-007 | Design system |
| UNY-DOC-008 | Infraestrutura, segurança e deploy |
| UNY-DOC-009 | Plano de construção da Fase 1 |

## Regras do repositório

- Código entra por *pull request* em ramo próprio, nunca direto no `main` (a exceção é este commit inicial).
- Nenhuma senha, chave, `.env` ou dado real de aluno (CPF) no repositório, nem de teste.
- Dados de teste são sempre fictícios.

## Desenvolvimento

- Requisitos: PHP 8.3+, Composer, MySQL (a produção usa 5.7; o CI testa em 5.7).
- `cp .env.example .env && composer install && php artisan key:generate`
- Crie quatro bancos vazios: `unyeducation`, `unyeducation_test`, `ava_audit` e `ava_audit_test`
  (os dois últimos guardam a auditoria). Depois rode `php artisan migrate` e `php artisan test`.
- Criar parceiros de teste: `php artisan parceiro:criar demo-a "Parceiro A" --host=ava-a.test --admin-email=voce@exemplo.com`
  (repita com `demo-b` e `--host=ava-b.test`). No Windows, aponte `ava-a.test` e `ava-b.test` para 127.0.0.1
  no arquivo hosts, ou use os sites automáticos do Laragon.
- O parceiro é descoberto pelo endereço (host) da requisição; endereço desconhecido responde 404.
- Os testes recusam rodar em banco cujo nome não contenha "test".
- Trabalho sempre em ramo + pull request; nunca direto em `main`.
- Docker de produção: `docker/php/` (ver docs/UNY-DOC-008).
