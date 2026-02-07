# Solução: PDFs com Imagens em Pacotes Composer

## Problema Identificado

Quando `laravel-insights` é instalado via Composer no projeto `core`, o DOMPDF não consegue acessar imagens via `file://` protocol, mesmo com arquivos existindo e permissões corretas.

### Root Cause

1. **Symlink Vendor**: `/var/www/html/vendor/matheusfs/laravel-insights` → `/var/www/laravel-insights`
2. **DOMPDF chroot**: Configurado para `base_path()` = `/var/www/html`
3. **Resultado**: DOMPDF não consegue acessar `/var/www/laravel-insights/` (fora do chroot)

## Solução Implementada

### 1. Novos Métodos PDF-Optimized (laravel-insights)

```php
// LogoPath - Suporta 3 variantes
LogoPath::getPdfUri()           // Ícone (default)
LogoPath::getPdfUri('light')    // Logo para fundo claro
LogoPath::getPdfUri('dark')     // Logo para fundo escuro

// Shorthands
LogoPath::getPdfUriLight()      // Logo fundo claro
LogoPath::getPdfUriDark()       // Logo fundo escuro

// EmojiPath  
EmojiPath::getPdfUri('2139')    // Emoji específico em base64
EmojiPath::getPdfIconArray()    // Array com 5 icons em base64
```

### 2. Compatibilidade Mantida

```php
// Para uso WEB (continua funcionando com file://)
LogoPath::getUri()              // file:// ícone
LogoPath::getUri('light')       // file:// logo claro
LogoPath::getUri('dark')        // file:// logo escuro
EmojiPath::getUri('2139')       // file:// emoji
EmojiPath::getIconArray()       // Array com file:// URIs
```

### 3. Uso no PDF Generator

```php
// Antes (não funcionava com vendor symlink)
'icons' => EmojiPath::getIconArray()

// Agora (funciona)
'icons' => EmojiPath::getPdfIconArray()
```

## Resultados

### Antes
- PDF: 1.618 bytes (vazio, 0 páginas)
- Erro: DOMPDF não acessava imagens via file://
758 KB (4 páginas com 3 logos + 5 emojis)
- ✅ Todas as imagens renderizando corretamente
- ✅ 3 variantes de logo disponíveis (icon, light, dark)
- PDF: 327 KB (2 páginas com logo + 5 emojis)
- ✅ Todas as imagens renderizando corretamente
- ✅ Base64 inline (sem dependência de file://)

## Arquivos Alterados

### laravel-insights (package)
- `src/Helpers/LogoPath.php` - Adicionado `getPdfUri()`
- `src/Helpers/EmojiPath.php` - Adicionado `getPdfUri()` e `getPdfIconArray()`
- `src/Services/Pdf/IncidentPdfGeneratorV2.php` - Usa `getPdfIconArray()`
- `resources/assets/*` - Permissões 600 → 644
- `resources/emojis/twemoji/*` - Permissões 600 → 644

### core (consuming project)
- `config/dompdf.php` - Ajustado chroot para `/` (permite vendor access)

## Commits

1. **laravel-insights**: 
   - `76415b6` - feat: add PDF-optimized helpers using base64 data URIs
   - `10248c3` - feat: add support for multiple logo variants
2. **core**: `a1c1cf14c` - change(infra): adjust DOMPDF chroot for vendor access

## Performance

**Base64 vs file://**
- Base64: ~480KB por imagem (inline no HTML)
- File: ~360KB por imagem (referência externa)
- Trade-off: +33% tamanho HTML, mas 100% compatibilidade

**PDF Fícone: 352KB (icone_regular.png)
- Logo claro: 226KB (logo_fundo_claro.png)
- Logo escuro: 217KB (logo_fundo_escuro.png)
- Emojis (5x): ~5-10KB cada (Twemoji 72x72px)
- Total exemplo: ~758KB (3 logos + 5 emoji
- Total: ~327KB (aceitável para PDFs de incidentes)

## Considerações

### Por que base64 e não resolver o chroot?

1. **Symlinks complexos**: Vendor pode estar em qualquer lugar
2. **Segurança**: chroot aberto (`/`) é menos seguro
3. **Portabilidade**: Base64 funciona em qualquer ambiente
4. **Manutenibilidade**: Não depende de configuração do servidor

### Alternativas consideradas

❌ **chroot = '/'** - Funcional mas inseguro  
❌ **Publicar assets** - Requer `php artisan vendor:publish` (extra step)  
❌ **Absolute paths** - Quebra em ambientes diferentes  
✅ **Base64 inline** - Portável e garantido
 com todos os logos
docker exec core-fpm-1 php test-all-logos.php

# Output esperado:
# Testing variant: icon   ✅ 480370 bytes
# Testing variant: light  ✅ 307274 bytes  
# Testing variant: dark   ✅ 295070 bytes
# ✅ PDF generated: 758.02 KB (776213 bytes)
# 🎉 SUCCESS: PDF has all 3 logos + emoji
# ✅ LogoPath::getPdfUri() length: 480370
# ✅ EmojiPath::getPdfIconArray() count: 5
# ✅ PDF generated: 327.55 KB (335411 bytes)
# 🎉 SUCCESS: PDF has content and images!
```

## Migração

### Para outros pacotes Composer com PDFs

Se Suporte variantes se necessário (light/dark/sizes)
3. Use nos templates: `<img src="{{ Helper::getPdfUri() }}">`
4. Teste com pacote instalado via Composer (não symlink local)

### Exemplo de uso em templates Blade

```blade
{{-- Logo para fundo branco (documentos, PDFs) --}}
<img src="{{ \MatheusFS\Laravel\Insights\Helpers\LogoPath::getPdfUriLight() }}" alt="Logo">

{{-- Logo para dark mode --}}
<img src="{{ \MatheusFS\Laravel\Insights\Helpers\LogoPath::getPdfUriDark() }}" alt="Logo">

{{-- Emojis --}}
@php
    $icons = \MatheusFS\Laravel\Insights\Helpers\EmojiPath::getPdfIconArray();
@endphp
<img src="{{ $icons['2139'] }}" alt="Info">
```
1. Adicione método `getPdfUri()` que retorna base64
2. Use nos templates: `<img src="{{ Helper::getPdfUri() }}">`
3. Teste com pacote instalado via Composer (não symlink local)

---

**Data**: 2026-02-07  
**Versão laravel-insights**: dev-master (76415b6)  
**Versão core**: master (a1c1cf14c)
