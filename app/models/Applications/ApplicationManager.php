<?php

namespace Applications;

use Symfony\Component\Filesystem\Filesystem;
use Events\ApplicationEvents;
use Events\Application\ApplicationCreatedEvent;



/**
 * Description of ApplicationManager
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class ApplicationManager extends \BaseManager
{

	/**
	 * @param string $name
	 * @throws ExistingApplicationException
	 */
	public function createApplication($name)
	{
		$application = new \Application($name);

		$this->dm->persist($application);
		try {
			$this->dm->flush();
			$event = new ApplicationCreatedEvent($application);
			$this->mediator->dispatch(ApplicationEvents::APPLICATION_CREATED, $event);
		} catch (\MongoCursorException $e) {
			throw new ExistingApplicationException(sprintf('Application with name: %s already exists', $name));
		}
	}


	/**
	 * @return \Application[]
	 */
	public function listApplications()
	{
		return $this->dm->getRepository('Application')->findAll();
	}


	/**
	 * @param string $id
	 * @return \Application
	 */
	public function loadApplication($id)
	{
		return $this->dm->getRepository('Application')->find($id);
	}


	/**
	 * @param string $name
	 * @return \Application
	 */
	public function loadApplicationByRepoName($repoName)
	{
		return $this->dm->getRepository('Application')->findOneBy(['repoName' => $repoName]);
	}


	public function updateSettings(\Application $application, array $settings)
	{
		$application->setSettings($settings);

		$this->dm->persist($application);
		$this->dm->flush();
	}


	public function getReleaseHistory(\Application $application)
	{
		$qb = $this->dm->getRepository('Release')->createQueryBuilder();
		$qb->field('application.id')->equals($application->getId())
				->sort('date', 'desc');
		return $qb->getQuery()->execute();
	}


	public function getRelease(\Application $application, $releaseId)
	{
		return $this->dm->getRepository('Release')->findOneBy(['id' => $releaseId]);

		$qb = $this->dm->getRepository('Release')->createQueryBuilder();
		$qb->field('application.id')->equals($application->getId())
				->field('id')->equals($releaseId);

		return $qb->getQuery()->execute()->getNext();
	}


}
