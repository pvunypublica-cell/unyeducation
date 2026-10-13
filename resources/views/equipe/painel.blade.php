@extends('layouts.painel')
@section('titulo', 'Início')
@section('conteudo')
  <h1>Olá, {{ $membro->nome }}</h1>
  <p class="suave">Painel de {{ $parceiro->nome }}</p>

  <div class="indicadores">
    <div class="indicador"><strong>{{ $cursos['publicado'] }}</strong><span>cursos publicados</span></div>
    <div class="indicador"><strong>{{ $cursos['rascunho'] }}</strong><span>em rascunho</span></div>
    <div class="indicador"><strong>{{ $alunos }}</strong><span>alunos</span></div>
    <div class="indicador"><strong>{{ $matriculasRecentes }}</strong><span>matrículas em 30 dias</span></div>
    <div class="indicador"><strong>{{ $concluidas }}</strong><span>conclusões</span></div>
  </div>

  @if ($rascunhosAntigos > 0)
    <div class="aviso">Você tem {{ $rascunhosAntigos }} {{ $rascunhosAntigos === 1 ? 'curso' : 'cursos' }} em rascunho sem alterações há mais de 30 dias.</div>
  @endif

  <p><a class="botao" href="{{ route('equipe.cursos') }}">Ver cursos</a></p>
@endsection
