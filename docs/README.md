# Laravel Insights

## Instalation

Require package to your composer.json
```bash
composer require matheusfs/laravel-insights
```
Publish config file
```bash
php artisan vendor:publish --provider="MatheusFS\Laravel\Insights\ServiceProvider" --tag="config"
```
Run migrations
```bash
php artisan migrate
```
Add the following blade directive on a base view (to reach all your site pages)
```blade
@record_pageview
```