@extends('layouts.app')
@section('titulo', 'Entrar')
@section('conteudo')
  <h1>Entrar</h1>
  <p class="suave">Use seu e-mail e senha, ou peça um link de acesso.</p>
  <form method="post" action="{{ url('/entrar') }}">
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
  <hr>
  <form method="post" action="{{ route('link-magico.solicitar') }}">
    @csrf
    <label>Receber um link de acesso por e-mail
      <input type="email" name="email" autocomplete="email" required>
    </label>
    <button class="principal" type="submit">Enviar link</button>
  </form>
  <p><small>Ainda não tem conta? <a href="{{ route('cadastro') }}" style="color:inherit">Criar conta</a></small></p>
@endsection
