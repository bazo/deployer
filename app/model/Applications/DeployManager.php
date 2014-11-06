<?php

namespace Applications;


use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Commander\Console\DeployConsoleOutput;
use GitWrapper\GitWrapper;
use Symfony\Component\Filesystem\Exception\IOException;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Symfony\Component\Process\Process;
use Nette\Neon\Neon;
use Nette\Neon\Exception as NeonException;

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

	/** @var DeployConsoleOutput */
	private $output;

	/** @var GitWrapper */
	private $git;

	/** @var \Application */
	private $application;

	public function __construct($releasesDir, $repositoriesDir, DocumentManager $dm, EventDispatcher $mediator, Filesystem $fs, DeployConsoleOutput $output, GitWrapper $git)
	{
		parent::__construct($dm, $mediator);
		$this->releasesDir		 = $releasesDir;
		$this->repostioriesDir	 = $repositoriesDir;
		$this->fs				 = $fs;
		$this->output			 = $output;
		$this->git				 = $git;
	}


	/**
	 * @param type $path
	 * @throws IOException
	 */
	public function prepareFoldersForDeploy($path)
	{
		$folders = [
			$path . '/releases',
			$path . '/shared',
		];

		//$this->fs->chmod($path, 0777);
		$this->fs->mkdir($folders);
	}


	public function deployManual(\Application $application, $branch, $revision)
	{
		$this->deploy($application, $branch, $revision);
	}


	public function deployAutomatic(\Application $application, $branch, $revision)
	{
		$applicationSettings = $application->getSettings();
		if ($applicationSettings['auto_deploy'] !== TRUE or $applicationSettings['auto_deploy_branch'] !== $branch) {
			return;
		}

		$this->deploy($application, $branch, $revision);
	}


	private function deploy(\Application $application, $branch, $revision)
	{
		$this->startDeploy($application);

		$originalDir		 = getcwd();
		ob_implicit_flush(FALSE);
		ob_start();
		$applicationSettings = $application->getSettings();

		$release = new \Release($application, $branch, $revision);

		$commitMessage = $this->readLastCommitMessage($application, $branch);
		$release->setCommitMessage($commitMessage);

		$this->output->writeln(sprintf('Deploying branch <info>%s</info> release <info>%s</info>', $branch, $release->getNumber()));

		$releaseDir = $this->prepareDeployFiles($application, $branch, $revision, $release);
		try {
			$commands = $this->parseCommandFile($releaseDir);
		} catch (NeonException $e) {
			$reason = 'Unable to read command file, aborting. Reason: ' . $e->getMessage();
			$this->releaseFail($release, $reason);
			$this->output->writeln(sprintf('<error>%s</error>', $reason));
			return;
		}

		//$_ENV for hook commands
		$env = [
			'branch'	 => $branch,
			'revision'	 => $revision,
			'release'	 => $release->getNumber()
		];

		$neonEnv = Neon::encode($env);
		file_put_contents('env.neon', $neonEnv);

		//run after receive hooks
		try {
			$this->output->writeln('<info>Running after receive hooks</info>');
			$this->runHooks($commands['afterReceiveHooks'], $releaseDir, $env);
		} catch (DeployException $e) {
			$reason = 'After receive hooks failed. Deploy aborted.';
			$this->releaseFail($release, $reason);
			$this->output->writeln(sprintf('<error>%s<error>', $reason));
			return;
		}

		$rootDir		 = $applicationSettings['deploy_dir'];
		$liveReleaseDir	 = $this->copyRelease($releaseDir, $rootDir, $release);

		//run before deploy hooks
		try {
			$this->output->writeln('<info>Running before deploy hooks</info>');
			$this->runHooks($commands['beforeDeployHooks'], $liveReleaseDir, $env);
		} catch (DeployException $e) {
			$reason = 'Before deploy hooks failed. Deploy aborted.';
			$this->releaseFail($release, $reason);
			$this->output->writeln(sprintf('<error>%s</error>', $reason));
			return;
		}

		//symlink shared folders
		$warnings			 = [];
		$sharedDirsLinked	 = $this->linkSharedDirs($liveReleaseDir, $rootDir, $commands['sharedFolders']);
		if (!$sharedDirsLinked) {
			$reason		 = 'Symlinking shared folders failed.';
			$warnings[]	 = $reason;
		}
		$liveDeploySwitched = $this->switchLiveDeploy($liveReleaseDir, $rootDir);
		if (!$liveDeploySwitched) {
			$reason		 = 'Symlinking live deploy folder failed.';
			$warnings[]	 = $reason;
		}

		//run after deploy hooks
		try {
			$this->output->writeln('<info>Running after deploy hooks</info>');
			$this->runHooks($commands['afterDeployHooks'], $liveReleaseDir, $env);
		} catch (DeployException $e) {
			$reason		 = 'After deploy hooks failed';
			$warnings[]	 = $reason;
			$this->output->writeln(sprintf('<error>%s</error>', $reason));
			return;
		}

		$application->setCurrentRelease($release);
		$this->dm->persist($application);

		if (!empty($warnings)) {
			$this->output->writeln(sprintf('<comment>Application not fully deployed. There were errors: %s</comment>', implode(' ', $warnings)));
			$this->releaseWarning($release, $warnings);
		} else {
			$this->output->writeln('<info>Application deployed!</info>');
			$this->releaseSuccess($release);
		}

		chdir($originalDir);
	}


	private function releaseSuccess(\Release $release)
	{
		$release->success();
		$this->finishDeploy($release);
	}


	private function releaseFail(\Release $release, $reason)
	{
		$release->fail($reason);
		$this->finishDeploy($release);
	}


	private function releaseWarning(\Release $release, array $reasons)
	{
		$release->warn($reasons);
		$this->finishDeploy($release);
	}


	private function startDeploy(\Application $application)
	{
		$deployStartedEvent = new \Events\Application\DeployStarted($application->getId());
		$this->mediator->dispatch(\Events\ApplicationEvents::DEPLOY_STARTED, $deployStartedEvent);

		$this->output->setApplicationId($application->getId());
		$this->application = $application;
	}


	private function finishDeploy(\Release $release)
	{
		$output = ob_get_contents();
		ob_end_clean();
		$release->setDeployOutput($this->output->getOutputBuffer());
		$this->dm->persist($release);
		$this->dm->flush();

		$deployFinishedEvent = new \Events\Application\DeployFinished($this->application->getId());
		$this->mediator->dispatch(\Events\ApplicationEvents::DEPLOY_FINISHED, $deployFinishedEvent);
	}


	private function runHooks($commands, $dir, $env)
	{
		if (!empty($commands)) {
			chdir($dir);
			$output		 = [];
			$returnVar	 = NULL;
			foreach ($commands as $command) {
				$output = [];
				$this->output->writeln($command);

				$process	 = new Process($commandline = escapeshellcmd($command), $dir);

				//removes PATH
				/*
				  $originalEnv = $process->getEnv();
				  $env = array_merge($originalEnv, $env);
				  $process->setEnv($env);
				 */

				$process->setTimeout(NULL);
				$process->run(function ($type, $buffer) {
					if ('err' === $type) {
						$this->output->writeln(sprintf('<error>%s</error>', $buffer));
					} else {
						$this->output->writeln($buffer);
					}
				});
			}
		}
	}


	private function readLastCommitMessage(\Application $application, $branch)
	{
		$repositoryPath	 = $this->repostioriesDir . '/' . $application->getRepoName();
		$output			 = [];
		$returnVar		 = NULL;
		$format			 = '--pretty=format:"%s" -n 1';
		$command		 = sprintf('git log %s %s', $format, $branch);
		chdir($repositoryPath);
		exec($command, $output, $returnVar);

		return current($output);
	}


	private function prepareDeployFiles(\Application $application, $branch, $revision, $release)
	{
		$releaseDir		 = $this->releasesDir . '/' . $release->getNumber();
		$repositoryPath	 = $this->repostioriesDir . '/' . $application->getRepoName();
		$this->fs->mkdir($releaseDir);

		try {
			$this->git->cloneRepository($repositoryPath, $releaseDir);
		} catch (\GitWrapper\GitException $e) {
			$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			$this->output->writeln('Try again later.');
			exit;
		}

		chdir($releaseDir);

		//checkout the desired branch or commit
		$output		 = [];
		$returnVar	 = NULL;
		exec(sprintf('git --git-dir=%s/.git --work-tree=%s checkout %s', $releaseDir, $releaseDir, escapeshellarg($revision)), $output, $returnVar);

		//delete the .git direcotry
		$this->fs->chmod('.git', 0777);
		$output		 = [];
		$returnVar	 = NULL;
		exec('rm -r -f .git 2>&1', $output, $returnVar);
		if ($returnVar !== 0) {
			$this->output->writeln('Could not remove .git folder. Falling back to stupid file copy.');
		}

		return $releaseDir;
	}


	private function parseCommandFile($releaseDir)
	{
		$this->output->writeln('Parsing command file');

		$sharedFolders		 = [];
		$afterReceiveHooks	 = [];
		$beforeDeployHooks	 = [];
		$afterDeployHooks	 = [];

		$commandFile = $releaseDir . '/deploy.neon';
		if (!file_exists($commandFile)) {
			$this->output->writeln('No command file found');
		} else {

			$neon		 = new Neon;
			$commands	 = $neon->decode(file_get_contents($commandFile));

			if (isset($commands['shared_folders']) and is_array($commands['shared_folders'])) {
				$sharedFolders = $commands['shared_folders'];
			}

			if (isset($commands['hooks'])) {
				$hooks = $commands['hooks'];

				if (isset($hooks['after_receive']) and is_array($hooks['after_receive'])) {
					$afterReceiveHooks = $hooks['after_receive'];
				}

				if (isset($hooks['before_deploy']) and is_array($hooks['before_deploy'])) {
					$beforeDeployHooks = $hooks['before_deploy'];
				}

				if (isset($hooks['after_deploy']) and is_array($hooks['after_deploy'])) {
					$afterDeployHooks = $hooks['after_deploy'];
				}
			}
		}
		return [
			'sharedFolders'		 => $sharedFolders,
			'afterReceiveHooks'	 => $afterReceiveHooks,
			'beforeDeployHooks'	 => $beforeDeployHooks,
			'afterDeployHooks'	 => $afterDeployHooks
		];
	}


	private function copyRelease($releaseDir, $targetDir, $release)
	{
		$this->output->writeln('Copying files');

		$liveReleaseDir = $targetDir . '/releases/' . $release;

		//stupid copy
		if ($this->fs->exists($releaseDir . '/.git')) {
			$filesAndFolders = Finder::find('*')->in($releaseDir);
			$this->fs->mkdir($liveReleaseDir);
			foreach ($filesAndFolders as $file) {
				//ignore .git files
				if (!Strings::startsWith($file->getFilename(), '.git')) {
					exec(sprintf('cp -ar %s %s', escapeshellarg($file->getRealpath()), escapeshellarg($liveReleaseDir . '/' . $file->getFilename())));
				}
			}
		} else {
			//move to releases folder
			chdir($releaseDir . '/../');
			exec(sprintf('cp -ar %s %s', escapeshellarg(basename($releaseDir)), escapeshellarg($liveReleaseDir)));
		}

		chdir($targetDir);
		//change dir only to execute mode
		try {
			$this->fs->chmod($release, 0555, 0000, $recursive = TRUE);
		} catch (IOException $e) {
			//ignore
		}

		return $liveReleaseDir;
	}


	private function linkSharedDirs($liveReleaseDir, $rootDir, $sharedDirs = [])
	{
		chdir($liveReleaseDir);
		$success = TRUE;
		foreach ($sharedDirs as $dirName) {
			$originDir = $rootDir . '/shared/' . $dirName;

			if (!$this->fs->exists($originDir)) {
				$this->fs->mkdir($originDir, 0755);
			}

			if ($this->fs->exists($dirName)) {
				exec(sprintf('rm -rf %s', escapeshellarg($dirName)));
			}
			try {
				$this->fs->symlink($originDir, $dirName, TRUE);
			} catch (IOException $e) {
				$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
				//maybe we're on windows
				if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
					$output		 = [];
					$returnVar	 = NULL;
					$command	 = sprintf('mklink /D %s %s', escapeshellarg($dirName), escapeshellarg($originDir));
					exec($command, $output, $returnVar);
					if ($returnVar !== 0) {
						$this->output->writeln(sprintf('<error>Cannot create symbolik link on windows. You need administrative privileges.</error>', $e->getMessage()));
						$success = FALSE;
					}
					continue;
				}
				$success = FALSE;
				$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			}
		}
		return $success;
	}


	private function switchLiveDeploy($liveReleaseDir, $rootDir)
	{
		$success = TRUE;
		try {
			chdir($rootDir);
			$this->fs->remove(['live']);
			$this->fs->symlink($liveReleaseDir, 'live', TRUE);
		} catch (IOException $e) {
			$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			//maybe we're on windows
			if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
				$output		 = [];
				$returnVar	 = NULL;
				$command	 = sprintf('mklink /D %s %s', escapeshellarg('live'), escapeshellarg($liveReleaseDir));
				exec($command, $output, $returnVar);
				if ($returnVar !== 0) {
					$this->output->writeln(sprintf('<error>Cannot create symbolik link on windows. You need administrative privileges.</error>', $e->getMessage()));
				}
			}
			$success = FALSE;
			$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
		}
		return $success;
	}


}
