@extends('layouts.ambiente')
@section('titulo', $aula->titulo)
@section('classe', 'sala')
@section('conteudo')
<a class="voltar" href="{{ route('curso', $curso->slug) }}">@include('ambiente._icone', ['n' => 'seta']){{ $curso->titulo }}</a>
<div class="sala-grade">
  <section class="sala-principal">
    <div class="player" id="player"
         data-posicao-url="{{ route('aula.posicao', $aula->id) }}"
         data-inicio="{{ (int) ($registro->posicao_seg ?? 0) }}"
         data-duracao="{{ (int) $aula->duracao_seg }}">
      @if ($embed)
        <iframe src="{{ $embed }}" title="{{ $aula->titulo }}" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
      @elseif ($aula->tipo === 'texto')
        <div class="texto-aula">{!! nl2br(e($aula->conteudo_texto ?? '')) !!}</div>
      @else
        <div class="sem-video"><span>@include('ambiente._icone', ['n' => 'play'])</span><p>O vídeo desta aula ainda não foi configurado.</p></div>
      @endif
    </div>

    <div class="aula-cabeca">
      <div>
        <h1>{{ $aula->titulo }}</h1>
        <p class="suave">{{ $moduloAtual?->titulo }} · {{ (int) $matricula->percentual_concluido }}% do curso</p>
      </div>
      <div class="acoes">
        @if ($anterior)<a class="botao secundario" href="{{ route('aula', $anterior->id) }}">Aula anterior</a>@endif
        @if ($feita)
          <form method="post" action="{{ route('aula.concluir', $aula->id) }}">@csrf<input type="hidden" name="desfazer" value="1"><button class="botao secundario" type="submit">Marcar como não vista</button></form>
          @if ($seguinte)<a class="botao" href="{{ route('aula', $seguinte->id) }}">Próxima aula</a>@endif
        @else
          <form method="post" action="{{ route('aula.concluir', $aula->id) }}">@csrf<button class="botao" type="submit">{{ $seguinte ? 'Concluir e seguir' : 'Concluir curso' }}</button></form>
        @endif
      </div>
    </div>

    @if ($materiais->isNotEmpty())
      <section class="caixa" aria-labelledby="h-mat">
        <h2 id="h-mat">Material da aula</h2>
        <ul class="materiais">
          @foreach ($materiais as $m)
            <li><a href="{{ route('material', $m->id) }}">@include('ambiente._icone', ['n' => 'arquivo'])<span>{{ $m->titulo }}</span><small>{{ number_format($m->arquivo->tamanho_bytes / 1024, 0, ',', '.') }} KB</small></a></li>
          @endforeach
        </ul>
      </section>
    @endif
  </section>

  <aside class="sala-lista caixa" aria-label="Aulas do curso">
    <div class="sala-lista-topo">Aulas do curso</div>
    @foreach ($estruturas as $e)
      <details class="modulo" @if ($e['modulo']->id === $aula->modulo_id) open @endif>
        <summary><span>{{ $e['modulo']->titulo }}</span></summary>
        <ul>
          @foreach ($e['aulas'] as $item)
            @php($a = $item['aula'])
            <li>
              @if ($item['estado'] === \App\Aprendizado\Liberacao::DISPONIVEL)
                <a href="{{ route('aula', $a->id) }}" @class(['feita' => $item['feita'], 'atual' => $a->id === $aula->id]) @if ($a->id === $aula->id) aria-current="page" @endif>
                  @include('ambiente._icone', ['n' => $item['feita'] ? 'check' : 'play'])<span>{{ $a->titulo }}</span>
                </a>
              @else
                <span class="travada">@include('ambiente._icone', ['n' => 'cadeado'])<span>{{ $a->titulo }}</span><small>Em breve</small></span>
              @endif
            </li>
          @endforeach
        </ul>
      </details>
    @endforeach
  </aside>
</div>
@endsection
