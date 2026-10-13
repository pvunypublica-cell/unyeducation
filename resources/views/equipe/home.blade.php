@extends('layouts.painel')
@section('titulo', 'Home e vitrines')
@section('conteudo')
  <div class="cabeca-pagina">
    <h1>Home e vitrines</h1>
    <a class="botao secundario" href="{{ route('equipe.previa') }}" target="_blank" rel="noopener">Ver prévia</a>
  </div>
  <p class="suave">As mudanças ficam em rascunho até você publicar. Os alunos só veem o que foi publicado.</p>

  @if ($pendente)
    <div class="aviso">
      Há alterações ainda não publicadas.
      <span class="acoes">
        <form method="post" action="{{ route('equipe.home.publicar') }}">@csrf<button class="botao" type="submit">Publicar home</button></form>
        <form method="post" action="{{ route('equipe.home.descartar') }}" onsubmit="return confirm('Descartar as alterações não publicadas?')">@csrf<button class="botao secundario" type="submit">Descartar</button></form>
      </span>
    </div>
  @endif

  <section class="cartao">
    <h2>Banner</h2>
    <form class="formulario" method="post" action="{{ route('equipe.home.banner') }}" enctype="multipart/form-data">
      @csrf
      <label>Título <input type="text" name="titulo" value="{{ old('titulo', $banner['titulo'] ?? '') }}" maxlength="80"></label>
      <label>Texto <input type="text" name="texto" value="{{ old('texto', $banner['texto'] ?? '') }}" maxlength="200"></label>
      <label>Texto do botão <input type="text" name="botao_rotulo" value="{{ old('botao_rotulo', $banner['botao_rotulo'] ?? '') }}" maxlength="30"></label>
      <label>O botão leva para
        <select name="botao_tipo">
          @foreach (['busca' => 'A busca de cursos', 'curso' => 'Um curso', 'url' => 'Um endereço externo (https://)', 'nenhum' => 'Sem botão'] as $k => $r)
            <option value="{{ $k }}" @selected(old('botao_tipo', $banner['botao_tipo'] ?? 'busca') === $k)>{{ $r }}</option>
          @endforeach
        </select>
      </label>
      <label>Curso do botão
        <select name="botao_curso_id">
          <option value="">Escolha…</option>
          @foreach ($cursos as $c)<option value="{{ $c->id }}" @selected((int) old('botao_curso_id', $banner['botao_curso_id'] ?? 0) === $c->id)>{{ $c->titulo }}</option>@endforeach
        </select>
        @error('botao_curso_id')<span class="erro">{{ $message }}</span>@enderror
      </label>
      <label>Endereço externo
        <input type="text" name="botao_url" value="{{ old('botao_url', $banner['botao_url'] ?? '') }}" maxlength="300" placeholder="https://">
        @error('botao_url')<span class="erro">{{ $message }}</span>@enderror
      </label>
      <label>Imagem para computador (opcional; JPG, PNG ou WEBP, até 3 MB; sugestão 1600 × 500)
        <input type="file" name="imagem_desktop" accept="image/jpeg,image/png,image/webp">
        @error('imagem_desktop')<span class="erro">{{ $message }}</span>@enderror
      </label>
      @if (! empty($banner['imagem_desktop_id']))<label class="marca-check"><input type="checkbox" name="remover_desktop" value="1"> Remover a imagem do computador</label>@endif
      <label>Imagem para celular (opcional; sugestão 800 × 600)
        <input type="file" name="imagem_mobile" accept="image/jpeg,image/png,image/webp">
        @error('imagem_mobile')<span class="erro">{{ $message }}</span>@enderror
      </label>
      @if (! empty($banner['imagem_mobile_id']))<label class="marca-check"><input type="checkbox" name="remover_mobile" value="1"> Remover a imagem do celular</label>@endif
      <button class="botao" type="submit">Salvar banner</button>
    </form>
  </section>

  <h2>Vitrines</h2>
  @error('vitrines')<p class="erro">{{ $message }}</p>@enderror
  @foreach ($vitrines as $v)
    <section class="cartao">
      <form class="formulario" method="post" action="{{ route('equipe.home.vitrines.atualizar', $v['id']) }}">
        @csrf
        <label>Título da vitrine <input type="text" name="titulo" value="{{ $v['titulo'] ?? '' }}" maxlength="80" required></label>
        <label>O que mostra
          <select name="tipo">@foreach ($tipos as $k => $r)<option value="{{ $k }}" @selected(($v['tipo'] ?? '') === $k)>{{ $r }}</option>@endforeach</select>
        </label>
        <label>Categoria (se for “de uma categoria”)
          <select name="categoria">
            <option value="">Escolha…</option>
            @foreach ($categorias as $c)<option value="{{ $c->slug }}" @selected(($v['categoria'] ?? '') === $c->slug)>{{ $c->nome }}</option>@endforeach
          </select>
        </label>
        <fieldset>
          <legend>Cursos (se for “seleção manual”)</legend>
          @foreach ($cursos as $c)
            <label class="marca-check"><input type="checkbox" name="cursos[]" value="{{ $c->id }}" @checked(in_array($c->id, array_map('intval', (array) ($v['cursos'] ?? []))))> {{ $c->titulo }}</label>
          @endforeach
          @if ($cursos->isEmpty())<small>Ainda não há cursos publicados.</small>@endif
        </fieldset>
        <label>Quantos cursos no máximo <input type="number" name="limite" min="1" max="24" value="{{ (int) ($v['limite'] ?? 12) }}"></label>
        <label class="marca-check"><input type="checkbox" name="ativa" value="1" @checked(($v['ativa'] ?? true) !== false)> Mostrar esta vitrine na home</label>
        @error('vitrine-'.$v['id'])<span class="erro">{{ $message }}</span>@enderror
        <button class="botao secundario" type="submit">Salvar vitrine</button>
      </form>
      <div class="mini-acoes">
        <form method="post" action="{{ route('equipe.home.vitrines.mover', $v['id']) }}">@csrf<input type="hidden" name="sentido" value="sobe"><button class="botao secundario mini" type="submit" aria-label="Subir vitrine {{ $v['titulo'] ?? '' }}" @disabled($loop->first)>↑</button></form>
        <form method="post" action="{{ route('equipe.home.vitrines.mover', $v['id']) }}">@csrf<input type="hidden" name="sentido" value="desce"><button class="botao secundario mini" type="submit" aria-label="Descer vitrine {{ $v['titulo'] ?? '' }}" @disabled($loop->last)>↓</button></form>
        <form method="post" action="{{ route('equipe.home.vitrines.remover', $v['id']) }}" onsubmit="return confirm('Remover esta vitrine?')">@csrf<button class="botao perigo mini" type="submit">Remover</button></form>
      </div>
    </section>
  @endforeach

  <form class="linha-form cartao" method="post" action="{{ route('equipe.home.vitrines.adicionar') }}">
    @csrf
    <label class="so-leitor" for="nv-titulo">Título da nova vitrine</label>
    <input id="nv-titulo" type="text" name="titulo" placeholder="Título da nova vitrine" maxlength="80" required>
    <label class="so-leitor" for="nv-tipo">Tipo</label>
    <select id="nv-tipo" name="tipo">@foreach ($tipos as $k => $r)<option value="{{ $k }}">{{ $r }}</option>@endforeach</select>
    <button class="botao" type="submit">Adicionar vitrine</button>
  </form>
@endsection
