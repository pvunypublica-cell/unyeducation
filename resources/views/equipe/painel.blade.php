@extends('layouts.app')
@section('titulo', 'Painel')
@section('menu')
  <form method="post" action="{{ route('equipe.logout') }}">@csrf<button type="submit">Sair</button></form>
@endsection
@section('conteudo')
  <h1>Painel de {{ $parceiro->nome }}</h1>
  <p class="suave">{{ $membro->nome }} · {{ $membro->papel }}</p>
  <p>As ferramentas de gestão chegam no marco M3.</p>
@endsection
