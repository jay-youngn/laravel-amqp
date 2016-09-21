<?php

namespace Gimq\Facades;

use Illuminate\Support\Facades\Facade;

class MQ extends Facade {

	protected static function getFacadeAccessor() {
		return 'mQ';
	}
}
