@extends('layouts.app')
@section('titulo', 'Entrar na equipe')
@section('conteudo')
  <h1>Acesso da equipe</h1>
  <p class="suave">Área de gestão de {{ $parceiro->nome }}.</p>
  <form method="post" action="{{ url('/equipe/entrar') }}">
    @csrf
    <label>E-mail
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
    </label>
    <label>Senha
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    @error('email')<div class="erro">{{ $message }}</div>@enderror
    @error('password')<div class="erro">{{ $message }}</div>@enderror
    <button class="principal" type="submit">Entrar</button>
  </form>
@endsection
