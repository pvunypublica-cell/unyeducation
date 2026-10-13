@php
  /** @var \App\Models\Curso $curso */
  $curso = $card['curso'];
  $imagem = \App\Ambiente\Capa::url($curso);
@endphp
<a class="card" href="{{ route('curso', $curso->slug) }}">
  <span class="capa" style="background: {{ \App\Ambiente\Capa::degrade($curso) }}">
    @if ($imagem)<img src="{{ $imagem }}" alt="" loading="lazy" width="200" height="300">
    @else<span class="capa-iniciais" aria-hidden="true">{{ \App\Ambiente\Capa::iniciais($curso) }}</span>@endif
    @if ($card['favorito'])<span class="selo-fav" title="Favorito">@include('ambiente._icone', ['n' => 'coracao'])</span>@endif
    @if ($card['percentual'] !== null)
      <span class="barra" role="progressbar" aria-valuenow="{{ $card['percentual'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso"><span style="width: {{ (int) $card['percentual'] }}%"></span></span>
    @endif
  </span>
  <span class="card-titulo">{{ $curso->titulo }}</span>
  <span class="card-meta">
    @if ($card['percentual'] !== null){{ (int) $card['percentual'] }}% concluído
    @elseif ($curso->gratuito)Gratuito
    @else Curso @endif
  </span>
</a>
