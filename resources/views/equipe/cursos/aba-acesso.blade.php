<form class="formulario" method="post" action="{{ route('equipe.cursos.atualizar', $curso->id) }}">
  @csrf
  <input type="hidden" name="aba" value="acesso">
  <label class="marca-check"><input type="checkbox" name="gratuito" value="1" @checked($curso->gratuito)> Curso gratuito (qualquer aluno cadastrado pode começar sozinho)</label>
  <label class="marca-check"><input type="checkbox" name="certificado_ativo" value="1" @checked($curso->certificado_ativo)> Este curso emite certificado</label>
  <p class="suave">Cursos que não são gratuitos só liberam acesso com matrícula. A venda e as matrículas chegam nos próximos marcos.</p>
  <button class="botao" type="submit">Salvar acesso</button>
</form>
