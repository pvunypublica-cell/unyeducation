<?php

namespace App\Http\Controllers;

use App\Auditoria\Auditor;
use App\Mail\LinkMagicoMail;
use App\Models\Aluno;
use App\Models\Parceiro;
use App\Models\TokenAcesso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Entrada por link enviado ao e-mail. O link é de uso único e vale por poucos minutos.
 * O GET só mostra uma página de confirmação; quem consome o token é o POST, para que
 * verificadores de link de e-mail (que abrem links sozinhos) não gastem o token.
 */
class LinkMagicoController extends Controller
{
    public function solicitar(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'email', 'max:190']], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        $aluno = Aluno::query()
            ->where('email', mb_strtolower(trim($dados['email'])))
            ->where('estado', 'ativo')
            ->first();

        if ($aluno) {
            $minutos = (int) config('uny.link_magico_minutos');
            $token = Str::random(48);

            TokenAcesso::create([
                'aluno_id' => $aluno->id,
                'token_hash' => hash('sha256', $token),
                'expira_em' => now()->addMinutes($minutos),
            ]);

            /** @var Parceiro $parceiro */
            $parceiro = $request->attributes->get('parceiro');

            Mail::to($aluno->email)->send(new LinkMagicoMail(
                $parceiro->nome,
                route('link-magico.confirmar', ['token' => $token]),
                $minutos,
            ));

            Auditor::registrar('aluno.link_enviado', $aluno);
        }

        // Mesma resposta exista ou não a conta.
        return back()->with('status', 'Se o e-mail estiver cadastrado, enviamos um link de acesso. Ele vale por poucos minutos.');
    }

    public function confirmar(string $token)
    {
        return view('aluno.link-confirmar', ['valido' => $this->buscar($token) !== null, 'token' => $token]);
    }

    public function entrar(Request $request, string $token): RedirectResponse
    {
        $registro = $this->buscar($token);

        if ($registro === null) {
            return redirect()->route('login')->withErrors(['email' => 'Este link expirou ou já foi usado. Peça um novo.']);
        }

        $registro->forceFill(['usado_em' => now()])->save();

        $aluno = Aluno::query()->where('estado', 'ativo')->find($registro->aluno_id);

        if ($aluno === null) {
            return redirect()->route('login')->withErrors(['email' => 'Não foi possível entrar com este link.']);
        }

        Auth::guard('aluno')->login($aluno);
        $request->session()->regenerate();
        $aluno->forceFill([
            'ultimo_acesso_em' => now(),
            'email_verificado_em' => $aluno->email_verificado_em ?? now(),
        ])->save();
        Auditor::registrar('aluno.login_link', $aluno);

        return redirect()->route('conta');
    }

    private function buscar(string $token): ?TokenAcesso
    {
        return TokenAcesso::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('usado_em')
            ->where('expira_em', '>', now())
            ->first();
    }
}
