# Sistema de Imagens PNG em PDFs - Laravel Insights

> Sistema padronizado e reutilizável para usar logo, emojis e ícones em PDFs com DOMPDF 3.1+

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Quick Start](#quick-start)
4. [Referência de Helpers](#referência-de-helpers)
5. [Estrutura de Diretórios](#estrutura-de-diretórios)
6. [Configuração](#configuração)
7. [Troubleshooting](#troubleshooting)
8. [Referências](#referências)

---

## Visão Geral

### Problema
Renderizar PNG em PDFs DOMPDF 3.1+ requer compreensão de protocolos e configurações específicas. Paths absolutos, data-URIs e URLs remotas têm comportamentos diferentes.

### Solução
**Dois helper classes** que abstraem a complexidade:

| Helper | Propósito | Exemplos |
|--------|-----------|----------|
| **LogoPath** | Centraliza logo da empresa | `LogoPath::getUri()` → file:// URI |
| **EmojiPath** | Emojis PNG por nome/codepoint + ícones do PDF | `EmojiPath::byName('check')` → ✔️ ou `EmojiPath::getIconArray()` → 5 ícones PDF |

### Benefícios
✅ Single source of truth para cada recurso
✅ Fallbacks automáticos (file:// → base64)
✅ DOMPDF compatible out-of-box
✅ Reutilizável em todos os templates
✅ Testável unitariamente
✅ EmojiPath consolidou IconGenerator para simplificar

---

## Arquitetura

### Stack
- **Framework:** Laravel 10
- **PDF:** barryvdh/laravel-dompdf ^3.1 (usa Dompdf 2.0+)
- **Emojis:** Twemoji (MIT) ou Noto Color Emoji (Apache 2.0)
- **Configuração:** config/emojis.php + config/dompdf.php

### Fluxo de Renderização

```
Template Blade
    ↓
Helper Class (LogoPath/EmojiPath/IconGenerator)
    ↓
Resolver Caminho (arquivo existe?)
    ↓
Gerar URL (file:// protocol)
    ↓
DOMPDF Renderiza
    ↓
PDF com Imagens
```

### Protocolos Suportados

| Protocolo | Suporte | Recomendação | Notas |
|-----------|---------|--------------|-------|
| `file://` | ✅ Nativo | **RECOMENDADO** | Sem permissões remotas, rápido |
| `data://` | ✅ Via base64 | Fallback | Maior tamanho, lento em muitas imagens |
| `http://` | ❌ Bloqueado | N/A | Requer `enable_remote: true` (inseguro) |
| `https://` | ❌ Bloqueado | N/A | Requer `enable_remote: true` (inseguro) |

---

## Quick Start

### 1. Setup Inicial

```bash
# Baixar emojis Twemoji
bash download_twemoji.sh

# Verificar que foram baixados
ls -la resources/emojis/twemoji/
# Output: 2714.png, 274c.png, 26a0.png, ...
```

### 2. Usar em Template

```blade
@use('MatheusFS\Laravel\Insights\Helpers\LogoPath', 'Logo')
@use('MatheusFS\Laravel\Insights\Helpers\EmojiPath', 'Emoji')

<img src="{{ Logo::getUri() }}" alt="Logo" style="max-width: 100px;" />

<div>
    <img src="{{ Emoji::byName('check') }}" style="width: 16px;" />
    <span>Approved</span>
</div>
```

### 3. Testar em PDF

```bash
# Gerar PDF (exemplo - ajuste conforme seu projeto)
php artisan insights:generate-incident-pdf {id}

# Verificar que imagens aparecem
```

---

## Referência de Helpers

### LogoPath

Centraliza o logo da Continuo Tecnologia (`assets/icone_regular.png`).

#### Métodos

```php
use MatheusFS\Laravel\Insights\Helpers\LogoPath;

// Retorna: file:///absolute/path/assets/icone_regular.png
$uri = LogoPath::getUri();

// Retorna: /absolute/path/assets/icone_regular.png
$path = LogoPath::getPath();

// Retorna: [width: 256, height: 256]
[$width, $height] = LogoPath::dimensions();

// Retorna: true/false
$exists = LogoPath::exists();

// Retorna: data:image/png;base64,...
$base64 = LogoPath::getBase64();

// Retorna: true se arquivo foi modificado
$isModified = LogoPath::isModified();
```

#### Uso em Blade

```blade
@use('MatheusFS\Laravel\Insights\Helpers\LogoPath')

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

---

### EmojiPath

Gerencia emojis PNG (Twemoji/Noto).

#### Métodos

```php
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

// Por nome (mais legível)
$uri = EmojiPath::byName('check');      // ✔️ Checkmark
$uri = EmojiPath::byName('warning');    // ⚠️ Warning
$uri = EmojiPath::byName('fail');       // ❌ Fail

// Por codepoint Unicode
$uri = EmojiPath::getUri('2714');       // ✔️ (codepoint hex)

// Base64 (fallback)
$base64 = EmojiPath::getBase64('2714');

// Listar emojis disponíveis
$emojis = EmojiPath::common();          // ['check' => '2714', ...]

// Trocar fonte
EmojiPath::setSource('noto');           // Usar Noto em vez de Twemoji

// Verificar existência
$exists = EmojiPath::exists('2714');    // true/false
```

#### Emojis Disponíveis

Veja `config/emojis.php` para lista completa. Alguns comuns:

```
'check'    => '2714'  (✔️)
'fail'     => '274c'  (❌)
'warning'  => '26a0'  (⚠️)
'info'     => '2139'  (ℹ️)
'fire'     => '1f525' (🔥)
'alert'    => '1f6a8' (🚨)
'star'     => '2b50'  (⭐)
'yes'      => '1f44d' (👍)
```

#### Uso em Blade

```blade
@use('MatheusFS\Laravel\Insights\Helpers\EmojiPath')

<!-- Por nome -->
<img src="{{ EmojiPath::byName('check') }}" style="width: 16px;" />

<!-- Com fallback -->
@if($status === 'success')
    <img src="{{ EmojiPath::byName('check') }}" />
@else
    <img src="{{ EmojiPath::byName('warning') }}" />
@endif

<!-- Em loop -->
@foreach(EmojiPath::common() as $name => $codepoint)
    <img src="{{ EmojiPath::getUri($codepoint) }}" title="{{ $name }}" />
@endforeach
```

---

### EmojiPath - Ícones do PDF

EmojiPath::getIconArray() retorna os 5 ícones do PDF (consolidou IconGenerator):

```php
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

// Retorna ícones do PDF de forma consistente
$icons = EmojiPath::getIconArray();
// [
//     'blue_info' => 'file://...emoji/2139.png',      // ℹ️
//     'blue_dot' => 'file://...emoji/1f535.png',      // 🔵
//     'red_dot' => 'file://...emoji/1f534.png',       // 🔴
//     'orange_warning' => 'file://...emoji/26a0.png', // ⚠️
//     'green_check' => 'file://...emoji/2705.png',    // ✅
// ]

// Usar no template
@if(!empty($icons['blue_info']))
    <img src="{{ $icons['blue_info'] }}" width="11" alt="Info" />
@endif

// Customizar via byName (acesso por nome)
$checkIcon = EmojiPath::byName('green_check');    // Path to 2705.png
$warningIcon = EmojiPath::byName('orange_warning'); // Path to 26a0.png

// Verificar se emoji existe
if (EmojiPath::exists('2705')) {
    // ...
}
```

#### Ícones PDF Mapeados (via common())

| Ícone | Codepoint | Emoji |
|-------|-----------|-------|
| blue_info | 2139 | ℹ️ |
| blue_dot | 1f535 | 🔵 |
| red_dot | 1f534 | 🔴 |
| orange_warning | 26a0 | ⚠️ |
| green_check | 2705 | ✅ |

#### Cores Disponíveis

```
red, orange, yellow, green, blue, purple, pink, gray, black, white
```

#### Uso em Blade

```blade
@use('MatheusFS\Laravel\Insights\Helpers\IconGenerator', 'Icon')

<!-- Status simples -->
<img src="{{ Icon::getIcon('dot', $statusColor) }}" style="width: 16px;" />

<!-- Com legenda -->
@foreach(['red', 'orange', 'yellow', 'green'] as $color)
    <img src="{{ Icon::getIcon('dot', $color) }}" />
@endforeach
```

---

## Estrutura de Diretórios

```
laravel-insights/
├── assets/
│   ├── icone_regular.png           ← Logo único (centralizador)
│   └── ... (outros assets)
├── resources/
│   └── emojis/
│       ├── twemoji/                ← Emojis PNG (Twemoji)
│       │   ├── 2714.png            (✔️ Checkmark)
│       │   ├── 274c.png            (❌ Cross)
│       │   ├── 26a0.png            (⚠️ Warning)
│       │   ├── 1f534.png           (🔴 Red dot)
│       │   ├── 2705.png            (✅ Green check)
│       │   └── ... (mais)
│       └── noto/                   ← Alternativa: Noto Emoji
│           └── ... (não baixado por padrão)
├── config/
│   ├── dompdf.php                  ← Configuração DOMPDF
│   └── emojis.php                  ← Mapeamento de nomes/codepoints
├── src/
│   ├── Helpers/
│   │   ├── LogoPath.php            ← Logo helper
│   │   └── EmojiPath.php           ← Emoji + Ícones PDF helper
│   └── ...
├── download_twemoji.sh             ← Script para baixar
├── QUICK_START_IMAGES.md           ← Guia rápido
└── ...
```

---

## Configuração

### config/emojis.php

```php
return [
    'source' => 'twemoji',           // ou 'noto'
    'size' => 72,                    // pixels
    'directory' => 'resources/emojis',
    
    'byName' => [
        'check' => '2714',
        'fail' => '274c',
        // ... (mais)
    ],
    
    'groups' => [
        'status' => ['check', 'fail', 'warning'],
        'urgent' => ['fire', 'alert', 'clock'],
        // ... (mais)
    ],
];
```

### config/dompdf.php

```php
return [
    'chroot' => realpath(base_path()),  // ✅ Permite file://
    'enable_remote' => false,            // ✅ Bloqueia http://
    // ... (outras opções)
];
```

**Não requer mudanças** - já está configurado corretamente!

---

## Troubleshooting

### Logo não aparece no PDF

**Sintomas:**
- Imagem exibe como texto quebrado (alt)
- Ou desaparece completamente

**Diagnóstico:**

```php
// 1. Verificar arquivo existe
dd(LogoPath::exists()); // true/false

// 2. Verificar path
dd(LogoPath::getPath());

// 3. Verificar URI
dd(LogoPath::getUri());

// 4. Gerar base64 (fallback)
dd(LogoPath::getBase64());
```

**Soluções:**
1. Verificar permissões: `chmod 644 assets/icone_regular.png`
2. Verificar path em `config/dompdf.php` (chroot)
3. Usar base64 como fallback temporário
4. Consultar logs do DOMPDF em `storage/logs/`

---

### Emoji não encontrado

**Sintomas:**
- `EmojiPath::byName('check')` retorna null
- Imagem não carrega no PDF

**Diagnóstico:**

```php
// 1. Verificar se emojis foram baixados
dd(EmojiPath::common());        // Lista de nomes

// 2. Verificar arquivo existe
dd(EmojiPath::exists('2714'));  // true/false

// 3. Verificar diretório
dd(file_exists('resources/emojis/twemoji/')); // true/false
```

**Solução:**

```bash
# Redownload emojis
bash download_twemoji.sh

# Ou adicionar emoji manualmente
cp ~/Downloads/2714.png resources/emojis/twemoji/
```

---

### IconGenerator não gera ícones

**Sintomas:**
- `IconGenerator::getIcon()` retorna null
- `storage/app/pdf-icons/` vazio

**Diagnóstico:**

```php
dd(IconGenerator::getIconArray()); // Array vazio?
dd(IconGenerator::exists('dot', 'red')); // false?
```

**Solução:**

```bash
# Gerar ícones manualmente (força regen)
php artisan insights:generate-icons

# Limpar cache
rm -rf storage/app/pdf-icons/*
php artisan cache:clear
```

---

### Imagem aparece como Base64 em vez de arquivo

**Sintomas:**
- PDF gerado mas com imagens base64 (funciona, mas lento)

**Causa:**
- `file://` protocol falhou (fallback ativo)
- DOMPDF usando base64 como mecanismo seguro

**Solução:**
1. Verificar permissões: `ls -l assets/icone_regular.png`
2. Verificar chroot: `realpath(base_path())`
3. Adicionar logging em LogoPath para debug

---

## Referências

- **DOMPDF 3.1:** https://github.com/dompdf/dompdf/wiki/
- **Twemoji:** https://github.com/twitter/twemoji (MIT License)
- **Noto Emoji:** https://github.com/googlei18n/noto-emoji (Apache 2.0)
- **Unicode Emojis:** https://unicode.org/emoji/charts/full-emoji-list.html
- **Laravel Docs:** https://laravel.com/docs/10

---

## Próximas Etapas

1. ✅ Executar `bash download_twemoji.sh`
2. ✅ Usar helpers em templates (ver QUICK_START_IMAGES.md)
3. ✅ Gerar PDFs e validar renderização
4. 📝 Adicionar mais emojis conforme necessário
5. 📝 Customizar ícones se necessário

---

**Versão:** 1.0
**Última Atualização:** 2026-02
**Mantido por:** Technical Agent
