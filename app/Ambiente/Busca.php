<?php

namespace App\Ambiente;

use App\Models\Aluno;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Matricula;
use Illuminate\Support\Collection;

/**
 * Busca simples por título (e descrição do curso). Os caracteres especiais do LIKE são
 * neutralizados e a consulta usa parâmetros, nunca texto concatenado (SEC-033).
 */
class Busca
{
    public const MINIMO = 2;

    public const MAXIMO = 80;

    public static function normalizar(string $termo): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $termo) ?? ''), 0, self::MAXIMO);
    }

    /** @return array{cursos: Collection<int, Curso>, aulas: Collection<int, Aula>} */
    public function procurar(string $termo, Aluno $aluno): array
    {
        $termo = self::normalizar($termo);

        if (mb_strlen($termo) < self::MINIMO) {
            return ['cursos' => collect(), 'aulas' => collect()];
        }

        $padrao = '%'.addcslashes($termo, '\\%_').'%';

        $cursos = Curso::query()->with('capa')->publicados()
            ->where(fn ($q) => $q->where('titulo', 'like', $padrao)->orWhere('descricao', 'like', $padrao))
            ->orderBy('titulo')->limit(30)->get();

        // Aulas só dos cursos em que o aluno tem matrícula ativa.
        $cursosDoAluno = Matricula::query()->where('aluno_id', $aluno->id)
            ->where('estado', Matricula::ATIVA)->pluck('curso_id');

        $aulas = $cursosDoAluno->isEmpty() ? collect() : Aula::query()
            ->with('curso')
            ->publicadas()->whereIn('curso_id', $cursosDoAluno)
            ->where('titulo', 'like', $padrao)
            ->orderBy('titulo')->limit(30)->get();

        return ['cursos' => $cursos, 'aulas' => $aulas];
    }
}
