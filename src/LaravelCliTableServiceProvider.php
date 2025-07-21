<?php

namespace Crumbls\LaravelCliTable;

use Illuminate\Support\ServiceProvider;

class LaravelCliTableServiceProvider extends ServiceProvider
{
	public function register()
	{
		//
	}

	public function boot()
	{
		$this->loadTranslationsFrom(__DIR__.'/../lang', 'cli-table');
		
		$this->publishes([
			__DIR__.'/../lang' => $this->app->langPath('vendor/cli-table'),
		], 'cli-table-lang');
	}
}