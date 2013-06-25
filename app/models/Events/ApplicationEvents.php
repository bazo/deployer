<?php

namespace Events;

/**
 * Description of ApplicationEvents
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
abstract class ApplicationEvents
{
	const APPLICATION_CREATED = 'application.created';
	const DEPLOY_STARTED = 'application.deploy.started';
	const DEPLOY_FINISHED = 'application.deploy.finished';
}

