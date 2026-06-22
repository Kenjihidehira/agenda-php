<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/functions.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

$mensagem = $_SESSION['mensagem'] ?? null;
$erro = $_SESSION['erro'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['erro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Requisição inválida.');
    }

    $agendamentos = carregarAgendamentos();
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'criar') {
        $novo = [
            'id' => gerarId(),
            'cliente' => limparTexto($_POST['cliente'] ?? ''),
            'email' => limparTexto($_POST['email'] ?? ''),
            'telefone' => limparTexto($_POST['telefone'] ?? ''),
            'servico' => limparTexto($_POST['servico'] ?? ''),
            'data' => limparTexto($_POST['data'] ?? ''),
            'hora' => limparTexto($_POST['hora'] ?? ''),
            'observacoes' => limparTexto($_POST['observacoes'] ?? ''),
            'status' => 'pendente',
            'criadoEm' => date(DATE_ATOM),
        ];

        $erros = validarAgendamento($novo);
        $horarioOcupado = array_filter($agendamentos, fn(array $item) =>
            $item['data'] === $novo['data'] &&
            $item['hora'] === $novo['hora'] &&
            $item['status'] !== 'cancelado'
        );

        if ($horarioOcupado) {
            $erros[] = 'Esse horário já está ocupado.';
        }

        if ($erros) {
            $_SESSION['erro'] = implode(' ', $erros);
        } else {
            $agendamentos[] = $novo;
            salvarAgendamentos($agendamentos);
            $_SESSION['mensagem'] = 'Agendamento criado com sucesso.';
        }
    } else {
        $id = limparTexto($_POST['id'] ?? '');
        $indice = array_search($id, array_column($agendamentos, 'id'), true);

        if ($indice !== false) {
            if ($acao === 'confirmar') {
                $agendamentos[$indice]['status'] = 'confirmado';
                $_SESSION['mensagem'] = 'Agendamento confirmado.';
            } elseif ($acao === 'cancelar') {
                $agendamentos[$indice]['status'] = 'cancelado';
                $_SESSION['mensagem'] = 'Agendamento cancelado.';
            } elseif ($acao === 'excluir') {
                array_splice($agendamentos, $indice, 1);
                $_SESSION['mensagem'] = 'Agendamento excluído.';
            }
            salvarAgendamentos($agendamentos);
        }
    }

    header('Location: index.php');
    exit;
}

$agendamentos = carregarAgendamentos();
ordenarAgendamentos($agendamentos);
$filtro = $_GET['status'] ?? 'todos';
$visiveis = $filtro === 'todos'
    ? $agendamentos
    : array_values(array_filter($agendamentos, fn(array $item) => $item['status'] === $filtro));

$hoje = date('Y-m-d');
$totalHoje = count(array_filter($agendamentos, fn(array $item) =>
    $item['data'] === $hoje && $item['status'] !== 'cancelado'
));
$confirmados = count(array_filter($agendamentos, fn(array $item) => $item['status'] === 'confirmado'));
$pendentes = count(array_filter($agendamentos, fn(array $item) => $item['status'] === 'pendente'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema simples de agendamentos desenvolvido em PHP.">
    <title>AgendaFácil</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<aside class="menu">
    <a class="marca" href="index.php"><span>A</span> AgendaFácil</a>
    <nav>
        <a class="ativo" href="index.php">▦ <span>Agenda</span></a>
        <a href="#novo">＋ <span>Novo horário</span></a>
        <a href="#resumo">◫ <span>Resumo</span></a>
    </nav>
    <p>Projeto em PHP<br><strong>Portfólio GitHub</strong></p>
</aside>

<main>
    <header>
        <div>
            <p class="etiqueta">PAINEL DE ATENDIMENTOS</p>
            <h1>Sua agenda, <span>sem confusão.</span></h1>
            <p>Organize clientes, serviços e horários com facilidade.</p>
        </div>
        <button id="abrir-modal" type="button">+ Novo agendamento</button>
    </header>

    <?php if ($mensagem): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alerta erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <section id="resumo" class="resumo">
        <article><span>Hoje</span><strong><?= $totalHoje ?></strong><small>atendimentos</small></article>
        <article><span>Confirmados</span><strong><?= $confirmados ?></strong><small>na agenda</small></article>
        <article><span>Pendentes</span><strong><?= $pendentes ?></strong><small>aguardando</small></article>
        <article><span>Total</span><strong><?= count($agendamentos) ?></strong><small>registros</small></article>
    </section>

    <section class="painel">
        <div class="topo-painel">
            <div>
                <h2>Próximos agendamentos</h2>
                <p><?= count($visiveis) ?> <?= count($visiveis) === 1 ? 'registro encontrado' : 'registros encontrados' ?></p>
            </div>
            <form method="get">
                <select name="status" onchange="this.form.submit()" aria-label="Filtrar por status">
                    <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="pendente" <?= $filtro === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="confirmado" <?= $filtro === 'confirmado' ? 'selected' : '' ?>>Confirmados</option>
                    <option value="cancelado" <?= $filtro === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                </select>
            </form>
        </div>

        <?php if (!$visiveis): ?>
            <div class="vazio">
                <span>✓</span>
                <h3>Nada agendado por aqui</h3>
                <p>Crie um novo horário para começar.</p>
            </div>
        <?php else: ?>
            <div class="tabela">
                <table>
                    <thead>
                    <tr><th>Cliente</th><th>Serviço</th><th>Data e hora</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visiveis as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['cliente']) ?></strong>
                                <span><?= htmlspecialchars($item['email']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($item['servico']) ?></td>
                            <td>
                                <strong><?= formatarData($item['data']) ?></strong>
                                <span><?= htmlspecialchars($item['hora']) ?></span>
                            </td>
                            <td><span class="status <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span></td>
                            <td>
                                <div class="acoes">
                                    <?php if ($item['status'] === 'pendente'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button name="acao" value="confirmar" title="Confirmar">✓</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($item['status'] !== 'cancelado'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button name="acao" value="cancelar" title="Cancelar">−</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" onsubmit="return confirm('Excluir este agendamento?')">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="excluir" name="acao" value="excluir" title="Excluir">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<dialog id="novo">
    <form method="post">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="acao" value="criar">
        <div class="topo-modal">
            <div><p class="etiqueta">NOVO HORÁRIO</p><h2>Criar agendamento</h2></div>
            <button id="fechar-modal" type="button">×</button>
        </div>
        <div class="grade-form">
            <label>Cliente<input name="cliente" type="text" maxlength="80" required></label>
            <label>E-mail<input name="email" type="email" maxlength="100" required></label>
            <label>Telefone<input name="telefone" type="tel" maxlength="20" placeholder="(00) 00000-0000"></label>
            <label>Serviço<input name="servico" type="text" maxlength="80" required></label>
            <label>Data<input name="data" type="date" min="<?= $hoje ?>" required></label>
            <label>Horário<input name="hora" type="time" required></label>
        </div>
        <label>Observações<textarea name="observacoes" rows="3" maxlength="300"></textarea></label>
        <button class="salvar" type="submit">Salvar agendamento</button>
    </form>
</dialog>

<script src="app.js"></script>
</body>
</html>
