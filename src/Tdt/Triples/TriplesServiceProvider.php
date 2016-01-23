<?php

namespace Tdt\Triples;

use Illuminate\Support\ServiceProvider;
use Tdt\Triples\Commands\CacheTriples;

class TriplesServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('tdt/triples');

        include __DIR__ . '/../../routes.php';
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'Tdt\Triples\Repositories\Interfaces\SemanticSourceRepositoryInterface',
            'Tdt\Triples\Repositories\SemanticSourceRepository'
        );

        $this->app->bind(
            'Tdt\Triples\Repositories\Interfaces\TurtleSourceRepositoryInterface',
            'Tdt\Triples\Repositories\TurtleSourceRepository'
        );

        $this->app->bind(
            'Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface',
            'Tdt\Triples\Repositories\ARC2\TripleRepository'
        );

        $this->app->bind(
            'Tdt\Triples\Repositories\Interfaces\RdfSourceRepositoryInterface',
            'Tdt\Triples\Repositories\RdfSourceRepository'
        );

        $this->app->bind(
            'Tdt\Triples\Repositories\Interfaces\SparqlSourceRepositoryInterface',
            'Tdt\Triples\Repositories\SparqlSourceRepository'
        );

        $this->app->bind(
            'Tdt\Triples\Repositories\Interfaces\LdfSourceRepositoryInterface',
            'Tdt\Triples\Repositories\LdfSourceRepository'
        );

        $this->app['triples.load'] = $this->app->share(function ($app) {
            return new CacheTriples();
        });

        $this->commands('triples.load');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }
}
