<?php namespace Terranet\Translatable;

use Illuminate\Support\ServiceProvider;

class TranslatableServiceProvider extends ServiceProvider {

    protected $package = 'terranet/translatable';

    public function boot()
    {
        $this->publishes([
            base_path('vendor/' . $this->package . '/src/config/config.php') => config_path('translatable.php')
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            base_path('vendor/' . $this->package . '/src/config/config.php'),
            'translatable'
        );
    }
}
