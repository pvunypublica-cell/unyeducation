<?php

namespace App\Http\Controllers;

use App\Ambiente\Vitrines;
use App\Models\Parceiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index(Request $request, Vitrines $vitrines)
    {
        $aluno = Auth::guard('aluno')->user();

        if ($aluno === null) {
            return view('home');
        }

        /** @var Parceiro $parceiro */
        $parceiro = $request->attributes->get('parceiro');

        return view('ambiente.home', $vitrines->montar($aluno, $parceiro));
    }
}
