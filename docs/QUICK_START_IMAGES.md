# Quick Start: Usando Imagens PNG em PDFs

> Guia rápido para usar logo, emojis e ícones em templates de PDF com DOMPDF 3.1+

---

## 1. Logo da Empresa (Continuo Tecnologia)

### Uso em Blade
```blade
@use('MatheusFS\Laravel\Insights\Helpers\LogoPath')

<!-- Verificar existência -->
@if(LogoPath::exists())
    <div style="text-align: center;">
        <img 
            src="{{ LogoPath::getUri() }}" 
            alt="Continuo Tecnologia"
            style="max-width: 150px; height: auto;"
        />
    </div>
@endif
```

### Uso em PHP
```php
use MatheusFS\Laravel\Insights\Helpers\LogoPath;

// Retorna: file:///absolute/path/assets/icone_regular.png
$logoUri = LogoPath::getUri();

// Retorna: /absolute/path/assets/icone_regular.png
$logoPath = LogoPath::getPath();

// Retorna: [width, height] em pixels
[$width, $height] = LogoPath::dimensions();

// Base64 (fallback se DOMPDF faihar)
$base64 = LogoPath::getBase64(); // data:image/png;base64,...
```

---

## 2. Emojis PNG (Twemoji)

### Primeiros Passos

```bash
# 1. Baixar emojis do Twemoji (15 emojis comuns)
bash download_twemoji.sh

# 2. Verificar se foram baixados
ls -la resources/emojis/twemoji/
# Output:
# 2714.png    (✔️ Checkmark)
# 274c.png    (❌ Cross)
# 26a0.png    (⚠️ Warning)
# ... (12 mais)
```

### Uso em Blade
```blade
@use('MatheusFS\Laravel\Insights\Helpers\EmojiPath')

<!-- Por nome comum -->
<img 
    src="{{ EmojiPath::byName('check') }}" 
    alt="✓ Approved"
    style="width: 16px; height: 16px;"
/>

<!-- Por codepoint Unicode -->
<img 
    src="{{ EmojiPath::getUri('2714') }}" 
    alt="✓"
    style="width: 16px;"
/>
```

### Uso em PHP
```php
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

// Por nome (mais legível)
$checkUri = EmojiPath::byName('check');      // ✔️
$warnUri = EmojiPath::byName('warning');     // ⚠️
$failUri = EmojiPath::byName('fail');        // ❌

// Por codepoint (mais preciso)
$customUri = EmojiPath::getUri('2714');      // ✔️

// Listar emojis disponíveis
$common = EmojiPath::common(); // Array de codepoints

// Usar em PDF (exemplo)
$status = $isSuccess 
    ? EmojiPath::byName('check')
    : EmojiPath::byName('fail');
```

### Emojis Disponíveis (Padrão)
```php
'check'   => '2714',   // ✔️ Checkmark
'fail'    => '274c',   // ❌ Cross
'warning' => '26a0',   // ⚠️ Warning Sign
'info'    => '2139',   // ℹ️ Information
'fire'    => '1f525',  // 🔥 Fire
'clock'   => '1f550',  // 🕐 Clock
'dot'     => '2b55',   // 🔵 Blue Circle
'star'    => '2b50',   // ⭐ Star
'ok'      => '1f44c',  // 👌 OK Hand
'no'      => '1f44e',  // 👎 Thumbs Down
'yes'     => '1f44d',  // 👍 Thumbs Up
'alert'   => '1f6a8',  // 🚨 Alert
'speed'   => '1f4a8',  // 💨 Dashing Away
'perfect' => '1f4af',  // 💯 100 Points
'check2'  => '2705',   // ✅ Check Mark Button
```

---

## 3. Ícones do PDF (Emojis Coloridos)

### Consolidação: EmojiPath::getIconArray()

Os ícones do PDF foram consolidados em EmojiPath. Não use mais IconGenerator.

```blade
@php
    use MatheusFS\Laravel\Insights\Helpers\EmojiPath;
    $icons = EmojiPath::getIconArray();
@endphp

<!-- Retorna 5 ícones padrão do PDF -->
@if(!empty($icons['blue_info']))
    <img src="{{ $icons['blue_info'] }}" alt="Info" style="width: 12px; margin-right: 4px;" />
@endif

@if(!empty($icons['red_dot']))
    <img src="{{ $icons['red_dot'] }}" alt="Error" style="width: 12px; margin-right: 4px;" />
@endif

@if(!empty($icons['green_check']))
    <img src="{{ $icons['green_check'] }}" alt="Success" style="width: 12px; margin-right: 4px;" />
@endif
```

### Uso em PHP

```php
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

// Retorna array com 5 ícones do PDF (consolidou IconGenerator)
$icons = EmojiPath::getIconArray();
// [
//     'blue_info' => 'file://...emoji/2139.png',
//     'blue_dot' => 'file://...emoji/1f535.png',
//     'red_dot' => 'file://...emoji/1f534.png',
//     'orange_warning' => 'file://...emoji/26a0.png',
//     'green_check' => 'file://...emoji/2705.png',
// ]

// Verificar se ícone existe
if (!empty($icons['blue_info'])) {
    echo '<img src="' . $icons['blue_info'] . '" />';
}

// Acessar emoji específico por nome
$checkIcon = EmojiPath::byName('green_check');
```

---

## 4. Exemplo Completo em Template PDF

```blade
<!-- resources/views/pdf/incidents/receipt_v2.blade.php -->
@use('MatheusFS\Laravel\Insights\Helpers\LogoPath')
@use('MatheusFS\Laravel\Insights\Helpers\EmojiPath')

<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .status { display: flex; align-items: center; gap: 8px; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; }
    </style>
</head>
<body>
    <!-- Logo Header -->
    <div class="header">
        @if(Logo::exists())
            <img src="{{ Logo::getUri() }}" alt="Continuo" style="max-width: 100px;" />
        @endif
        <h1>Incident Report</h1>
    </div>

    <!-- Status com Emoji -->
    <div class="status">
        <img src="{{ Emoji::byName($incident->is_resolved ? 'check' : 'warning') }}" 
             alt="Status" 
             style="width: 20px;" />
        <strong>Status:</strong> 
        {{ $incident->is_resolved ? 'Resolved' : 'Pending' }}
    </div>

    <!-- Tabela com Indicadores -->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td>CPU Usage</td>
            <td>
                <img src="{{ Icon::getIcon('dot', $cpuColor) }}" style="width: 16px;" />
                {{ $cpuUsage }}%
            </td>
        </tr>
        <tr>
            <td>Memory</td>
            <td>
                <img src="{{ Icon::getIcon('dot', $memColor) }}" style="width: 16px;" />
                {{ $memUsage }}%
            </td>
        </tr>
    </table>

    <!-- Badges -->
    <div style="margin-top: 20px;">
        @foreach($incidents as $inc)
            <span class="badge">
                <img src="{{ Emoji::byName('alert') }}" style="width: 14px;" />
                {{ $inc->name }}
            </span>
        @endforeach
    </div>
</body>
</html>
```

---

## 5. Configuração DOMPDF (Já Feita)

O arquivo `config/dompdf.php` já está configurado corretamente:

```php
'chroot' => realpath(base_path()), // Permite file:// access
'enable_remote' => false,           // Bloqueia URLs remotas (por segurança)
```

**Não requer mudanças.** Os helpers já usam o protocolo correto.

---

## 6. Adicionar Mais Emojis

### Customizar Lista de Download
```bash
# Editar download_twemoji.sh
nano download_twemoji.sh

# Modificar array EMOJI_CODEPOINTS com novos valores:
EMOJI_CODEPOINTS=(
    "2714"      # ✔️
    "274c"      # ❌
    "1f4a1"     # 💡 Lightbulb (nova)
    "1f49a"     # 💚 Green Heart (nova)
)

# Executar
bash download_twemoji.sh
```

### Usar Novo Emoji em Código
```blade
<!-- Adicionar mapeamento em EmojiPath::byName() -->
'idea' => '1f4a1',
'heart' => '1f49a',

<!-- Usar -->
<img src="{{ Emoji::byName('idea') }}" />
```

---

## 7. Troubleshooting

| Problema | Solução |
|----------|---------|
| Imagem não aparece no PDF | Verificar: `LogoPath::exists()` retorna true? |
| Base64 em vez de arquivo | DOMPDF usando fallback (tudo bem, válido) |
| Emoji não encontrado | Rodar `bash download_twemoji.sh` novamente |
| Permissão negada em resources/ | Corrigir: `chmod 755 resources/emojis/` |
| Arquivo corrompido | Deletar `resources/emojis/` e redownload |

---

## 8. Recursos

- **DOMPDF Docs:** https://github.com/dompdf/dompdf/wiki/
- **Twemoji:** https://github.com/twitter/twemoji
- **Emoji Codepoints:** https://unicode.org/emoji/charts/full-emoji-list.html
- **Laravel Helpers Pattern:** https://laravel.com/docs/helpers

---

## 9. Próximas Etapas

1. ✅ Download emojis: `bash download_twemoji.sh`
2. ✅ Testar em template: Use exemplos acima em receipt_v2.blade.php
3. ✅ Gerar PDF: `php artisan insights:generate-pdf {incident_id}`
4. ✅ Validar render: Abrir PDF e verificar imagens aparecem
5. 📝 Customizar: Adicionar mais emojis conforme necessidade

---

**Versão:** 1.0 | **Última Atualização:** 2026-02
