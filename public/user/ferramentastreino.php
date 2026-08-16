<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

stridebr_require_login();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Ferramentas de treino | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="page-heading"><h1>Ferramentas de treino</h1><p>Utilitários simples que funcionam direto no navegador.</p></div>
            <div class="tools-grid">
                <section class="tool-card">
                    <h2>Contador</h2>
                    <p>Toque nos botões para contar repetições, voltas ou qualquer sequência.</p>
                    <output class="counter-output" data-counter-output>0</output>
                    <div class="tool-actions">
                        <button type="button" data-counter-minus>−</button>
                        <button type="button" data-counter-plus>+</button>
                        <button type="button" data-counter-reset>Resetar</button>
                    </div>
                </section>
                <section class="tool-card">
                    <h2>Temporizador</h2>
                    <p>Defina a duração e mantenha a aba aberta para receber o alarme.</p>
                    <div class="timer-inputs">
                        <label>Minutos<input type="number" min="0" max="999" value="1" data-timer-minutes></label>
                        <label>Segundos<input type="number" min="0" max="59" value="0" data-timer-seconds></label>
                    </div>
                    <output class="timer-output" data-timer-output>01:00</output>
                    <div class="tool-actions">
                        <button type="button" data-timer-start>Iniciar</button>
                        <button type="button" data-timer-pause disabled>Pausar</button>
                        <button type="button" data-timer-reset>Resetar</button>
                    </div>
                    <audio src="<?php echo stridebr_e(stridebr_asset('/assets/audio/alarm1.mp3')); ?>" preload="auto" data-timer-alarm></audio>
                </section>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/treino.js')); ?>"></script>
</body>
</html>
