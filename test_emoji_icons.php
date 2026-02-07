<?php

require 'vendor/autoload.php';

use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

echo "🎨 Testando EmojiPath com ícones do PDF\n";
echo "=====================================\n\n";

$icons = EmojiPath::getIconArray();

echo "Ícones do PDF (getIconArray):\n";
foreach ($icons as $name => $uri) {
    if (!empty($uri)) {
        echo "  ✓ $name: $uri\n";
    } else {
        echo "  ✗ $name: (não encontrado)\n";
    }
}

echo "\n\nVerificação de existência:\n";
$codepoints = ['2139', '26a0', '1f534', '1f535', '2705'];
foreach ($codepoints as $cp) {
    $exists = EmojiPath::exists($cp);
    echo "  " . ($exists ? "✓" : "✗") . " $cp: " . ($exists ? "OK" : "FALTA") . "\n";
}

echo "\n✅ Teste concluído!\n";
