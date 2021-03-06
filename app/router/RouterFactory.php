<?php

namespace App;

use Nette\Application\Routers\RouteList,
	Nette\Application\Routers\Route;


/**
 * Router factory.
 */
class RouterFactory
{

	/**
	 * @return \Nette\Application\IRouter
	 */
	public function createRouter()
	{
		$router = new RouteList();
		$router[] = new Route('index.php', 'Applications:default', Route::ONE_WAY);
		$router[] = new Route('application[/<action>][/<id>][/<release_id>]', [
			'presenter' => 'Application',
		]);
		$router[] = new Route('<presenter>[/<action>][/<id>]', [
			'presenter' => 'Applications',
			'action' => 'default'
		]);
		return $router;
	}

}
