<?php
session_start();

/**
 * Autentica o usuário validando e-mail e senha com hash (bcrypt)
 * e inicia a sessão identificando o perfil (ADMIN ou VENDEDOR).
 */
function autenticarUsuario(PDO $pdo, string $email, string $senha): array
{
    if (empty($email) || empty($senha)) {
        return ['sucesso' => false, 'mensagem' => 'Preencha e-mail e senha.'];
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !$usuario['ativo'] || !password_verify($senha, $usuario['senha_hash'])) {
        return ['sucesso' => false, 'mensagem' => 'Credenciais inválidas.'];
    }

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_perfil'] = $usuario['perfil']; // ADMIN ou VENDEDOR
    $_SESSION['ultimo_acesso'] = time();

    return [
        'sucesso' => true,
        'perfil' => $usuario['perfil'],
        'mensagem' => 'Login realizado com sucesso.'
    ];
}

/**
 * Bloqueia acesso direto a rotas restritas para usuários não autenticados
 * ou sem permissão de administrador.
 */
function verificarPermissao(string $perfilExigido = 'VENDEDOR'): void
{
    $tempoExpiracao = 1800; // 30 minutos

    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        header('Location: /login.php');
        exit;
    }

    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso']) > $tempoExpiracao) {
        encerrarSessao();
        header('Location: /login.php?erro=sessao_expirada');
        exit;
    }

    $_SESSION['ultimo_acesso'] = time();

    if ($perfilExigido === 'ADMIN' && $_SESSION['usuario_perfil'] !== 'ADMIN') {
        http_response_code(403);
        exit('Acesso negado: permissão exclusiva de administrador.');
    }
}

/**
 * Encerra a sessão de forma segura (logout).
 */
function encerrarSessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}