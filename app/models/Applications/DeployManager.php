<?php

namespace Applications;

use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Output\ConsoleOutput;
use GitWrapper\GitWrapper;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Description of DeployManager
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class DeployManager extends \BaseManager
{

	/** @var string */
	private $releasesDir;

	/** @var string */
	private $repostioriesDir;

	/** @var Filesystem */
	private $fs;

	/** @var ConsoleOutput */
	private $output;

	/** @var GitWrapper */
	private $git;


	public function __construct($releasesDir, $repositoriesDir, DocumentManager $dm, EventDispatcher $mediator, Filesystem $fs, ConsoleOutput $output, GitWrapper $git)
	{
		parent::__construct($dm, $mediator);
		$this->releasesDir = $releasesDir;
		$this->repostioriesDir = $repositoriesDir;
		$this->fs = $fs;
		$this->output = $output;
		$this->git = $git;
	}

	/**
	 * @param type $path
	 * @throws IOException
	 */
	public function prepareFoldersForDeploy($path)
	{
		$folders = [
			$path . '/releases',
			$path . '/live',
			$path . '/shared',
		];
		
		//$this->fs->chmod($path, 0777);
		$this->fs->mkdir($folders);
	}


	public function deploy(\Application $application, $branch, $revision)
	{
		$applicationSettings = $application->getSettings();
		if ($applicationSettings['auto_deploy'] !== TRUE and $applicationSettings['auto_deploy_branch'] !== $branch) {
			return;
		}

		$date = new \DateTime;
		$release = $date->format('YmdHis');

		$this->output->writeln(sprintf('Deploying branch <info>%s</info> release <info>%s</info>', $branch, $release));

		$releaseDir = $this->prepareDeployFiles($application, $branch, $revision, $release);
		try {
			$commands = $this->parseCommandFile($releaseDir);
		} catch (\Nette\Utils\NeonException $e) {
			$this->output->writeln('Unable to read command file, aborting. Reason: ' . $e->getMessage());
			return;
		}

		//run after receive hooks
		try {
			$this->output->writeln('Running after receive hooks');
			$this->runHooks($commands['afterReceiveHooks'], $releaseDir);
		} catch (DeployException $e) {
			$this->output->writeln('After receive hooks failed. Deploy aborted');
			return;
		}

		$targetDir = $applicationSettings['deploy_dir'];
		$liveReleaseDir = $this->copyRelease($releaseDir, $targetDir, $release);

		//run before deploy hooks
		try {
			$this->output->writeln('Running before deploy hooks');
			$this->runHooks($commands['beforeDeployHooks'], $liveReleaseDir);
		} catch (DeployException $e) {
			$this->output->writeln('Before deploy hooks failed. Deploy aborted');
			return;
		}

		//symlink shared folders
		$this->linkSharedDirs($liveReleaseDir, $targetDir, $commands['sharedFolders']);
	}


	private function runHooks($commands, $dir)
	{
		if (!empty($commands)) {
			chdir($dir);
			$output = [];
			$returnVar = NULL;
			foreach ($commands as $command) {
				$this->output->writeln($command);
				exec(escapeshellcmd($command), $output, $returnVar);
				if ($returnVar !== 0) {
					foreach ($output as $line) {
						$this->output->writeln(sprintf('<error>%s</error>', $line));
					}
					throw new DeployException(implode(' ', $output));
				}
				$this->output->writeln($output);
			}
		}
	}


	private function prepareDeployFiles(\Application $application, $branch, $revision, $release)
	{
		$releaseDir = $this->releasesDir . '/' . $release;
		$repository = $this->repostioriesDir . '/' . $application->getRepoName();
		$this->fs->mkdir($releaseDir);

		$this->git->cloneRepository($repository, $releaseDir);

		//delete the .git direcotry
		$dir = getcwd();
		chdir($releaseDir);
		exec('rm -r -f .git');
		chdir($dir);
		return $releaseDir;
	}


	private function parseCommandFile($releaseDir)
	{
		$this->output->writeln('Parsing command file');

		$sharedFolders = [];
		$afterReceiveHooks = [];
		$beforeDeployHooks = [];
		$afterDeployHooks = [];

		$commandFile = $releaseDir . '/commander.neon';
		if (!file_exists($commandFile)) {
			$this->output->writeln('No command file found');
		} else {

			$neon = new \Nette\Utils\Neon;
			$commands = $neon->decode(file_get_contents($commandFile));

			if (isset($commands['shared_folders']) and is_array($commands['shared_folders'])) {
				$sharedFolders = $commands['shared_folders'];
			}

			if (isset($commands['hooks'])) {
				$hooks = $commands['hooks'];

				if (isset($hooks['after_receive']) and is_array($hooks['after_receive'])) {
					$afterReceiveHooks = $hooks['after_receive'];
				}

				if (isset($hooks['before_deploy']) and is_array($hooks['before_deploy'])) {
					$beforeDeployHooks = $hooks['after_deploy'];
				}

				if (isset($hooks['after_deploy']) and is_array($hooks['after_deploy'])) {
					$afterDeployHooks = $hooks['after_deploy'];
				}
			}
		}
		return [
			'sharedFolders' => $sharedFolders,
			'afterReceiveHooks' => $afterReceiveHooks,
			'beforeDeployHooks' => $beforeDeployHooks,
			'afterDeployHooks' => $beforeDeployHooks
		];
	}


	private function copyRelease($releaseDir, $targetDir, $release)
	{
		$this->output->writeln('Copying files');

		$liveReleaseDir = $targetDir . '/releases/' . $release;

		//move to releases folder
		chdir($releaseDir . '/../');

		exec(sprintf('cp -ar %s %s', escapeshellarg(basename($releaseDir)), escapeshellarg($liveReleaseDir)));
		chdir($targetDir);
		try {
			$this->fs->chmod($release, 0555, 0000, $recursive = TRUE);
		} catch (IOException $e) {
			//ignore
		}
		return $liveReleaseDir;
	}


	private function linkSharedDirs($liveReleaseDir, $rootDir, $sharedDirs = [])
	{
		foreach ($sharedDirs as $dirName) {
			$originDir = $liveReleaseDir . '/' . $dirName;
			$targetDir = $rootDir . '/shared/' . $dirName;
			if (!$this->fs->exists($targetDir)) {
				$this->fs->mkdir($targetDir);
			}
			try {
				$this->fs->symlink($originDir, $targetDir, TRUE);
			} catch (IOException $e) {
				$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
				//maybe we're on windows
				if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
					chdir($liveReleaseDir);
					$output = [];
					$returnVar = NULL;
					$command = sprintf('mklink /D %s %s', escapeshellarg($dirName), escapeshellarg($targetDir));
					exec($command, $output, $returnVar);
					if ($returnVar !== 0) {
						$this->output->writeln(sprintf('<error>Cannot create symbolik link on windows. You need administrative privileges.</error>', $e->getMessage()));
					}
					continue;
				}
				$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			}
		}
	}


	private function switchLiveDeploy($liveReleaseDir, $rootDir)
	{
		try {
			$this->fs->symlink($originDir, $targetDir, TRUE);
		} catch (IOException $e) {
			$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			//maybe we're on windows
			if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
				chdir($liveReleaseDir);
				$output = [];
				$returnVar = NULL;
				$command = sprintf('mklink /D %s %s', escapeshellarg($dirName), escapeshellarg($targetDir));
				exec($command, $output, $returnVar);
				if ($returnVar !== 0) {
					$this->output->writeln(sprintf('<error>Cannot create symbolik link on windows. You need administrative privileges.</error>', $e->getMessage()));
				}
			}
			$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
		}
	}


	private function windowsSymLink()
	{
		
	}


}

