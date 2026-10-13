@extends('layouts.ambiente')
@section('titulo', $curso->titulo)
@section('classe', 'largo')
@section('conteudo')
@php
  $horas = intdiv($totalSeg, 3600); $mins = intdiv($totalSeg % 3600, 60);
  $duracao = $totalSeg > 0 ? ($horas > 0 ? "{$horas} h {$mins} min" : "{$mins} min") : null;
  $imagem = \App\Ambiente\Capa::url($curso);
  $alvo = $matricula ? $continuar : null;
@endphp
@if ($previa ?? false)<div class="faixa-previa">Prévia: é assim que o aluno vê esta página. Os botões não funcionam aqui.</div>@endif
<a class="voltar" href="{{ route('home') }}">@include('ambiente._icone', ['n' => 'seta'])Início</a>
<section class="heroi">
  <div class="heroi-texto">
    @if ($categorias->isNotEmpty())<span class="etiqueta">{{ $categorias->pluck('nome')->implode(' · ') }}</span>@endif
    <h1>{{ $curso->titulo }}</h1>
    @if ($curso->descricao)<p class="descricao">{{ $curso->descricao }}</p>@endif
    <div class="fatos">
      <span>{{ $totalAulas }} {{ $totalAulas === 1 ? 'aula' : 'aulas' }}</span>
      @if ($duracao)<span>{{ $duracao }}</span>@endif
      @if ($curso->certificado_ativo)<span>Certificado</span>@endif
      @if ($matricula)<span>{{ (int) $matricula->percentual_concluido }}% concluído</span>@endif
    </div>
    <div class="acoes">
      @if ($matricula && $alvo)
        <a class="botao" href="{{ route('aula', $alvo->id) }}">@include('ambiente._icone', ['n' => 'play'])Continuar</a>
      @elseif ($curso->gratuito && $primeira)
        <form method="post" action="{{ route('curso.iniciar', $curso->slug) }}">@csrf<button class="botao" type="submit">@include('ambiente._icone', ['n' => 'play'])Começar agora</button></form>
      @elseif ($previa ?? false)
        {{-- prévia: sem ações --}}
      @elseif (! $matricula)
        <span class="aviso">Este curso ainda não está liberado na sua conta.</span>
      @endif
      @unless ($previa ?? false)
      <form method="post" action="{{ route('curso.favorito', $curso->slug) }}">@csrf
        <button class="botao secundario" type="submit" aria-pressed="{{ $favorito ? 'true' : 'false' }}">@include('ambiente._icone', ['n' => 'coracao']){{ $favorito ? 'Nos favoritos' : 'Favoritar' }}</button>
      </form>
      @endunless
    </div>
  </div>
  <div class="heroi-capa" style="background: {{ \App\Ambiente\Capa::degrade($curso) }}">
    @if ($imagem)<img src="{{ $imagem }}" alt="" width="200" height="300">@else<span class="capa-iniciais" aria-hidden="true">{{ \App\Ambiente\Capa::iniciais($curso) }}</span>@endif
  </div>
</section>

<section class="conteudo-curso" aria-labelledby="h-conteudo">
  <h2 id="h-conteudo">Conteúdo do curso</h2>
  @forelse ($estruturas as $i => $e)
    <details class="modulo" @if ($i === 0 || ($alvo && $alvo->modulo_id === $e['modulo']->id)) open @endif>
      <summary><span>{{ $e['modulo']->titulo }}</span><small>{{ count($e['aulas']) }} {{ count($e['aulas']) === 1 ? 'aula' : 'aulas' }}</small></summary>
      <ul>
        @foreach ($e['aulas'] as $item)
          @php($a = $item['aula'])
          <li>
            @if ($matricula && $item['estado'] === \App\Aprendizado\Liberacao::DISPONIVEL)
              <a href="{{ route('aula', $a->id) }}" @class(['feita' => $item['feita']])>
                @include('ambiente._icone', ['n' => $item['feita'] ? 'check' : 'play'])
                <span>{{ $a->titulo }}</span>
                @if ($a->duracao_seg)<small>{{ max(1, intdiv($a->duracao_seg, 60)) }} min</small>@endif
              </a>
            @else
              <span class="travada">
                @include('ambiente._icone', ['n' => 'cadeado'])<span>{{ $a->titulo }}</span>
                @if ($matricula && $item['estado'] !== \App\Aprendizado\Liberacao::DISPONIVEL)<small>Em breve</small>@endif
              </span>
            @endif
          </li>
        @endforeach
      </ul>
    </details>
  @empty
    <p class="vazio">As aulas deste curso ainda não foram publicadas.</p>
  @endforelse
</section>
@endsection
