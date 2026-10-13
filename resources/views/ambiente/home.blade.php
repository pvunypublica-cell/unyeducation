@extends('layouts.ambiente')
@section('titulo', 'Início')
@section('conteudo')
  <section class="banner">
    <div class="banner-texto">
      <h1>{{ $banner['titulo'] ?? $parceiro->nome }}</h1>
      <p>{{ $banner['texto'] ?? '' }}</p>
      <a class="botao" href="{{ route('busca') }}">{{ $banner['botao_rotulo'] ?? 'Explorar cursos' }}</a>
    </div>
  </section>

  @if (count($continuar))
    <section class="vitrine" aria-labelledby="v-continuar">
      <h2 id="v-continuar">Continuar assistindo</h2>
      <div class="trilho">
        @foreach ($continuar as $item)
          <a class="card-continuar" href="{{ route('aula', $item['aula']->id) }}">
            <span class="capa-h" style="background: {{ \App\Ambiente\Capa::degrade($item['curso']) }}">
              <span class="play" aria-hidden="true">@include('ambiente._icone', ['n' => 'play'])</span>
              <span class="barra" role="progressbar" aria-valuenow="{{ (int) $item['percentual'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso"><span style="width: {{ (int) $item['percentual'] }}%"></span></span>
            </span>
            <span class="card-titulo">{{ $item['curso']->titulo }}</span>
            <span class="card-meta">{{ $item['aula']->titulo }}</span>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  @forelse ($vitrines as $i => $vitrine)
    <section class="vitrine" aria-labelledby="v-{{ $i }}">
      <h2 id="v-{{ $i }}">{{ $vitrine['titulo'] }}</h2>
      <div class="trilho">
        @foreach ($vitrine['cards'] as $card)@include('ambiente._card', ['card' => $card])@endforeach
      </div>
    </section>
  @empty
    @if (! count($continuar))
      <p class="vazio">Ainda não há cursos publicados por aqui. Volte em breve.</p>
    @endif
  @endforelse
@endsection
