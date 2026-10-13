<?php

namespace App\Http\Controllers;

use App\Auditoria\Auditor;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EquipeAuthController extends Controller
{
    public function formLogin()
    {
        return view('equipe.login');
    }

    public function login(Request $request, TenantContext $contexto): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ]);

        $credenciais = [
            'email' => mb_strtolower(trim($dados['email'])),
            'password' => $dados['password'],
            'estado' => 'ativo',
        ];

        if (! Auth::guard('equipe')->attempt($credenciais)) {
            Auditor::registrar('equipe.login_falhou', tenantId: $contexto->id(), detalhes: ['email' => $credenciais['email']]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'E-mail ou senha incorretos.']);
        }

        $request->session()->regenerate();
        Auditor::registrar('equipe.login', Auth::guard('equipe')->user());

        return redirect()->intended(route('equipe.painel'));
    }

    public function painel()
    {
        return view('equipe.painel', ['membro' => Auth::guard('equipe')->user()]);
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::guard('equipe')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('equipe.login');
    }
}
