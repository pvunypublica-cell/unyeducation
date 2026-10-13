<form class="formulario" method="post" action="{{ route('equipe.cursos.atualizar', $curso->id) }}">
  @csrf
  <input type="hidden" name="aba" value="info">
  <label>Título
    <input type="text" name="titulo" value="{{ old('titulo', $curso->titulo) }}" maxlength="160" required>
  </label>
  <label>Descrição
    <textarea name="descricao" rows="6" maxlength="5000">{{ old('descricao', $curso->descricao) }}</textarea>
  </label>
  <fieldset>
    <legend>Categorias</legend>
    @forelse ($categorias as $c)
      <label class="marca-check"><input type="checkbox" name="categorias[]" value="{{ $c->id }}" @checked(in_array($c->id, old('categorias', $selecionadas)))> {{ $c->nome }}</label>
    @empty
      <p class="suave">Ainda não há categorias. @if (\App\Permissoes\Matriz::escopo(\App\Permissoes\Matriz::papelDe($membro ?? auth('equipe')->user()), 'cursos.editar') === 'todos')<a href="{{ route('equipe.categorias') }}">Criar categorias</a>@endif</p>
    @endforelse
  </fieldset>
  @if (\App\Permissoes\Matriz::escopo(\App\Permissoes\Matriz::papelDe(auth('equipe')->user()), 'cursos.editar') === 'todos')
    <label>Instrutor
      <select name="instrutor_id">
        <option value="">Sem instrutor definido</option>
        @foreach ($instrutores as $i)<option value="{{ $i->id }}" @selected((int) old('instrutor_id', $curso->instrutor_id) === $i->id)>{{ $i->nome }}</option>@endforeach
      </select>
    </label>
  @endif
  <fieldset>
    <legend>Vídeo de apresentação (opcional)</legend>
    <label>Provedor
      <select name="trailer_provedor">
        <option value="">Nenhum</option>
        <option value="youtube" @selected(old('trailer_provedor', $curso->trailer_provedor) === 'youtube')>YouTube</option>
        <option value="vimeo" @selected(old('trailer_provedor', $curso->trailer_provedor) === 'vimeo')>Vimeo</option>
      </select>
    </label>
    <label>Endereço do vídeo
      <input type="text" name="trailer_url" value="{{ old('trailer_url', $curso->trailer_ref) }}" placeholder="Cole o endereço da página do vídeo" maxlength="300">
    </label>
  </fieldset>
  <button class="botao" type="submit">Salvar informações</button>
</form>

<form class="formulario cartao" method="post" action="{{ route('equipe.cursos.capa', $curso->id) }}" enctype="multipart/form-data">
  @csrf
  <h2>Capa do curso</h2>
  <p class="suave">Imagem vertical (proporção 2:3, por exemplo 800 × 1200). JPG, PNG ou WEBP, até 2 MB.</p>
  @if ($capa)<img class="previa-capa" src="{{ route('midia', $capa->uuid) }}" alt="Capa atual do curso">@endif
  <label>Nova imagem
    <input type="file" name="capa" accept="image/jpeg,image/png,image/webp" required>
  </label>
  <button class="botao secundario" type="submit">Enviar capa</button>
</form>
