@extends('layouts.painel')
@section('titulo', $aula->titulo)
@section('classe', 'estreito')
@section('conteudo')
  <a class="voltar" href="{{ route('equipe.cursos.editar', [$curso->id, 'aba' => 'conteudo']) }}">← {{ $curso->titulo }}</a>
  <div class="cabeca-pagina">
    <div>
      <h1>{{ $aula->titulo }}</h1>
      <span class="suave">{{ $modulo?->titulo }}</span>
      <span class="selo {{ $aula->publicada ? 'selo-publicado' : 'selo-rascunho' }}">{{ $aula->publicada ? 'Publicada' : 'Rascunho' }}</span>
    </div>
    <form method="post" action="{{ route('equipe.aulas.publicar', $aula->id) }}">@csrf
      <input type="hidden" name="publicada" value="{{ $aula->publicada ? 0 : 1 }}">
      <button class="botao {{ $aula->publicada ? 'secundario' : '' }}" type="submit">{{ $aula->publicada ? 'Voltar para rascunho' : 'Publicar aula' }}</button>
    </form>
  </div>

  <form class="formulario" method="post" action="{{ route('equipe.aulas.atualizar', $aula->id) }}">
    @csrf
    <label>Título
      <input type="text" name="titulo" value="{{ old('titulo', $aula->titulo) }}" maxlength="160" required>
    </label>
    <label>Tipo de aula
      <select name="tipo">
        <option value="video" @selected(old('tipo', $aula->tipo) === 'video')>Vídeo</option>
        <option value="texto" @selected(old('tipo', $aula->tipo) === 'texto')>Texto</option>
      </select>
    </label>
    <fieldset>
      <legend>Vídeo (para aulas em vídeo)</legend>
      <label>Provedor
        <select name="video_provedor">
          <option value="">Nenhum</option>
          <option value="youtube" @selected(old('video_provedor', $aula->video_provedor) === 'youtube')>YouTube</option>
          <option value="vimeo" @selected(old('video_provedor', $aula->video_provedor) === 'vimeo')>Vimeo</option>
        </select>
      </label>
      <label>Endereço do vídeo
        <input type="text" name="video_url" value="{{ old('video_url', $aula->video_ref) }}" placeholder="Cole o endereço da página do vídeo" maxlength="300">
        @error('video_url')<span class="erro">{{ $message }}</span>@enderror
      </label>
      <label>Duração (minutos)
        <input type="number" name="duracao_min" min="0" max="1440" value="{{ old('duracao_min', $aula->duracao_seg ? intdiv($aula->duracao_seg, 60) : '') }}">
      </label>
    </fieldset>
    <label>Conteúdo em texto (para aulas em texto)
      <textarea name="conteudo_texto" rows="10" maxlength="100000">{{ old('conteudo_texto', $aula->conteudo_texto) }}</textarea>
    </label>
    <fieldset>
      <legend>Quando o aluno pode abrir esta aula</legend>
      <label>Regra
        <select name="liberacao_tipo">
          <option value="imediata" @selected(old('liberacao_tipo', $aula->liberacao_tipo) === 'imediata')>Assim que o aluno entrar no curso</option>
          <option value="data" @selected(old('liberacao_tipo', $aula->liberacao_tipo) === 'data')>A partir de uma data</option>
          <option value="apos_aula" @selected(old('liberacao_tipo', $aula->liberacao_tipo) === 'apos_aula')>Depois de concluir outra aula</option>
        </select>
      </label>
      <label>Data e hora ({{ $fuso }})
        <input type="datetime-local" name="liberacao_data" value="{{ old('liberacao_data', $dataLocal) }}">
        @error('liberacao_data')<span class="erro">{{ $message }}</span>@enderror
      </label>
      <label>Aula que precisa ser concluída antes
        <select name="liberacao_aula_id">
          <option value="">Escolha…</option>
          @foreach ($outras as $o)<option value="{{ $o->id }}" @selected((int) old('liberacao_aula_id', $aula->liberacao_aula_id) === $o->id)>{{ $o->titulo }}</option>@endforeach
        </select>
        @error('liberacao_aula_id')<span class="erro">{{ $message }}</span>@enderror
      </label>
    </fieldset>
    <button class="botao" type="submit">Salvar aula</button>
  </form>

  <section class="cartao">
    <h2>Materiais da aula</h2>
    <ul class="lista-edicao">
      @forelse ($materiais as $m)
        <li>
          <span class="titulo-aula">{{ $m->titulo }} <small>{{ $m->arquivo?->nome_original }}</small></span>
          <form method="post" action="{{ route('equipe.materiais.apagar', $m->id) }}" onsubmit="return confirm('Remover este material?')">@csrf<button class="botao perigo mini" type="submit">Remover</button></form>
        </li>
      @empty
        <li class="vazio">Nenhum material anexado.</li>
      @endforelse
    </ul>
    <form class="formulario" method="post" action="{{ route('equipe.materiais.enviar', $aula->id) }}" enctype="multipart/form-data">
      @csrf
      <label>Nome que o aluno vê (opcional)
        <input type="text" name="titulo" maxlength="160">
      </label>
      <label>Arquivo (até 20 MB)
        <input type="file" name="arquivo" required>
      </label>
      <button class="botao secundario" type="submit">Enviar material</button>
    </form>
  </section>

  <form class="cartao" method="post" action="{{ route('equipe.aulas.apagar', $aula->id) }}" onsubmit="return confirm('Apagar esta aula?')">
    @csrf
    <button class="botao perigo" type="submit">Apagar aula</button>
  </form>
@endsection
