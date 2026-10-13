@extends('layouts.painel')
@section('titulo', 'Cursos')
@section('conteudo')
  <div class="cabeca-pagina">
    <h1>Cursos</h1>
    <a class="botao" href="{{ route('equipe.cursos.criar') }}">Novo curso</a>
  </div>

  <form class="filtros" method="get" action="{{ route('equipe.cursos') }}">
    <label>Status
      <select name="estado" onchange="this.form.submit()">
        <option value="">Todos</option>
        @foreach (['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'arquivado' => 'Arquivado'] as $valor => $rotulo)
          <option value="{{ $valor }}" @selected($estado === $valor)>{{ $rotulo }}</option>
        @endforeach
      </select>
    </label>
    <label>Categoria
      <select name="categoria" onchange="this.form.submit()">
        <option value="">Todas</option>
        @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected($categoriaId === $c->id)>{{ $c->nome }}</option>@endforeach
      </select>
    </label>
    <noscript><button class="botao secundario" type="submit">Filtrar</button></noscript>
  </form>

  @forelse ($cursos as $curso)
    @if ($loop->first)
      <div class="tabela" role="table" aria-label="Cursos">
        <div class="linha cab" role="row"><span role="columnheader">Curso</span><span role="columnheader">Status</span><span role="columnheader">Aulas</span><span role="columnheader">Alunos</span><span role="columnheader">Editado</span></div>
    @endif
        <a class="linha" role="row" href="{{ route('equipe.cursos.editar', $curso->id) }}">
          <span role="cell"><strong>{{ $curso->titulo }}</strong><small>{{ $curso->categorias->pluck('nome')->implode(', ') ?: 'Sem categoria' }}</small></span>
          <span role="cell"><span class="selo selo-{{ $curso->estado }}">{{ ['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'arquivado' => 'Arquivado'][$curso->estado] }}</span></span>
          <span role="cell">{{ $curso->aulas_count }}</span>
          <span role="cell">{{ $curso->alunos }}</span>
          <span role="cell">{{ $curso->atualizado_em?->timezone(\App\Http\Controllers\Equipe\AulaController::FUSO)->format('d/m/Y H:i') }}</span>
        </a>
    @if ($loop->last)</div>@endif
  @empty
    <p class="vazio">Nenhum curso por aqui ainda. Clique em “Novo curso” para começar.</p>
  @endforelse
@endsection
