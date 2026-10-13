// Ambiente do aluno: só o que precisa de JavaScript. Sem dependências.
(function () {
  'use strict';

  // Guarda "onde parei" enquanto a aula está aberta, usando o tempo com a página visível.
  // Isto NÃO conclui aula: o percentual assistido só será enviado quando o M5 ligar os eventos
  // reais do player. Por ora a conclusão é pelo botão "Concluir e seguir".
  var player = document.getElementById('player');
  if (!player || !player.dataset.posicaoUrl) { return; }

  var token = document.querySelector('input[name="_token"]');
  var csrf = token ? token.value : '';
  var inicio = parseInt(player.dataset.inicio || '0', 10);
  var duracao = parseInt(player.dataset.duracao || '0', 10);
  var decorrido = 0;
  var ultimoEnvio = -1;

  function enviar() {
    var posicao = inicio + decorrido;
    if (posicao === ultimoEnvio) { return; }
    ultimoEnvio = posicao;
    var corpo = new URLSearchParams({ posicao: String(Math.min(posicao, duracao > 0 ? duracao : posicao)), percentual: '0', _token: csrf });
    fetch(player.dataset.posicaoUrl, {
      method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: corpo
    }).catch(function () { /* sem rede: tenta no próximo ciclo */ });
  }

  setInterval(function () { if (!document.hidden) { decorrido += 1; } }, 1000);
  setInterval(function () { if (!document.hidden && decorrido > 0) { enviar(); } }, 20000);
  window.addEventListener('pagehide', function () { if (decorrido > 0) { enviar(); } });
})();
