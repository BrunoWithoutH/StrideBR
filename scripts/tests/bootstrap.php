<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma_compartilhar.php';
require_once dirname(__DIR__, 2) . '/src/function/treinador.php';
require_once dirname(__DIR__, 2) . '/src/function/dashboard.php';
require_once dirname(__DIR__, 2) . '/src/function/eventos.php';
require_once dirname(__DIR__, 2) . '/src/function/account_data.php';
require_once dirname(__DIR__, 2) . '/src/function/marketing.php';

$alphaTestDatabase = (string) $pdo->query('SELECT current_database()')->fetchColumn();
$alphaTestAllowed = getenv('STRIDEBR_TEST_ALLOW_DATABASE') === '1';
if (!$alphaTestAllowed && preg_match('/(?:test|alpha)/i', $alphaTestDatabase) !== 1) {
    throw new RuntimeException('Testes recusados no banco "' . $alphaTestDatabase . '". Use um banco separado contendo test/alpha no nome.');
}

final class AlphaTest
{
    public static int $assertions = 0;

    public static function assert(bool $condition, string $message): void
    {
        self::$assertions++;
        if (!$condition) throw new RuntimeException($message);
    }

    public static function same(mixed $expected, mixed $actual, string $message): void
    {
        self::assert($expected === $actual, $message . ' (esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true) . ')');
    }

    public static function throws(callable $callback, string $message): void
    {
        self::$assertions++;
        try {
            $callback();
        } catch (Throwable) {
            return;
        }
        throw new RuntimeException($message);
    }
}

function alphaTestUser(PDO $pdo, string $suffix, array $extra = []): string
{
    $id = 'alpha_test_' . substr(sha1($suffix), 0, 10);
    $values = array_merge([
        'nome' => 'Teste ' . $suffix,
        'email' => $suffix . '@alpha-test.invalid',
        'username' => 'alpha_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($suffix)),
        'status' => 'Ativo',
        'trainer' => false,
    ], $extra);
    $stmt = $pdo->prepare('INSERT INTO usuarios (idusuario, nomeusuario, nome_exibicao, emailusuario, senhausuario, username, statususuario, onboarding_concluido, modo_treinador) VALUES (:id, :nome, :nome, :email, :senha, :username, :status, TRUE, :trainer)');
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':nome', $values['nome']);
    $stmt->bindValue(':email', $values['email']);
    $stmt->bindValue(':senha', stridebr_password_hash('Alpha-test-password-123'));
    $stmt->bindValue(':username', $values['username']);
    $stmt->bindValue(':status', $values['status']);
    $stmt->bindValue(':trainer', (bool) $values['trainer'], PDO::PARAM_BOOL);
    $stmt->execute();
    return $id;
}

function alphaTestGeneralModel(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT idmodelo FROM modelos_modalidade WHERE idmodalidade = 'm_geral' AND ativo = TRUE ORDER BY padrao DESC, versao DESC LIMIT 1");
    $id = $stmt->fetchColumn();
    if (!is_string($id) || $id === '') throw new RuntimeException('Modelo geral seed não encontrado.');
    return $id;
}

function alphaTestRouteModel(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT mm.idmodelo FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade WHERE mm.ativo = TRUE AND m.ativo = TRUE AND m.permite_rota = TRUE ORDER BY mm.padrao DESC, mm.versao DESC LIMIT 1");
    $id = $stmt->fetchColumn();
    if (!is_string($id) || $id === '') throw new RuntimeException('Modelo com rota seed não encontrado.');
    return $id;
}

function alphaTestCleanup(PDO $pdo): void
{
    try {
        if ($pdo->query("SELECT to_regclass('stridebr.marketing_eventos_aquisicao') IS NOT NULL")->fetchColumn()) {
            $pdo->exec("DELETE FROM marketing_eventos_aquisicao WHERE idusuario LIKE 'alpha_test_%' OR chave_atribuicao IN (SELECT chave_hash FROM marketing_atribuicoes WHERE idusuario LIKE 'alpha_test_%' OR idcampanha IN (SELECT idcampanha FROM marketing_campanhas WHERE codigo LIKE 'alpha_test_%'))");
            $pdo->exec("DELETE FROM marketing_atribuicoes WHERE idusuario LIKE 'alpha_test_%' OR idcampanha IN (SELECT idcampanha FROM marketing_campanhas WHERE codigo LIKE 'alpha_test_%')");
            $pdo->exec("DELETE FROM marketing_placements WHERE codigo LIKE 'alpha_test_%' OR idcampanha IN (SELECT idcampanha FROM marketing_campanhas WHERE codigo LIKE 'alpha_test_%')");
            $pdo->exec("DELETE FROM marketing_campanhas WHERE codigo LIKE 'alpha_test_%'");
        }
    } catch (Throwable) {
    }
    try {
        if ($pdo->query("SELECT to_regclass('stridebr.eventos_esportivos') IS NOT NULL")->fetchColumn()) {
            $pdo->exec("DELETE FROM eventos_esportivos WHERE criado_por LIKE 'alpha_test_%' OR titulo LIKE 'Alpha test event%'");
        }
    } catch (Throwable) {
    }
    $pdo->exec("DELETE FROM usuarios WHERE idusuario LIKE 'alpha_test_%'");
    $pdo->exec("DELETE FROM auth_rate_limits WHERE escopo LIKE 'alpha_test_%'");
}
