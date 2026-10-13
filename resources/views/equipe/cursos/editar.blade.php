@extends('layouts.painel')
@section('titulo', $curso->titulo)
@section('conteudo')
  <a class="voltar" href="{{ route('equipe.cursos') }}">← Cursos</a>
  <div class="cabeca-pagina">
    <div>
      <h1>{{ $curso->titulo }}</h1>
      <span class="selo selo-{{ $curso->estado }}">{{ ['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'arquivado' => 'Arquivado'][$curso->estado] }}</span>
    </div>
    <a class="botao secundario" href="{{ route('equipe.cursos.previa', $curso->id) }}" target="_blank" rel="noopener">Ver como aluno</a>
  </div>

  <nav class="abas" aria-label="Seções do curso">
    @foreach ($abas as $chave => $rotulo)
      <a href="{{ route('equipe.cursos.editar', [$curso->id, 'aba' => $chave]) }}" @class(['ativa' => $aba === $chave]) @if ($aba === $chave) aria-current="page" @endif>{{ $rotulo }}</a>
    @endforeach
  </nav>

  @include('equipe.cursos.aba-'.$aba)
@endsection
