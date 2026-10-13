<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo', 'Início') · {{ $parceiro->nome }}</title>
<style>
  :root { --fundo:#0d0f12; --painel:#161a1f; --linha:#232830; --texto:#eef0f3; --suave:#9aa3ad; --destaque:#f0b44c; }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--fundo); color:var(--texto); font-family:system-ui,-apple-system,"Segoe UI",sans-serif; line-height:1.5; }
  header { padding:16px 24px; border-bottom:1px solid var(--linha); display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
  header a.marca { font-weight:800; font-size:20px; color:var(--texto); text-decoration:none; }
  header nav { margin-left:auto; display:flex; gap:16px; align-items:center; }
  header nav a, header nav button { color:var(--suave); text-decoration:none; background:none; border:0; font:inherit; cursor:pointer; min-height:44px; }
  main { max-width:440px; margin:48px auto; padding:0 20px; }
  main.largo { max-width:960px; }
  h1 { font-size:28px; margin:0 0 8px; }
  p.suave { color:var(--suave); margin:0 0 24px; }
  form { display:flex; flex-direction:column; gap:14px; }
  label { display:flex; flex-direction:column; gap:6px; font-size:14px; color:var(--suave); }
  input[type=email], input[type=password], input[type=text] { min-height:44px; padding:10px 12px; border-radius:10px; border:1px solid var(--linha); background:var(--painel); color:var(--texto); font:inherit; }
  button.principal, a.botao { min-height:44px; padding:10px 18px; border-radius:10px; border:0; background:var(--destaque); color:#1a1204; font-weight:700; font:inherit; font-weight:700; cursor:pointer; text-align:center; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
  .erro { color:#ff8a7a; font-size:14px; }
  .aviso { background:var(--painel); border:1px solid var(--linha); padding:12px 14px; border-radius:10px; margin-bottom:16px; }
  hr { border:0; border-top:1px solid var(--linha); margin:24px 0; }
  small { color:var(--suave); }
</style>
</head>
<body>
<header>
  <a class="marca" href="{{ route('home') }}">{{ $parceiro->nome }}</a>
  <nav>@yield('menu')</nav>
</header>
<main class="@yield('classe')">
  @if (session('status'))<div class="aviso">{{ session('status') }}</div>@endif
  @yield('conteudo')
</main>
</body>
</html>
