@extends('layouts.app')
@section('titulo', 'Minha conta')
@section('menu')
  <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sair</button></form>
@endsection
@section('conteudo')
  <h1>Olá, {{ $aluno->nome }}</h1>
  <p class="suave">{{ $aluno->email }}</p>
  <p>Seus cursos aparecerão aqui.</p>
@endsection
