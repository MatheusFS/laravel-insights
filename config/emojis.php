<?php

/**
 * Configuração de Emojis PNG para Projeto Laravel Insights
 * 
 * Define mapeamentos de nomes legíveis para codepoints Unicode
 * e configurações de fonte (Twemoji/Noto).
 * 
 * Uso:
 *  $checkUri = config('insights.emojis.byName.check'); // '2714'
 *  $source = config('insights.emojis.source');          // 'twemoji'
 */

return [

    /**
     * Fonte de emojis: 'twemoji' (recomendado) ou 'noto'
     * 
     * - twemoji: Menor tamanho, design único
     *            https://github.com/twitter/twemoji
     * 
     * - noto: Mais variedade de estilos
     *         https://github.com/googlei18n/noto-emoji
     */
    'source' => env('EMOJI_SOURCE', 'twemoji'),

    /**
     * Tamanho padrão dos emojis em pixels
     */
    'size' => env('EMOJI_SIZE', 72),

    /**
     * Diretório de armazenamento relativo ao project root
     */
    'directory' => 'resources/emojis',

    /**
     * Mapeamento de nomes legíveis para codepoints Unicode
     * 
     * Formato: 'nome_descritivo' => 'codepoint_hex'
     * 
     * Encontrar codepoints: https://unicode.org/emoji/charts/full-emoji-list.html
     */
    'byName' => [
        // Status/Validação
        'check'        => '2714',     // ✔️ Checkmark
        'fail'         => '274c',     // ❌ Cross/Fail
        'warning'      => '26a0',     // ⚠️ Warning Sign
        'info'         => '2139',     // ℹ️ Information
        
        // Indicadores
        'fire'         => '1f525',    // 🔥 Fire (urgente/crítico)
        'clock'        => '1f550',    // 🕐 Clock (tempo)
        'dot'          => '2b55',     // 🔵 Blue Circle (ponto)
        'star'         => '2b50',     // ⭐ Star (importante)
        
        // Gestos
        'ok'           => '1f44c',    // 👌 OK Hand
        'no'           => '1f44e',    // 👎 Thumbs Down
        'yes'          => '1f44d',    // 👍 Thumbs Up
        
        // Alertas
        'alert'        => '1f6a8',    // 🚨 Alert/Siren
        'speed'        => '1f4a8',    // 💨 Dashing Away (rápido)
        'perfect'      => '1f4af',    // 💯 100 Points
        'check2'       => '2705',     // ✅ Check Mark Button
        
        // Adicionais
        'heart'        => '2764',     // ❤️ Red Heart
        'arrow-right'  => '27a1',     // ➡️ Arrow Right
        'hourglass'    => '231b',     // ⌛ Hourglass
        'target'       => '1f3af',    // 🎯 Target
        'rocket'       => '1f680',    // 🚀 Rocket
        'shield'       => '1f6e1',    // 🛡️ Shield
        'lock'         => '1f512',    // 🔒 Lock
        'unlock'       => '1f513',    // 🔓 Unlock
        'key'          => '1f511',    // 🔑 Key
        'bug'          => '1f41b',    // 🐛 Bug
        'gear'         => '2699',     // ⚙️ Gear
        'wrench'       => '1f527',    // 🔧 Wrench
        'hammer'       => '1f528',    // 🔨 Hammer
        'chart-up'     => '1f4c8',    // 📈 Chart Increasing
        'chart-down'   => '1f4c9',    // 📉 Chart Decreasing
        'document'     => '1f4c4',    // 📄 Document
        'folder'       => '1f4c1',    // 📁 Folder
        'database'     => '1f4f1',    // 📲 Database-like icon
        'globe'        => '1f30d',    // 🌍 Globe
        'cloud'        => '2601',     // ☁️ Cloud
    ],

    /**
     * Grupos de emojis por categoria
     * Útil para selecionar subconjuntos conforme necessidade
     */
    'groups' => [
        'status' => ['check', 'fail', 'warning', 'info'],
        'urgent' => ['fire', 'alert', 'clock', 'hourglass'],
        'success' => ['check', 'yes', 'perfect', 'star'],
        'security' => ['lock', 'unlock', 'key', 'shield'],
        'development' => ['bug', 'gear', 'wrench', 'hammer', 'rocket'],
        'metrics' => ['chart-up', 'chart-down', 'target'],
        'files' => ['document', 'folder', 'database'],
        'network' => ['globe', 'cloud'],
    ],

];
