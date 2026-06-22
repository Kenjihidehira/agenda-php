<?php
declare(strict_types=1);

const ARQUIVO_DADOS = __DIR__ . '/data/agendamentos.json';

function garantirArquivo(): void
{
    $diretorio = dirname(ARQUIVO_DADOS);
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0775, true);
    }
    if (!file_exists(ARQUIVO_DADOS)) {
        file_put_contents(ARQUIVO_DADOS, '[]', LOCK_EX);
    }
}

function carregarAgendamentos(): array
{
    garantirArquivo();
    $conteudo = file_get_contents(ARQUIVO_DADOS);
    $dados = json_decode($conteudo ?: '[]', true);
    return is_array($dados) ? $dados : [];
}

function salvarAgendamentos(array $agendamentos): void
{
    garantirArquivo();
    file_put_contents(
        ARQUIVO_DADOS,
        json_encode(array_values($agendamentos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function limparTexto(string $texto): string
{
    return trim(strip_tags($texto));
}

function validarAgendamento(array $dados): array
{
    $erros = [];

    if (strlen($dados['cliente']) < 2) {
        $erros[] = 'Informe o nome do cliente.';
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (strlen($dados['servico']) < 2) {
        $erros[] = 'Informe o serviço.';
    }
    if (!$dados['data'] || !$dados['hora']) {
        $erros[] = 'Informe a data e o horário.';
    }

    return $erros;
}

function gerarId(): string
{
    return bin2hex(random_bytes(6));
}

function formatarData(string $data): string
{
    $objeto = DateTime::createFromFormat('Y-m-d', $data);
    return $objeto ? $objeto->format('d/m/Y') : $data;
}

function ordenarAgendamentos(array &$agendamentos): void
{
    usort($agendamentos, fn(array $a, array $b) =>
        strcmp($a['data'] . $a['hora'], $b['data'] . $b['hora'])
    );
}
