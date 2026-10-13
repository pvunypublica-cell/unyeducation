<?php

namespace App\Ambiente;

use App\Models\ConfiguracaoVisual;

/**
 * Tema do ambiente do aluno. O parceiro escolhe TOKENS (cores, fonte, raio), nunca CSS livre
 * (UNY-DOC-004, 11): todo valor que chega ao estilo vem de uma lista fixa ou de uma cor
 * validada. Tema base nesta versão: Cinema (UNY-DOC-007, 4.1).
 */
class Tema
{
    public const FONTES = [
        'sora' => '"Sora", system-ui, -apple-system, "Segoe UI", sans-serif',
        'inter' => '"Inter", system-ui, -apple-system, "Segoe UI", sans-serif',
        'sistema' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
    ];

    public const RAIOS = ['reto' => '2px', 'suave' => '10px', 'arredondado' => '18px'];

    /** Contraste mínimo da cor principal contra o fundo (alvo gráfico, WCAG 1.4.11). */
    public const CONTRASTE_MINIMO = 3.0;

    public const PADRAO = [
        'tema_base' => 'cinema',
        'cor_principal' => '#8B6CFF',
        'cor_secundaria' => '#F5B942',
        'fonte_titulo' => 'sora',
        'fonte_texto' => 'inter',
        'raio_borda' => 'suave',
    ];

    private const CINEMA = [
        'fundo' => '#0E0E14',
        'superficie' => '#16161F',
        'superficie-2' => '#1F1F2B',
        'borda' => '#2C2C3A',
        'texto' => '#F4F4F8',
        'texto-suave' => '#A6A6B8',
        'sucesso' => '#3DD68C',
        'aviso' => '#F5B942',
        'erro' => '#FF6B6B',
        'foco' => '#C9BBFF',
    ];

    /** @var array<string, mixed> */
    private array $tokens;

    /** @var list<string> */
    private array $avisos = [];

    /** @param  array<string, mixed>  $escolhas */
    public function __construct(array $escolhas = [])
    {
        $this->tokens = self::PADRAO;

        foreach (['cor_principal', 'cor_secundaria'] as $chave) {
            if (! array_key_exists($chave, $escolhas) || $escolhas[$chave] === null) {
                continue;
            }

            if (! Contraste::hexValido($escolhas[$chave])) {
                $this->avisos[] = "{$chave}: cor inválida, mantido o padrão.";

                continue;
            }

            if (Contraste::razao($escolhas[$chave], self::CINEMA['fundo']) < self::CONTRASTE_MINIMO) {
                $this->avisos[] = "{$chave}: contraste insuficiente com o fundo, mantido o padrão.";

                continue;
            }

            $this->tokens[$chave] = strtoupper($escolhas[$chave]);
        }

        foreach (['fonte_titulo', 'fonte_texto'] as $chave) {
            if (isset($escolhas[$chave]) && array_key_exists($escolhas[$chave], self::FONTES)) {
                $this->tokens[$chave] = $escolhas[$chave];
            }
        }

        if (isset($escolhas['raio_borda']) && array_key_exists($escolhas['raio_borda'], self::RAIOS)) {
            $this->tokens['raio_borda'] = $escolhas['raio_borda'];
        }
    }

    /** Tema publicado do parceiro atual (ou o padrão, se não houver). */
    public static function doParceiro(): self
    {
        $registro = ConfiguracaoVisual::query()
            ->where('tipo', 'tema')->where('estado', ConfiguracaoVisual::PUBLICADO)->first();

        return new self($registro?->conteudo_json ?? []);
    }

    public function token(string $chave): mixed
    {
        return $this->tokens[$chave] ?? null;
    }

    /** @return list<string> */
    public function avisos(): array
    {
        return $this->avisos;
    }

    /** Variáveis CSS do tema. Só sai daqui texto montado a partir de valores validados. */
    public function css(): string
    {
        $principal = $this->tokens['cor_principal'];
        $secundaria = $this->tokens['cor_secundaria'];

        $variaveis = [];
        foreach (self::CINEMA as $nome => $valor) {
            $variaveis["--cor-{$nome}"] = $valor;
        }

        $variaveis += [
            '--cor-principal' => $principal,
            '--cor-sobre-principal' => Contraste::textoSobre($principal),
            '--cor-secundaria' => $secundaria,
            '--cor-sobre-secundaria' => Contraste::textoSobre($secundaria),
            '--fonte-titulo' => self::FONTES[$this->tokens['fonte_titulo']],
            '--fonte-texto' => self::FONTES[$this->tokens['fonte_texto']],
            '--raio' => self::RAIOS[$this->tokens['raio_borda']],
        ];

        $linhas = [];
        foreach ($variaveis as $nome => $valor) {
            $linhas[] = "{$nome}:{$valor}";
        }

        return ':root{'.implode(';', $linhas).'}';
    }
}
