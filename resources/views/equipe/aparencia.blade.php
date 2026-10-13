@extends('layouts.painel')
@section('titulo', 'Aparência')
@section('classe', 'estreito')
@section('conteudo')
  <div class="cabeca-pagina">
    <h1>Aparência</h1>
    <div class="acoes">
      <a class="botao secundario" href="{{ route('equipe.previa') }}" target="_blank" rel="noopener">Ver prévia</a>
    </div>
  </div>
  <p class="suave">As mudanças ficam em rascunho até você publicar. Os alunos só veem o que foi publicado.</p>

  @if ($pendente)
    <div class="aviso">
      Há alterações ainda não publicadas.
      <span class="acoes">
        <form method="post" action="{{ route('equipe.aparencia.publicar') }}">@csrf<button class="botao" type="submit">Publicar aparência</button></form>
        <form method="post" action="{{ route('equipe.aparencia.descartar') }}" onsubmit="return confirm('Descartar as alterações não publicadas?')">@csrf<button class="botao secundario" type="submit">Descartar</button></form>
      </span>
    </div>
  @endif

  <form class="formulario" method="post" action="{{ route('equipe.aparencia.salvar') }}" enctype="multipart/form-data">
    @csrf
    <label>Nome do ambiente
      <input type="text" name="nome_ambiente" value="{{ old('nome_ambiente', $valores['nome_ambiente']) }}" maxlength="60" placeholder="{{ $parceiro->nome }}">
      <small>Aparece no topo e no menu. Se ficar vazio, usamos “{{ $parceiro->nome }}”.</small>
    </label>

    <fieldset>
      <legend>Logo</legend>
      @if ($logoUrl)<img class="previa-logo" src="{{ $logoUrl }}" alt="Logo atual"><label class="marca-check"><input type="checkbox" name="remover_logo" value="1"> Remover a logo</label>@endif
      <label>Enviar nova logo (JPG, PNG ou WEBP, até 1 MB)
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
        @error('logo')<span class="erro">{{ $message }}</span>@enderror
      </label>
    </fieldset>

    <fieldset>
      <legend>Cores</legend>
      <label>Cor principal (botões, destaques)
        <span class="linha-cor"><input type="color" value="{{ old('cor_principal', $valores['cor_principal']) }}" oninput="this.nextElementSibling.value=this.value.toUpperCase()" aria-label="Escolher cor principal"><input type="text" name="cor_principal" value="{{ old('cor_principal', $valores['cor_principal']) }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required></span>
        @error('cor_principal')<span class="erro">{{ $message }}</span>@enderror
      </label>
      <label>Cor secundária (avisos, favoritos)
        <span class="linha-cor"><input type="color" value="{{ old('cor_secundaria', $valores['cor_secundaria']) }}" oninput="this.nextElementSibling.value=this.value.toUpperCase()" aria-label="Escolher cor secundária"><input type="text" name="cor_secundaria" value="{{ old('cor_secundaria', $valores['cor_secundaria']) }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required></span>
        @error('cor_secundaria')<span class="erro">{{ $message }}</span>@enderror
      </label>
      <small>O fundo do ambiente é escuro (tema Cinema). Cores muito escuras ficam difíceis de ler e o sistema não aceita (contraste mínimo {{ $contrasteMinimo }}).</small>
    </fieldset>

    <fieldset>
      <legend>Letras e cantos</legend>
      <label>Fonte dos títulos
        <select name="fonte_titulo">@foreach ($fontes as $k => $r)<option value="{{ $k }}" @selected(old('fonte_titulo', $valores['fonte_titulo']) === $k)>{{ $r }}</option>@endforeach</select>
      </label>
      <label>Fonte dos textos
        <select name="fonte_texto">@foreach ($fontes as $k => $r)<option value="{{ $k }}" @selected(old('fonte_texto', $valores['fonte_texto']) === $k)>{{ $r }}</option>@endforeach</select>
      </label>
      <label>Cantos dos cartões e botões
        <select name="raio_borda">@foreach ($raios as $k => $r)<option value="{{ $k }}" @selected(old('raio_borda', $valores['raio_borda']) === $k)>{{ $r }}</option>@endforeach</select>
      </label>
    </fieldset>

    <button class="botao" type="submit">Salvar rascunho</button>
  </form>
@endsection
