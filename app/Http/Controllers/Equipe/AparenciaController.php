<?php

namespace App\Http\Controllers\Equipe;

use App\Ambiente\Contraste;
use App\Ambiente\Personalizacao;
use App\Ambiente\Tema;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AparenciaController extends Controller
{
    use Autoriza;

    private const FUNDO = '#0E0E14';

    public function index(Request $request)
    {
        $this->autorizar('aparencia.configurar');

        $atual = array_merge(Tema::PADRAO, Personalizacao::paraEditar('tema', []));
        $tema = new Tema($atual);

        return view('equipe.aparencia', [
            'valores' => $atual,
            'logoUrl' => $tema->logoUrl(),
            'pendente' => Personalizacao::pendente('tema'),
            'fontes' => ['sora' => 'Sora (moderna)', 'inter' => 'Inter (neutra)', 'sistema' => 'Padrão do aparelho'],
            'raios' => ['reto' => 'Retos', 'suave' => 'Suaves', 'arredondado' => 'Bem arredondados'],
            'contrasteMinimo' => Tema::CONTRASTE_MINIMO,
        ]);
    }

    public function salvar(Request $request): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');

        $dados = $request->validate([
            'nome_ambiente' => ['nullable', 'string', 'max:60'],
            'cor_principal' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cor_secundaria' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fonte_titulo' => ['required', Rule::in(array_keys(Tema::FONTES))],
            'fonte_texto' => ['required', Rule::in(array_keys(Tema::FONTES))],
            'raio_borda' => ['required', Rule::in(array_keys(Tema::RAIOS))],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:min_width=64,min_height=64'],
            'remover_logo' => ['nullable', 'boolean'],
        ], [
            'cor_principal.regex' => 'Use uma cor no formato #RRGGBB, por exemplo #8B6CFF.',
            'cor_secundaria.regex' => 'Use uma cor no formato #RRGGBB, por exemplo #F5B942.',
            'logo.mimes' => 'A logo deve ser JPG, PNG ou WEBP.',
            'logo.max' => 'A logo pode ter no máximo 1 MB.',
            'logo.dimensions' => 'A logo é pequena demais (mínimo 64 × 64 pixels).',
        ]);

        foreach (['cor_principal' => 'principal', 'cor_secundaria' => 'secundária'] as $campo => $rotulo) {
            $razao = Contraste::razao($dados[$campo], self::FUNDO);
            if ($razao < Tema::CONTRASTE_MINIMO) {
                return back()->withInput()->withErrors([$campo => sprintf(
                    'A cor %s fica difícil de enxergar sobre o fundo escuro (contraste %.1f; o mínimo é %.1f). Escolha uma cor mais clara ou mais viva.',
                    $rotulo, $razao, Tema::CONTRASTE_MINIMO,
                )]);
            }
        }

        $atual = Personalizacao::paraEditar('tema', []);
        $conteudo = [
            'tema_base' => 'cinema',
            'nome_ambiente' => trim((string) ($dados['nome_ambiente'] ?? '')) ?: null,
            'cor_principal' => strtoupper($dados['cor_principal']),
            'cor_secundaria' => strtoupper($dados['cor_secundaria']),
            'fonte_titulo' => $dados['fonte_titulo'],
            'fonte_texto' => $dados['fonte_texto'],
            'raio_borda' => $dados['raio_borda'],
            'logo_arquivo_id' => $atual['logo_arquivo_id'] ?? null,
        ];

        if ($request->boolean('remover_logo')) {
            $conteudo['logo_arquivo_id'] = null;
        }

        if ($request->hasFile('logo')) {
            $conteudo['logo_arquivo_id'] = CursoController::guardarArquivo($request, 'logo', 'identidade', true, $this->membro())->id;
        }

        Personalizacao::salvarRascunho('tema', $conteudo, $this->membro());

        return redirect()->route('equipe.aparencia')->with('status', 'Rascunho salvo. Veja a prévia e publique quando estiver pronto.');
    }

    public function publicar(): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        $publicou = Personalizacao::publicar('tema', $this->membro());

        return redirect()->route('equipe.aparencia')->with('status', $publicou ? 'Aparência publicada. Os alunos já veem as mudanças.' : 'Não há alterações para publicar.');
    }

    public function descartar(): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        Personalizacao::descartar('tema', $this->membro());

        return redirect()->route('equipe.aparencia')->with('status', 'Alterações descartadas.');
    }
}
