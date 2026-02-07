# Publicando Configuração de Emojis

> Guia para publicar `config/emojis.php` em aplicações que usam laravel-insights

---

## O Que É Publicado?

O arquivo `config/emojis.php` define:

```php
return [
    'source' => 'twemoji',                    // Fonte dos emojis
    'size' => 72,                             // Tamanho em pixels
    'directory' => 'resources/emojis',        // Onde armazenar
    'byName' => [ ... ],                      // Mapeamento nome → codepoint
    'groups' => [ ... ],                      // Agrupamentos temáticos
];
```

---

## Como Publicar

### Opção 1: Publicar via Artisan (Recomendado)

```bash
# Publicar configuração
php artisan vendor:publish --provider="MatheusFS\Laravel\Insights\ServiceProvider" --tag=config

# Resultado:
# Publishing complete.
# config/emojis.php
```

**Output esperado:**

O arquivo aparecerá em `config/emojis.php` do seu projeto e poderá ser customizado.

### Opção 2: Registrar Publicação Manualmente

Se o ServiceProvider não registrar automaticamente, adicione em `src/ServiceProvider.php`:

```php
public function boot()
{
    if ($this->app->runningInConsole()) {
        $this->publishes([
            __DIR__.'/../config/emojis.php' => config_path('emojis.php'),
        ], 'config');
    }
    
    // ... resto do boot
}
```

---

## Customizar Emojis

### Adicionar Novo Emoji

1. **Publicar a configuração:**
   ```bash
   php artisan vendor:publish --tag=insights-config
   ```

2. **Editar `config/emojis.php`:**
   ```php
   'byName' => [
       // ... existentes
       'custom_name' => 'unicode_codepoint',
   ],
   ```

3. **Usar no código:**
   ```blade
   <img src="{{ EmojiPath::byName('custom_name') }}" />
   ```

### Trocar Fonte (Twemoji → Noto)

```php
// config/emojis.php
'source' => 'noto',  // Em vez de 'twemoji'
```

Depois baixar emojis Noto:
```bash
# Criar script similar para Noto
bash download_noto_emoji.sh
```

---

## Variáveis de Ambiente

Opcionalmente, customize via `.env`:

```bash
# .env
EMOJI_SOURCE=twemoji      # ou 'noto'
EMOJI_SIZE=72              # Tamanho em pixels
```

E acesse em `config/emojis.php`:

```php
'source' => env('EMOJI_SOURCE', 'twemoji'),
'size' => env('EMOJI_SIZE', 72),
```

---

## Integração com Consumidores

Se você desenvolve um pacote que **usa** laravel-insights, publique a config também:

```php
// Seu ServiceProvider
public function boot()
{
    if ($this->app->runningInConsole()) {
        // Publicar config de emojis para que seja customizável
        $this->publishes([
            __DIR__.'/../config/emojis.php' => config_path('emojis.php'),
        ], 'your-package-config');
    }
}
```

Então o usuário pode:

```bash
php artisan vendor:publish --tag=your-package-config
```

---

## Checklist de Publicação

- [ ] Executado `php artisan vendor:publish` para emojis
- [ ] Arquivo `config/emojis.php` existe em seu projeto
- [ ] Customizações aplicadas conforme necessário
- [ ] Emojis baixados: `bash download_twemoji.sh`
- [ ] Testes passando: `php artisan test`

---

**Referência:** [Laravel Publishing Assets](https://laravel.com/docs/10/packages#publishing-assets)
