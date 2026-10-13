<?php

namespace Tests\Feature\Acesso;

use App\Models\Operador;
use App\Permissoes\Matriz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

/**
 * Matriz de permissões da UNY-DOC-003, seção 3.2, transcrita aqui de forma independente.
 * Instrutor "(os seus)" aparece como true; a checagem de dono entra com os cursos (M2).
 */
class PermissoesTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    /** @return array<string, array<string, bool>> ação => [papel => permitido] */
    private function esperado(): array
    {
        $o = 'operador';
        $a = 'administrador';
        $i = 'instrutor';
        $s = 'suporte';
        $l = 'aluno';

        $linha = fn (array $quem) => [
            $o => in_array($o, $quem), $a => in_array($a, $quem), $i => in_array($i, $quem),
            $s => in_array($s, $quem), $l => in_array($l, $quem),
        ];

        return [
            'parceiros.gerenciar' => $linha([$o]),
            'aparencia.configurar' => $linha([$a]),
            'integracoes.configurar' => $linha([$a]),
            'equipe.gerenciar' => $linha([$a]),
            'cursos.editar' => $linha([$a, $i]),
            'cursos.publicar' => $linha([$a, $i]),
            'alunos.ver' => $linha([$a, $i, $s]),
            'matriculas.criar_manual' => $linha([$a, $s]),
            'certificados.revogar' => $linha([$a]),
            'tarefas.corrigir' => $linha([$a, $i]),
            'comentarios.moderar' => $linha([$a, $i, $s]),
            'relatorios.ver' => $linha([$a, $i]),
            'cursos.assistir' => $linha([$l]),
            'suporte.acessar_parceiro' => $linha([$o]),
        ];
    }

    private function usuario(string $papel, $parceiro): object
    {
        return match ($papel) {
            'operador' => Operador::create([
                'nome' => 'Op', 'email' => 'op@exemplo.com', 'senha_hash' => 'x', 'papel' => 'administrador', 'ativo' => true,
            ]),
            'aluno' => $this->criarAluno($parceiro),
            // Instrutor com permissão de publicar, para testar a matriz sem a ressalva.
            'instrutor' => $this->criarMembro($parceiro, 'instrutor', 'i@exemplo.com', podePublicar: true),
            default => $this->criarMembro($parceiro, $papel, $papel.'@exemplo.com'),
        };
    }

    public function test_matriz_confere_com_a_especificacao(): void
    {
        $parceiro = $this->criarParceiro('alfa');
        $usuarios = [];
        foreach (['operador', 'administrador', 'instrutor', 'suporte', 'aluno'] as $papel) {
            $usuarios[$papel] = $this->usuario($papel, $parceiro);
        }

        $this->assertEqualsCanonicalizing(array_keys($this->esperado()), Matriz::acoes(), 'Há ação na matriz que não está na especificação (ou o contrário).');

        foreach ($this->esperado() as $acao => $porPapel) {
            foreach ($porPapel as $papel => $devepoder) {
                $this->assertSame(
                    $devepoder,
                    Matriz::permite($usuarios[$papel], $acao),
                    "{$papel} em {$acao}: esperado ".($devepoder ? 'permitir' : 'negar'),
                );
                $this->assertSame(
                    $devepoder,
                    Gate::forUser($usuarios[$papel])->allows($acao),
                    "Gate de {$papel} em {$acao}",
                );
            }
        }
    }

    public function test_instrutor_so_publica_se_tiver_a_permissao(): void
    {
        $parceiro = $this->criarParceiro('alfa');
        $sem = $this->criarMembro($parceiro, 'instrutor', 'sem@exemplo.com', podePublicar: false);
        $com = $this->criarMembro($parceiro, 'instrutor', 'com@exemplo.com', podePublicar: true);

        $this->assertFalse(Matriz::permite($sem, 'cursos.publicar'));
        $this->assertTrue(Matriz::permite($com, 'cursos.publicar'));
        $this->assertTrue(Matriz::permite($sem, 'cursos.editar'));
    }

    public function test_conta_inativa_nao_tem_nenhuma_permissao(): void
    {
        $parceiro = $this->criarParceiro('alfa');
        $inativo = $this->criarMembro($parceiro, 'administrador', 'x@exemplo.com', estado: 'inativo');
        $aluno = $this->criarAluno($parceiro, extra: ['estado' => 'inativo']);

        foreach (Matriz::acoes() as $acao) {
            $this->assertFalse(Matriz::permite($inativo, $acao), "inativo em {$acao}");
            $this->assertFalse(Matriz::permite($aluno, $acao), "aluno inativo em {$acao}");
        }
    }

    public function test_visitante_sem_login_nao_tem_permissao(): void
    {
        foreach (Matriz::acoes() as $acao) {
            $this->assertFalse(Gate::allows($acao), "visitante em {$acao}");
        }
    }
}
