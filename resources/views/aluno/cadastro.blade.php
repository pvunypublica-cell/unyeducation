@extends('layouts.app')
@section('titulo', 'Criar conta')
@section('conteudo')
  <h1>Criar conta</h1>
  <p class="suave">Leva menos de um minuto.</p>
  <form method="post" action="{{ url('/cadastro') }}">
    @csrf
    <label>Nome completo
      <input type="text" name="nome" value="{{ old('nome') }}" autocomplete="name" required>
    </label>
    <label>E-mail
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
    </label>
    <label>Senha (mínimo 8 caracteres)
      <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <label>Repita a senha
      <input type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>
    @foreach ($errors->all() as $erro)<div class="erro">{{ $erro }}</div>@endforeach
    <button class="principal" type="submit">Criar conta</button>
  </form>
@endsection
