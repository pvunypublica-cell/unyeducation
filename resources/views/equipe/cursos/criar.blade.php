@extends('layouts.painel')
@section('titulo', 'Novo curso')
@section('classe', 'estreito')
@section('conteudo')
  <a class="voltar" href="{{ route('equipe.cursos') }}">← Cursos</a>
  <h1>Novo curso</h1>
  <p class="suave">Comece pelo título. O resto você completa depois, e o curso fica em rascunho até você publicar.</p>
  <form class="formulario" method="post" action="{{ route('equipe.cursos.salvar') }}">
    @csrf
    <label>Título do curso
      <input type="text" name="titulo" value="{{ old('titulo') }}" maxlength="160" required autofocus>
    </label>
    <button class="botao" type="submit">Criar curso</button>
  </form>
@endsection
