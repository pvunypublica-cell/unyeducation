@extends('layouts.app')
@section('titulo', 'Início')
@section('menu')
  @auth('aluno')
    <a href="{{ route('conta') }}">Minha conta</a>
  @else
    <a href="{{ route('login') }}">Entrar</a>
    <a href="{{ route('cadastro') }}">Criar conta</a>
  @endauth
@endsection
@section('conteudo')
  <h1>{{ $parceiro->nome }}</h1>
  <p class="suave">Ambiente de cursos. Em construção: o catálogo chega no próximo marco.</p>
@endsection
