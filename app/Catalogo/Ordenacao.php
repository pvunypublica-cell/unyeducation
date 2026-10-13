<?php

namespace App\Catalogo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reordenação por "subir" e "descer" (funciona sem JavaScript e com teclado).
 * Renumera os irmãos de 1 a N a cada movimento, para a ordem nunca ficar com buracos ou empates.
 */
class Ordenacao
{
    /** @param  Builder<Model>  $irmaos  consulta já filtrada para o grupo (ex.: aulas do módulo) */
    public static function mover(Builder $irmaos, Model $item, string $sentido): void
    {
        $lista = $irmaos->orderBy('ordem')->orderBy('id')->get()->values();
        $indice = $lista->search(fn (Model $m) => $m->getKey() === $item->getKey());

        if ($indice === false) {
            return;
        }

        $destino = $sentido === 'sobe' ? $indice - 1 : $indice + 1;

        if ($destino >= 0 && $destino < $lista->count()) {
            $troca = $lista[$destino];
            $lista[$destino] = $lista[$indice];
            $lista[$indice] = $troca;
        }

        foreach ($lista as $posicao => $modelo) {
            if ((int) $modelo->ordem !== $posicao + 1) {
                $modelo->update(['ordem' => $posicao + 1]);
            }
        }
    }

    /** Próxima posição ao final do grupo. */
    public static function proxima(Builder $irmaos): int
    {
        return (int) $irmaos->max('ordem') + 1;
    }
}
