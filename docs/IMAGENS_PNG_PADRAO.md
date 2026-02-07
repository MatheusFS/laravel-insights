# Padronização de Imagens PNG - Laravel Insights

## 📋 Resumo Executivo

Este documento padroniza o uso de imagens PNG em todo o projeto `laravel-insights` e garante compatibilidade com DOMPDF 3.1+.

---

## 🎯 Objetivos

1. **Logo Centralizado**: Uma única fonte de verdade para o logo (`assets/icone_regular.png`)
2. **Ícones Dinâmicos**: Geração automática de ícones com cores personalizáveis
3. **Emojis PNG**: Suporte para emojis com licença livre (Twemoji/Noto)
4. **Caminhos Absolutos**: Uso consistente de `file://` protocol para DOMPDF

---

## 🔧 Helpers Disponíveis

### 1. LogoPath Helper

Gerencia o logo centralizado da Continuo Tecnologia.

**Uso:**
```php
use MatheusFS\Laravel\Insights\Helpers\LogoPath;

// Obter caminho absoluto
$path = LogoPath::getPath();

// Obter URI file:// (para DOMPDF)
$uri = LogoPath::getUri();

// Obter como base64 (fallback)
$base64 = LogoPath::getBase64();

// Verificar existência
if (LogoPath::exists()) {
    // ...
}

// Obter dimensões
$dims = LogoPath::dimensions(); // ['width' => 512, 'height' => 512]
```

**Em Blade:**
```blade
@php
    use MatheusFS\Laravel\Insights\Helpers\LogoPath;
    $logoUri = LogoPath::exists() ? LogoPath::getUri() : '';
@endphp

@if($logoUri)
    <img src="{{ $logoUri }}" alt="Logo" style="width: 70px;" />
@endif
```

### 2. IconGenerator Helper

Gera ícones dinâmicos com cores e tipos variados.

**Uso:**
```php
use MatheusFS\Laravel\Insights\Helpers\IconGenerator;

// Obter array com todos os ícones
$icons = IconGenerator::getIconArray();
// Resultado: ['blue_dot' => 'file://...', 'red_warning' => 'file://...', ...]

// Obter ícone específico
$blueDot = IconGenerator::getPath('blue', 'dot');
```

**Cores Suportadas:**
- blue, red, orange, yellow, green, gray, purple, cyan, pink, teal

**Tipos Suportados:**
- dot, square, triangle, check, x, warning, info

### 3. EmojiPath Helper

Gerencia referências a emojis PNG (Twemoji/Noto).

**Uso:**
```php
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

// Obter emoji por unicode codepoint
$checkmark = EmojiPath::get('2714'); // ✔️

// Obter emoji por nome comum
$check = EmojiPath::byName('check');

// Obter como file:// URI (DOMPDF)
$uri = EmojiPath::getUri('2714');

// Obter como base64
$base64 = EmojiPath::getBase64('2714');

// Verificar existência
if (EmojiPath::exists('2714')) {
    // ...
}

// Listar emojis comuns
$common = EmojiPath::common();
// ['check' => '2714', 'warning' => '26a0', ...]
```

---

## 📂 Estrutura de Arquivos

```
laravel-insights/
├── assets/
│   └── icone_regular.png          ← Logo central (USAR EXCLUSIVAMENTE)
├── resources/
│   └── emojis/                     ← Emojis (opcional)
│       ├── twemoji/
│       │   ├── 2714.png           (✔️ Checkmark)
│       │   ├── 274c.png           (❌ Cross)
│       │   └── ...
│       └── noto/
│           ├── 2714.png
│           └── ...
└── src/
    └── Helpers/
        ├── LogoPath.php            ← Logo helper
        ├── IconGenerator.php       ← Ícones dinâmicos
        └── EmojiPath.php          ← Emoji helper
```

---

## 🎨 Protocolo de Imagens para DOMPDF 3.1+

### Recomendação: file:// Protocol

DOMPDF 3.1+ prefere caminhos locais com `file://` protocol:

```html
<!-- ✅ CORRETO (file://) -->
<img src="file:///path/to/logo.png" />

<!-- ⚠️ EVITAR (data-uri longo) -->
<img src="data:image/png;base64,iVBORw0KGgo..." />

<!-- ❌ ERRADO (asset helper) -->
<img src="{{ asset('images/logo.png') }}" />
```

**Por quê?**
- `file://` é o formato nativo do DOMPDF para recursos locais
- Não depende de `enable_remote` ou `allowedProtocols` no config
- Mais rápido que base64 para imagens grandes
- Compatible com `chroot` security setting

### Alternativa: Base64 (Fallback)

Se `file://` falhar, usar base64:

```php
$logoBase64 = LogoPath::getBase64();
// 'data:image/png;base64,iVBORw0KGgo...'
```

---

## 📋 Checklist de Implementação

### Ao criar novo PDF com imagens:

- [ ] Usar `LogoPath::getUri()` para logo
- [ ] Usar `IconGenerator::getIconArray()` para ícones
- [ ] Usar `EmojiPath::getUri()` para emojis (se aplicável)
- [ ] Validar que paths retornam `file://` URIs
- [ ] Testar em DOMPDF 3.1+ (Laravel 10)
- [ ] Verificar que imagens aparecem no PDF gerado

### Ao adicionar novos emojis:

1. Baixar PNG da fonte escolhida (Twemoji/Noto)
2. Armazenar em `resources/emojis/{source}/{codepoint}.png`
3. Usar `EmojiPath::get()` para referenciar
4. Docum entar o codepoint em comentário

---

## 🚀 Exemplos de Uso Completo

### Exemplo 1: PDF com Logo

```blade
@php
    use MatheusFS\Laravel\Insights\Helpers\LogoPath;
@endphp

<html>
<body>
    <header>
        @if(LogoPath::exists())
            <img src="{{ LogoPath::getUri() }}" style="width: 100px;" />
        @endif
    </header>
    <h1>Meu PDF</h1>
</body>
</html>
```

### Exemplo 2: PDF com Ícones Dinâmicos

```php
// No Controller
$icons = \MatheusFS\Laravel\Insights\Helpers\IconGenerator::getIconArray();
return view('pdf.report', compact('icons'));
```

```blade
<!-- No Template -->
<div class="section">
    <h2>
        @if(!empty($icons['blue_info']))
            <img src="{{ $icons['blue_info'] }}" width="12" height="12" />
        @endif
        Informações
    </h2>
</div>
```

### Exemplo 3: Emojis em PDF

```blade
@php
    use MatheusFS\Laravel\Insights\Helpers\EmojiPath;
    EmojiPath::setSource('twemoji'); // Selecionar fonte
@endphp

<p>
    Status:
    @if($isSuccess)
        <img src="{{ EmojiPath::getUri('2714') }}" width="16" height="16" alt="✔️" />
    @else
        <img src="{{ EmojiPath::getUri('274c') }}" width="16" height="16" alt="❌" />
    @endif
</p>
```

---

## 📖 Referências

### DOMPDF 3.1 Documentação
- [Usage](https://github.com/dompdf/dompdf/wiki/Usage)
- [Securing DOMPDF](https://github.com/dompdf/dompdf/wiki/Securing-dompdf)
- [Resource URI Validation](https://github.com/dompdf/dompdf/wiki/Usage#resource-references-and-uri-validation)

### Emoji Sources
- **Twemoji**: https://github.com/twitter/twemoji (MIT License)
- **Noto Color Emoji**: https://github.com/googlei18n/noto-emoji (Apache 2.0 License)

### Laravel Configurations
- Config: `config/dompdf.php`
- Logo: `assets/icone_regular.png` (official)
- Emojis: `resources/emojis/` ou `public/emojis/`

---

## 🔄 Troubleshooting

| Problema | Causa | Solução |
|----------|-------|---------|
| Logo não aparece no PDF | Arquivo não existe ou path inválido | Verificar `LogoPath::exists()` |
| Ícones aparecem como texto | Protocolo não suportado | Usar `file://` em vez de `asset()` |
| Emojis cortados/distorcidos | Tamanho inadequado no HTML | Ajustar `width` e `height` no `<img>` |
| DOMPDF gera erro de acesso | Arquivo fora de `chroot` | Validar `config/dompdf.php` chroot setting |

---

**Atualizado**: 2026-02-07  
**Versão**: 1.0  
**Compatibilidade**: DOMPDF ^3.1, Laravel 10+
