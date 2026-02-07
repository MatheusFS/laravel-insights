# LogoPath - Guia Rápido

## Uso Básico

### Em PDFs (recomendado)

```php
use MatheusFS\Laravel\Insights\Helpers\LogoPath;

// Ícone apenas
$logo = LogoPath::getPdfUri();  // ou getPdfUri('icon')

// Logo para fundo claro (documentos, PDFs brancos)
$logo = LogoPath::getPdfUriLight();  // ou getPdfUri('light')

// Logo para fundo escuro (dark mode, apresentações)
$logo = LogoPath::getPdfUriDark();  // ou getPdfUri('dark')
```

### Em templates Blade

```blade
{{-- PDF/Documento --}}
<img src="{{ \MatheusFS\Laravel\Insights\Helpers\LogoPath::getPdfUriLight() }}" 
     style="max-width: 200px;">

{{-- Dark mode --}}
<img src="{{ \MatheusFS\Laravel\Insights\Helpers\LogoPath::getPdfUriDark() }}" 
     style="max-width: 200px;">
```

### Para uso WEB (file:// protocol)

```php
// Se você precisa do caminho de arquivo (não para PDFs)
$uri = LogoPath::getUri('light');  // file:///path/to/logo_fundo_claro.png
$path = LogoPath::getPath('light'); // /path/to/logo_fundo_claro.png
```

## Variantes Disponíveis

| Variante | Arquivo | Tamanho | Uso |
|----------|---------|---------|-----|
| `icon` | icone_regular.png | 352 KB | Favicon, ícones de app |
| `light` | logo_fundo_claro.png | 226 KB | Documentos, fundos brancos |
| `dark` | logo_fundo_escuro.png | 217 KB | Dark mode, fundos escuros |

## API Completa

```php
// Métodos PDF-optimized (retornam base64)
LogoPath::getPdfUri($variant = 'icon'): string
LogoPath::getPdfUriLight(): string              // Shorthand
LogoPath::getPdfUriDark(): string               // Shorthand

// Métodos file:// (para uso não-PDF)
LogoPath::getUri($variant = 'icon'): string     // file:///path/...
LogoPath::getPath($variant = 'icon'): string    // /path/...
LogoPath::getBase64($variant = 'icon'): string  // data:image/png;base64,...

// Utilitários
LogoPath::exists(): bool
LogoPath::dimensions($variant = 'icon'): ?array // ['width' => int, 'height' => int]
```

## Exemplo Completo: PDF de Incidente

```php
use MatheusFS\Laravel\Insights\Helpers\LogoPath;
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

$data = [
    'logo' => LogoPath::getPdfUriLight(),
    'icons' => EmojiPath::getPdfIconArray(),
    'incident' => $incidentData,
];

return Pdf::loadView('pdf.incident', $data)->download('incident.pdf');
```

```blade
{{-- resources/views/pdf/incident.blade.php --}}
<!DOCTYPE html>
<html>
<body>
    <header>
        <img src="{{ $logo }}" style="max-width: 150px;">
        <h1>Relatório de Incidente</h1>
    </header>
    
    <section>
        <h2><img src="{{ $icons['26a0'] }}" style="width: 24px;"> Alerta</h2>
        <p>{{ $incident['description'] }}</p>
    </section>
</body>
</html>
```

## Performance

- **Icon**: ~480 KB base64 (352 KB PNG)
- **Light**: ~307 KB base64 (226 KB PNG)
- **Dark**: ~295 KB base64 (217 KB PNG)

Base64 adiciona ~33% ao tamanho, mas garante compatibilidade 100% com DOMPDF em qualquer ambiente.

## Troubleshooting

**PDF não mostra logo?**
- ✅ Use `getPdfUri()` ou `getPdfUriLight()` (não `getUri()`)
- ✅ Verifique se o arquivo existe: `LogoPath::exists()`
- ✅ Teste com `test-all-logos.php` no core

**Logo cortado/distorcido?**
```blade
{{-- Adicione max-width para manter proporções --}}
<img src="{{ $logo }}" style="max-width: 200px; height: auto;">
```

**Qual variante usar?**
- 📄 **Documentos/PDFs**: `light`
- 🌙 **Dark mode**: `dark`
- 📱 **Apps/Ícones**: `icon`
