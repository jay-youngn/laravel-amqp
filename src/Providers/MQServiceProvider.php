<?php

namespace Gimq\Providers;

use Illuminate\Support\ServiceProvider;
use Gimq\AMQP;

class MQServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot() {
		$this->publishes([
			__DIR__ . '/../amqp.config.php' => config_path('amqp.php'),
		], 'config');
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register() {
		$this->app->singleton('mQ', function ($app) {
			return new AMQP;
		});
	}

	public function provides() {
		return ['mQ'];
	}
}
