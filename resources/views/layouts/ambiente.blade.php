@php($tema = \App\Ambiente\Tema::doParceiro())
@php($nomeAmbiente = $tema->nome($parceiro->nome))
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo', 'Início') · {{ $nomeAmbiente }}</title>
<style>{!! $tema->css() !!}</style>
<link rel="stylesheet" href="{{ asset('css/ambiente.css') }}">
</head>
<body class="ambiente">
<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<aside class="lateral" id="lateral" aria-label="Menu principal">
  <a class="marca" href="{{ route('home') }}">@if ($tema->logoUrl())<img class="marca-logo" src="{{ $tema->logoUrl() }}" alt="{{ $nomeAmbiente }}">@else<span class="marca-sinal" aria-hidden="true"></span><span class="rotulo">{{ $nomeAmbiente }}</span>@endif</a>
  <nav>
    <a href="{{ route('home') }}" @class(['ativo' => request()->routeIs('home')])>@include('ambiente._icone', ['n' => 'casa'])<span class="rotulo">Início</span></a>
    <a href="{{ route('busca') }}" @class(['ativo' => request()->routeIs('busca')])>@include('ambiente._icone', ['n' => 'lupa'])<span class="rotulo">Buscar</span></a>
    <a href="{{ route('conta') }}" @class(['ativo' => request()->routeIs('conta')])>@include('ambiente._icone', ['n' => 'pessoa'])<span class="rotulo">Minha conta</span></a>
  </nav>
  <a class="ajuda" href="mailto:{{ config('uny.suporte_email', 'suporte@faculdadeunypublica.edu.br') }}">@include('ambiente._icone', ['n' => 'ajuda'])<span class="rotulo">Ajuda</span></a>
</aside>

<div class="corpo">
  <header class="topo">
    <a class="marca marca-topo" href="{{ route('home') }}">@if ($tema->logoUrl())<img class="marca-logo" src="{{ $tema->logoUrl() }}" alt="{{ $nomeAmbiente }}">@else<span class="marca-sinal" aria-hidden="true"></span>{{ $nomeAmbiente }}@endif</a>
    <form class="busca-topo" action="{{ route('busca') }}" method="get" role="search">
      <label class="so-leitor" for="q">Buscar cursos e aulas</label>
      <input id="q" name="q" type="search" maxlength="80" placeholder="Buscar cursos e aulas" value="{{ request()->routeIs('busca') ? request('q') : '' }}">
    </form>
    <form action="{{ route('logout') }}" method="post" class="sair">@csrf<button type="submit" class="link">Sair</button></form>
  </header>

  <main id="conteudo" class="@yield('classe')">
    @if ($previa ?? false)<div class="faixa-previa">Prévia: é assim que o aluno verá a home com as suas alterações ainda não publicadas. <a href="{{ url()->previous() }}">Voltar ao editor</a></div>@endif
    @if (session('status'))<div class="aviso" role="status">{{ session('status') }}</div>@endif
    @yield('conteudo')
  </main>
</div>

<nav class="inferior" aria-label="Menu">
  <a href="{{ route('home') }}" @class(['ativo' => request()->routeIs('home')])>@include('ambiente._icone', ['n' => 'casa'])Início</a>
  <a href="{{ route('busca') }}" @class(['ativo' => request()->routeIs('busca')])>@include('ambiente._icone', ['n' => 'lupa'])Buscar</a>
  <a href="{{ route('conta') }}" @class(['ativo' => request()->routeIs('conta')])>@include('ambiente._icone', ['n' => 'pessoa'])Conta</a>
</nav>
<script src="{{ asset('js/ambiente.js') }}" defer></script>
</body>
</html>
