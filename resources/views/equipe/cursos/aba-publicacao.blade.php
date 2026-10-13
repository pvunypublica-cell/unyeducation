<section class="cartao">
  <h2>Antes de publicar</h2>
  @foreach ($pendencias['bloqueios'] as $b)<p class="pendencia bloqueio">✕ {{ $b }}</p>@endforeach
  @foreach ($pendencias['avisos'] as $a)<p class="pendencia aviso-p">! {{ $a }}</p>@endforeach
  @if ($pendencias['bloqueios'] === [] && $pendencias['avisos'] === [])<p class="pendencia ok">✓ Tudo certo para publicar.</p>@endif
</section>

<div class="acoes">
  @if ($curso->estado !== 'publicado')
    @if (\App\Permissoes\Matriz::permiteSobre(auth('equipe')->user(), 'cursos.publicar', $curso->instrutor_id))
      <form method="post" action="{{ route('equipe.cursos.publicar', $curso->id) }}">@csrf<button class="botao" type="submit" @disabled($pendencias['bloqueios'] !== [])>Publicar curso</button></form>
    @else
      <p class="suave">Você não tem permissão para publicar. Peça a um administrador.</p>
    @endif
  @endif
  @if ($curso->estado === 'publicado' && \App\Permissoes\Matriz::permiteSobre(auth('equipe')->user(), 'cursos.publicar', $curso->instrutor_id))
    <form method="post" action="{{ route('equipe.cursos.arquivar', $curso->id) }}" onsubmit="return confirm('Arquivar o curso? Ele sai do catálogo, mas quem já está matriculado continua com acesso.')">@csrf<button class="botao secundario" type="submit">Arquivar</button></form>
    <form method="post" action="{{ route('equipe.cursos.rascunho', $curso->id) }}">@csrf<button class="botao secundario" type="submit">Voltar para rascunho</button></form>
  @endif
  @if ($curso->estado === 'arquivado' && \App\Permissoes\Matriz::permiteSobre(auth('equipe')->user(), 'cursos.publicar', $curso->instrutor_id))
    <form method="post" action="{{ route('equipe.cursos.publicar', $curso->id) }}">@csrf<button class="botao" type="submit">Publicar de novo</button></form>
  @endif
</div>
