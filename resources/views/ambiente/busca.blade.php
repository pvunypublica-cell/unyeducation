@extends('layouts.ambiente')
@section('titulo', 'Buscar')
@section('conteudo')
  <h1>Buscar</h1>
  @if ($termo === '')
    <p class="suave">Digite o nome de um curso ou de uma aula.</p>
  @elseif ($curtoDemais)
    <p class="suave">Digite ao menos {{ \App\Ambiente\Busca::MINIMO }} letras.</p>
  @else
    <p class="suave">Resultados para “{{ $termo }}”</p>
    @if ($cursos->isEmpty() && $aulas->isEmpty())
      <p class="vazio">Nada encontrado. Tente outra palavra.</p>
    @endif
    @if ($cursos->isNotEmpty())
      <h2>Cursos</h2>
      <div class="grade">
        @foreach ($cursos as $curso)
          @include('ambiente._card', ['card' => ['curso' => $curso, 'percentual' => null, 'favorito' => false, 'incluido' => $curso->gratuito]])
        @endforeach
      </div>
    @endif
    @if ($aulas->isNotEmpty())
      <h2>Aulas dos seus cursos</h2>
      <ul class="lista-aulas">
        @foreach ($aulas as $aula)
          <li><a href="{{ route('aula', $aula->id) }}"><span>{{ $aula->titulo }}</span><small>{{ $aula->curso->titulo }}</small></a></li>
        @endforeach
      </ul>
    @endif
  @endif
@endsection
