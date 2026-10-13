<?php

namespace App\Http\Controllers\Equipe;

use App\Ambiente\Video;
use App\Auditoria\Auditor;
use App\Catalogo\Ordenacao;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use App\Models\Arquivo;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Material;
use App\Models\Modulo;
use App\Models\ProgressoAula;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AulaController extends Controller
{
    use Autoriza;

    /** Fuso em que o parceiro digita datas (por parceiro, mais adiante). Guardamos sempre em UTC. */
    public const FUSO = 'America/Sao_Paulo';

    public function salvar(Request $request, int $moduloId): RedirectResponse
    {
        $modulo = Modulo::query()->findOrFail($moduloId);
        $curso = $this->cursoDoPainel($modulo->curso_id);
        $dados = $request->validate(['titulo' => ['required', 'string', 'max:160']], ['titulo.required' => 'Dê um título à aula.']);

        $aula = Aula::create([
            'modulo_id' => $modulo->id, 'curso_id' => $curso->id, 'titulo' => trim($dados['titulo']),
            'ordem' => Ordenacao::proxima(Aula::query()->where('modulo_id', $modulo->id)),
            'tipo' => 'video', 'publicada' => false,
        ]);
        Auditor::registrar('aula.criada', $this->membro(), alvo: $aula);

        return redirect()->route('equipe.aulas.editar', $aula->id)->with('status', 'Aula criada como rascunho. Preencha o conteúdo e publique.');
    }

    public function editar(int $id)
    {
        $aula = Aula::query()->findOrFail($id);
        $curso = $this->cursoDoPainel($aula->curso_id);

        return view('equipe.aulas.editar', [
            'aula' => $aula,
            'curso' => $curso,
            'modulo' => Modulo::query()->find($aula->modulo_id),
            'outras' => Aula::query()->where('curso_id', $curso->id)->whereKeyNot($aula->id)->orderBy('modulo_id')->orderBy('ordem')->get(),
            'materiais' => Material::query()->where('aula_id', $aula->id)->with('arquivo')->orderBy('ordem')->orderBy('id')->get(),
            'fuso' => self::FUSO,
            'dataLocal' => $aula->liberacao_data ? CarbonImmutable::parse($aula->liberacao_data)->setTimezone(self::FUSO)->format('Y-m-d\TH:i') : '',
        ]);
    }

    public function atualizar(Request $request, int $id): RedirectResponse
    {
        $aula = Aula::query()->findOrFail($id);
        $curso = $this->cursoDoPainel($aula->curso_id);

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'tipo' => ['required', Rule::in(['video', 'texto'])],
            'video_provedor' => ['nullable', Rule::in(['youtube', 'vimeo'])],
            'video_url' => ['nullable', 'string', 'max:300'],
            'duracao_min' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'conteudo_texto' => ['nullable', 'string', 'max:100000'],
            'liberacao_tipo' => ['required', Rule::in(['imediata', 'data', 'apos_aula'])],
            'liberacao_data' => ['nullable', 'date'],
            'liberacao_aula_id' => ['nullable', 'integer'],
        ], ['titulo.required' => 'Dê um título à aula.']);

        $ref = null;
        if ($dados['tipo'] === 'video' && ! empty($dados['video_url'])) {
            $ref = Video::extrairRef($dados['video_provedor'] ?? null, $dados['video_url']);
            if ($ref === null) {
                return back()->withInput()->withErrors(['video_url' => 'Não reconheci esse endereço de vídeo. Escolha o provedor e cole o endereço da página do vídeo.']);
            }
        }

        $liberacaoData = null;
        $liberacaoAula = null;

        if ($dados['liberacao_tipo'] === 'data') {
            if (empty($dados['liberacao_data'])) {
                return back()->withInput()->withErrors(['liberacao_data' => 'Informe a data e a hora da liberação.']);
            }
            $liberacaoData = CarbonImmutable::parse($dados['liberacao_data'], self::FUSO)->utc();
        }

        if ($dados['liberacao_tipo'] === 'apos_aula') {
            $liberacaoAula = Aula::query()->where('curso_id', $curso->id)->whereKeyNot($aula->id)
                ->whereKey((int) ($dados['liberacao_aula_id'] ?? 0))->value('id');
            if ($liberacaoAula === null) {
                return back()->withInput()->withErrors(['liberacao_aula_id' => 'Escolha a aula que precisa ser concluída antes.']);
            }
        }

        $aula->update([
            'titulo' => trim($dados['titulo']),
            'tipo' => $dados['tipo'],
            'video_provedor' => $ref ? $dados['video_provedor'] : null,
            'video_ref' => $ref,
            'duracao_seg' => isset($dados['duracao_min']) ? $dados['duracao_min'] * 60 : null,
            'conteudo_texto' => $dados['tipo'] === 'texto' ? ($dados['conteudo_texto'] ?? null) : null,
            'liberacao_tipo' => $dados['liberacao_tipo'],
            'liberacao_data' => $liberacaoData,
            'liberacao_aula_id' => $liberacaoAula,
        ]);
        Auditor::registrar('aula.editada', $this->membro(), alvo: $aula);

        return redirect()->route('equipe.aulas.editar', $aula->id)->with('status', 'Aula salva.');
    }

    public function publicar(Request $request, int $id): RedirectResponse
    {
        $aula = Aula::query()->findOrFail($id);
        $this->cursoDoPainel($aula->curso_id);
        $publicar = $request->boolean('publicada');

        $aula->update(['publicada' => $publicar]);
        Auditor::registrar($publicar ? 'aula.publicada' : 'aula.despublicada', $this->membro(), alvo: $aula);

        return back()->with('status', $publicar ? 'Aula publicada.' : 'Aula voltou a ser rascunho.');
    }

    public function mover(Request $request, int $id): RedirectResponse
    {
        $aula = Aula::query()->findOrFail($id);
        $this->cursoDoPainel($aula->curso_id);
        $dados = $request->validate(['sentido' => ['required', 'in:sobe,desce']]);

        Ordenacao::mover(Aula::query()->where('modulo_id', $aula->modulo_id), $aula, $dados['sentido']);

        return redirect()->route('equipe.cursos.editar', [$aula->curso_id, 'aba' => 'conteudo']);
    }

    /** Aula com histórico de aluno não é apagada: despublique. */
    public function apagar(int $id): RedirectResponse
    {
        $aula = Aula::query()->findOrFail($id);
        $this->cursoDoPainel($aula->curso_id);
        $volta = redirect()->route('equipe.cursos.editar', [$aula->curso_id, 'aba' => 'conteudo']);

        if (ProgressoAula::query()->where('aula_id', $aula->id)->exists()) {
            return $volta->withErrors(['conteudo' => 'Há alunos com progresso nesta aula. Em vez de apagar, volte-a para rascunho.']);
        }

        if (Aula::query()->where('liberacao_aula_id', $aula->id)->exists()) {
            return $volta->withErrors(['conteudo' => 'Outra aula depende desta para ser liberada. Mude a regra dela antes.']);
        }

        DB::transaction(function () use ($aula): void {
            Material::query()->where('aula_id', $aula->id)->delete();
            $aula->delete();
        });
        Auditor::registrar('aula.apagada', $this->membro(), alvo: $aula);

        return $volta->with('status', 'Aula apagada.');
    }

    public function enviarMaterial(Request $request, int $id): RedirectResponse
    {
        $aula = Aula::query()->findOrFail($id);
        $this->cursoDoPainel($aula->curso_id);

        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg', 'max:20480'],
            'titulo' => ['nullable', 'string', 'max:160'],
        ], [
            'arquivo.required' => 'Escolha um arquivo.',
            'arquivo.mimes' => 'Tipo de arquivo não aceito. Use PDF, Word, Excel, PowerPoint, texto, CSV, ZIP ou imagem.',
            'arquivo.max' => 'O arquivo pode ter no máximo 20 MB.',
            'arquivo.uploaded' => 'Não foi possível enviar o arquivo (talvez seja grande demais).',
        ]);

        $arquivo = CursoController::guardarArquivo($request, 'arquivo', 'materiais', false, $this->membro());
        $titulo = trim((string) $request->input('titulo')) ?: pathinfo($arquivo->nome_original, PATHINFO_FILENAME);

        Material::create([
            'aula_id' => $aula->id, 'arquivo_id' => $arquivo->id, 'titulo' => mb_substr($titulo, 0, 160),
            'ordem' => Ordenacao::proxima(Material::query()->where('aula_id', $aula->id)),
        ]);
        Auditor::registrar('material.enviado', $this->membro(), alvo: $aula, detalhes: ['nome' => $arquivo->nome_original]);

        return redirect()->route('equipe.aulas.editar', $aula->id)->with('status', 'Material enviado.');
    }

    public function apagarMaterial(int $id): RedirectResponse
    {
        $material = Material::query()->findOrFail($id);
        $aula = Aula::query()->findOrFail($material->aula_id);
        $this->cursoDoPainel($aula->curso_id);

        $arquivo = Arquivo::query()->find($material->arquivo_id);
        $material->delete();

        if ($arquivo !== null && ! Material::query()->where('arquivo_id', $arquivo->id)->exists()
            && ! Curso::query()->where('capa_arquivo_id', $arquivo->id)->exists()) {
            Storage::disk('local')->delete($arquivo->caminho);
            $arquivo->delete();
        }
        Auditor::registrar('material.removido', $this->membro(), alvo: $aula);

        return redirect()->route('equipe.aulas.editar', $aula->id)->with('status', 'Material removido.');
    }
}
