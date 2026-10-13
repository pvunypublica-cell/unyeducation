<?php

namespace App\Http\Controllers;

use App\Ambiente\Busca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuscaController extends Controller
{
    public function index(Request $request, Busca $busca)
    {
        $termo = Busca::normalizar((string) $request->query('q', ''));
        $resultado = $busca->procurar($termo, Auth::guard('aluno')->user());

        return view('ambiente.busca', [
            'termo' => $termo,
            'curtoDemais' => $termo !== '' && mb_strlen($termo) < Busca::MINIMO,
            'cursos' => $resultado['cursos'],
            'aulas' => $resultado['aulas'],
        ]);
    }
}
