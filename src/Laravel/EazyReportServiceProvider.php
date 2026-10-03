<?php

namespace EazyReport\Laravel;

use EazyReport\EazyReport;
use EazyReport\ReportBuilder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class EazyReportServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/eazyreport.php', 'eazyreport');

        $this->app->singleton('eazyreport', function ($app) {
            return new class {
                public function load(string $templateOrPath): ReportBuilder
                {
                    return EazyReport::load($templateOrPath);
                }

                public function fromArray(array $data): ReportBuilder
                {
                    return EazyReport::fromArray($data);
                }

                public function renderHtml(string $templateOrPath, mixed $data = null, array $parameters = [], array $options = []): string
                {
                    return EazyReport::renderHtml($templateOrPath, $data, $parameters, $options);
                }

                public function renderPdf(string $templateOrPath, mixed $data = null, array $parameters = [], array $options = []): string
                {
                    return EazyReport::renderPdf($templateOrPath, $data, $parameters, $options);
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/eazyreport.php' => function_exists('config_path') ? config_path('eazyreport.php') : 'config/eazyreport.php',
            ], 'eazyreport-config');
        }

        // Register custom Blade directive: @eazyreport($template, $data, $params)
        if (class_exists(Blade::class)) {
            Blade::directive('eazyreport', function ($expression) {
                return "<?php echo \\EazyReport\\EazyReport::renderHtml({$expression}); ?>";
            });
        }
    }
}
