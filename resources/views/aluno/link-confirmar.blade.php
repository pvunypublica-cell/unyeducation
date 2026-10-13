@extends('layouts.app')
@section('titulo', 'Link de acesso')
@section('conteudo')
  @if ($valido)
    <h1>Entrar na sua conta</h1>
    <p class="suave">Confirme para entrar neste dispositivo.</p>
    <form method="post" action="{{ route('link-magico.entrar', ['token' => $token]) }}">
      @csrf
      <button class="principal" type="submit">Entrar</button>
    </form>
  @else
    <h1>Link inválido</h1>
    <p class="suave">Este link expirou ou já foi usado.</p>
    <a class="botao" href="{{ route('login') }}">Pedir um novo link</a>
  @endif
@endsection
