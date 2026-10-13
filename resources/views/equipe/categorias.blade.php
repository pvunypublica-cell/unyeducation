@extends('layouts.painel')
@section('titulo', 'Categorias')
@section('classe', 'estreito')
@section('conteudo')
  <h1>Categorias</h1>
  <ul class="lista-edicao">
    @forelse ($categorias as $c)
      <li>
        <span class="titulo-aula">{{ $c->nome }} <small>({{ $c->cursos_count }} {{ $c->cursos_count === 1 ? 'curso' : 'cursos' }})</small></span>
        <span class="selo {{ $c->ativa ? 'selo-publicado' : 'selo-arquivado' }}">{{ $c->ativa ? 'Ativa' : 'Oculta' }}</span>
        <form method="post" action="{{ route('equipe.categorias.alternar', $c->id) }}">@csrf<button class="botao secundario mini" type="submit">{{ $c->ativa ? 'Ocultar' : 'Mostrar' }}</button></form>
      </li>
    @empty
      <li class="vazio">Nenhuma categoria ainda.</li>
    @endforelse
  </ul>
  <form class="linha-form cartao" method="post" action="{{ route('equipe.categorias.salvar') }}">
    @csrf
    <label class="so-leitor" for="nome-cat">Nome da categoria</label>
    <input id="nome-cat" type="text" name="nome" placeholder="Nome da nova categoria" maxlength="100" required>
    <button class="botao" type="submit">Criar categoria</button>
  </form>
@endsection
