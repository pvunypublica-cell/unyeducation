<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo', 'Painel') · {{ $parceiro->nome }}</title>
<style>{!! \App\Ambiente\Tema::doParceiro()->css() !!}</style>
<link rel="stylesheet" href="{{ asset('css/ambiente.css') }}">
<link rel="stylesheet" href="{{ asset('css/painel.css') }}">
</head>
<body class="ambiente painel">
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<aside class="lateral" aria-label="Menu do painel">
  <a class="marca" href="{{ route('equipe.painel') }}"><span class="marca-sinal" aria-hidden="true"></span><span class="rotulo">{{ $parceiro->nome }}</span></a>
  <nav>
    <a href="{{ route('equipe.painel') }}" @class(['ativo' => request()->routeIs('equipe.painel')])><span class="rotulo">Início</span></a>
    @if (\App\Permissoes\Matriz::permite(auth('equipe')->user(), 'cursos.editar'))
      <a href="{{ route('equipe.cursos') }}" @class(['ativo' => request()->routeIs('equipe.cursos*', 'equipe.aulas*')])><span class="rotulo">Cursos</span></a>
      @if (\App\Permissoes\Matriz::escopo(\App\Permissoes\Matriz::papelDe(auth('equipe')->user()), 'cursos.editar') === 'todos')
        <a href="{{ route('equipe.categorias') }}" @class(['ativo' => request()->routeIs('equipe.categorias*')])><span class="rotulo">Categorias</span></a>
      @endif
    @endif
    @if (\App\Permissoes\Matriz::permite(auth('equipe')->user(), 'aparencia.configurar'))
      <a href="{{ route('equipe.home') }}" @class(['ativo' => request()->routeIs('equipe.home*')])><span class="rotulo">Home e vitrines</span></a>
      <a href="{{ route('equipe.aparencia') }}" @class(['ativo' => request()->routeIs('equipe.aparencia*')])><span class="rotulo">Aparência</span></a>
    @endif
    <a href="{{ route('home') }}" target="_blank" rel="noopener"><span class="rotulo">Ver o ambiente do aluno ↗</span></a>
  </nav>
  <form class="sair-lateral" action="{{ route('equipe.logout') }}" method="post">@csrf<button type="submit" class="link">Sair</button></form>
</aside>
<div class="corpo">
  <main id="conteudo" class="@yield('classe')">
    @if (session('status'))<div class="aviso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="aviso erro-caixa" role="alert">@foreach ($errors->all() as $erro)<div>{{ $erro }}</div>@endforeach</div>@endif
    @yield('conteudo')
  </main>
</div>
</body>
</html>
