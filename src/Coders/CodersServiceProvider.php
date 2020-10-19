<?php

namespace MartianAtWork\Coders;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use MartianAtWork\Coders\Console\CodeModelsCommand;
use MartianAtWork\Coders\Model\Config;
use MartianAtWork\Coders\Model\Factory as JsonApiFactory;
use MartianAtWork\Support\Classify;

class CodersServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/json_api_generator.php' => config_path('json_api_generator.php'),
            ], 'MartianAtWork-models');
            $this->mergeConfigFrom(realpath(__DIR__.'/../../config/json_api_generator.php'), 'json_api_generator');
            $this->commands([
                CodeModelsCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register() {
        $this->registerModelFactory();
    }

    /**
     * Register Model Factory.
     *
     * @return void
     */
    protected function registerModelFactory() {
        $this->app->singleton(JsonApiFactory::class, function ($app) {
            return new JsonApiFactory(
                $app->make('db'),
                $app->make(Filesystem::class),
                new Classify(),
                new Config($app->make('config')->get('json_api_generator'))
            );
        });
    }

    /**
     * @return array
     */
    public function provides() {
        return [ModelFactory::class];
    }
}
