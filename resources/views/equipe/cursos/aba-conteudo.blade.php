@forelse ($modulos as $modulo)
  <section class="cartao modulo-edicao">
    <div class="modulo-cab">
      <form method="post" action="{{ route('equipe.modulos.atualizar', $modulo->id) }}" class="linha-form">
        @csrf
        <label class="so-leitor" for="m{{ $modulo->id }}">Nome do módulo</label>
        <input id="m{{ $modulo->id }}" type="text" name="titulo" value="{{ $modulo->titulo }}" maxlength="160" required>
        <button class="botao secundario" type="submit">Renomear</button>
      </form>
      <div class="mini-acoes">
        <form method="post" action="{{ route('equipe.modulos.mover', $modulo->id) }}">@csrf<input type="hidden" name="sentido" value="sobe"><button class="botao secundario mini" type="submit" aria-label="Subir módulo {{ $modulo->titulo }}" @disabled($loop->first)>↑</button></form>
        <form method="post" action="{{ route('equipe.modulos.mover', $modulo->id) }}">@csrf<input type="hidden" name="sentido" value="desce"><button class="botao secundario mini" type="submit" aria-label="Descer módulo {{ $modulo->titulo }}" @disabled($loop->last)>↓</button></form>
        <form method="post" action="{{ route('equipe.modulos.apagar', $modulo->id) }}" onsubmit="return confirm('Apagar este módulo?')">@csrf<button class="botao perigo mini" type="submit">Apagar</button></form>
      </div>
    </div>

    <ul class="lista-edicao">
      @foreach ($aulas->get($modulo->id, collect()) as $aula)
        <li>
          <a class="titulo-aula" href="{{ route('equipe.aulas.editar', $aula->id) }}">{{ $aula->titulo }}</a>
          <span class="selo {{ $aula->publicada ? 'selo-publicado' : 'selo-rascunho' }}">{{ $aula->publicada ? 'Publicada' : 'Rascunho' }}</span>
          <span class="mini-acoes">
            <form method="post" action="{{ route('equipe.aulas.mover', $aula->id) }}">@csrf<input type="hidden" name="sentido" value="sobe"><button class="botao secundario mini" type="submit" aria-label="Subir aula {{ $aula->titulo }}" @disabled($loop->first)>↑</button></form>
            <form method="post" action="{{ route('equipe.aulas.mover', $aula->id) }}">@csrf<input type="hidden" name="sentido" value="desce"><button class="botao secundario mini" type="submit" aria-label="Descer aula {{ $aula->titulo }}" @disabled($loop->last)>↓</button></form>
          </span>
        </li>
      @endforeach
    </ul>

    <form method="post" action="{{ route('equipe.aulas.salvar', $modulo->id) }}" class="linha-form">
      @csrf
      <label class="so-leitor" for="na{{ $modulo->id }}">Título da nova aula</label>
      <input id="na{{ $modulo->id }}" type="text" name="titulo" placeholder="Título da nova aula" maxlength="160" required>
      <button class="botao" type="submit">Adicionar aula</button>
    </form>
  </section>
@empty
  <p class="vazio">Este curso ainda não tem módulos. Crie o primeiro abaixo.</p>
@endforelse

<form method="post" action="{{ route('equipe.modulos.salvar', $curso->id) }}" class="linha-form cartao">
  @csrf
  <label class="so-leitor" for="novo-modulo">Nome do novo módulo</label>
  <input id="novo-modulo" type="text" name="titulo" placeholder="Nome do novo módulo" maxlength="160" required>
  <button class="botao" type="submit">Adicionar módulo</button>
</form>
