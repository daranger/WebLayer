<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Debugger | Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #0f172a; /* Глубокий темный */
            color: #e2e8f0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 2rem;
            margin: 0;
        }
        .debug-card {
            background: #1e293b; /* Графитовая карточка */
            border-left: 5px solid #ef4444; /* Красная полоса ошибки */
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            max-width: 1200px;
            margin: 0 auto;
        }
        .error-title {
            color: #f87171;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-family: monospace;
            word-break: break-all;
            background: rgba(239, 68, 68, 0.1);
            padding: 1rem;
            border-radius: 8px;
        }
        .meta-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1rem;
            margin-bottom: 2rem;
            font-size: 1rem;
        }
        .meta-label {
            color: #94a3b8;
            font-weight: bold;
        }
        .meta-value {
            color: #38bdf8; /* Красивый голубой для путей файлов */
            font-family: monospace;
            word-break: break-all;
        }
        .meta-value.line {
            color: #34d399; /* Зеленый для номеров строк */
            font-weight: bold;
        }
        .trace-header {
            font-size: 1.2rem;
            color: #cbd5e1;
            margin-bottom: 0.8rem;
            font-weight: 600;
        }
        pre {
            background: #0f172a;
            color: #94a3b8;
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            border: 1px solid #334155;
        }
        /* Стилизуем сам текст трейса, подсвечивая ключевые места */
        .trace-num { color: #64748b; }
        .trace-file { color: #38bdf8; }
        .trace-line { color: #34d399; }
        .trace-func { color: #f2a243; }
    </style>
</head>
<body>

<div class="debug-card">
    <div class="error-title">
        Exception: <?php echo htmlspecialchars($e->getMessage()); ?>
    </div>

    <div class="meta-info">
        <div class="meta-label">File:</div>
        <div class="meta-value"><?php echo htmlspecialchars($e->getFile()); ?></div>

        <div class="meta-label">Line:</div>
        <div class="meta-value line"><?php echo $e->getLine(); ?></div>
    </div>

    <div class="trace-header">Stack Trace</div>
    <pre><?php
        // Небольшая магия: подсвечиваем Trace, чтобы он выглядел как в Laravel
        $trace = htmlspecialchars($e->getTraceAsString());

        // Подсвечиваем номера (#0, #1 и т.д.)
        $trace = preg_replace('/(#\d+)/', '<span class="trace-num">$1</span>', $trace);
        // Подсвечиваем вызовы функций/методов
        $trace = preg_replace('/([\w+\\\\]+->\w+\(|[\w+\\\\]+::\w+\(|\b\w+\(\))/', '<span class="trace-func">$1</span>', $trace);

        echo $trace;
        ?></pre>
</div>

</body>
</html>