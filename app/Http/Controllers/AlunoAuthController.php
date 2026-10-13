<?php

namespace App\Http\Controllers;

use App\Auditoria\Auditor;
use App\Models\Aluno;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AlunoAuthController extends Controller
{
    public function formLogin()
    {
        return view('aluno.login');
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

        if (! Auth::guard('aluno')->attempt($credenciais, $request->boolean('lembrar'))) {
            Auditor::registrar('aluno.login_falhou', tenantId: $contexto->id(), detalhes: ['email' => $credenciais['email']]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'E-mail ou senha incorretos.']);
        }

        $request->session()->regenerate();
        $aluno = Auth::guard('aluno')->user();
        $aluno->forceFill(['ultimo_acesso_em' => now()])->save();
        Auditor::registrar('aluno.login', $aluno);

        return redirect()->intended(route('conta'));
    }

    public function formCadastro()
    {
        return view('aluno.cadastro');
    }

    public function cadastrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'nome.required' => 'Informe o nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe uma senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
        ]);

        $email = mb_strtolower(trim($dados['email']));

        // O e-mail é único por parceiro. A resposta é a mesma exista ou não a conta,
        // para não revelar quem já está cadastrado.
        if (Aluno::query()->where('email', $email)->exists()) {
            return redirect()->route('login')
                ->with('status', 'Se o cadastro for possível, você já pode entrar. Se já tinha conta, use "Entrar".');
        }

        $aluno = Aluno::create([
            'nome' => trim($dados['nome']),
            'email' => $email,
            'senha_hash' => Hash::make($dados['password']),
        ]);

        Auditor::registrar('aluno.cadastro', $aluno);
        Auth::guard('aluno')->login($aluno);
        $request->session()->regenerate();

        return redirect()->route('conta');
    }

    public function conta()
    {
        return view('aluno.conta', ['aluno' => Auth::guard('aluno')->user()]);
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::guard('aluno')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
