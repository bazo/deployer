<?php

namespace Applications;

use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Output\ConsoleOutput;
use GitWrapper\GitWrapper;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Console\Helper\FormatterHelper;
use Nette\Utils\Finder;
use Nette\Utils\Strings;


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
			$path . '/shared',
		];

		//$this->fs->chmod($path, 0777);
		$this->fs->mkdir($folders);
	}


	public function deploy(\Application $application, $branch, $revision)
	{
		$applicationSettings = $application->getSettings();
		if ($applicationSettings['auto_deploy'] !== TRUE or $applicationSettings['auto_deploy_branch'] !== $branch) {
			return;
		}

		$release = new \Release($application, $branch, $revision);
		$application->setCurrentRelease($release);
		$this->dm->persist($application);
		
		$this->output->writeln(sprintf('Deploying branch <info>%s</info> release <info>%s</info>', $branch, $release->getNumber()));

		$releaseDir = $this->prepareDeployFiles($application, $branch, $revision, $release);
		try {
			$commands = $this->parseCommandFile($releaseDir);
		} catch (\Nette\Utils\NeonException $e) {
			$reason = 'Unable to read command file, aborting. Reason: ' . $e->getMessage();
			$this->releaseFail($release, $reason);
			$this->output->writeln(sprintf('<error>%s</error>', $reason));
			return;
		}

		//run after receive hooks
		try {
			$this->output->writeln('<info>Running after receive hooks</info>');
			$this->runHooks($commands['afterReceiveHooks'], $releaseDir);
		} catch (DeployException $e) {
			$reason = 'After receive hooks failed. Deploy aborted.';
			$this->releaseFail($release, $reason);
			$this->output->writeln(sprintf('<error>%s<error>', $reason));
			return;
		}

		$rootDir = $applicationSettings['deploy_dir'];
		$liveReleaseDir = $this->copyRelease($releaseDir, $rootDir, $release);

		//run before deploy hooks
		try {
			$this->output->writeln('<info>Running before deploy hooks</info>');
			$this->runHooks($commands['beforeDeployHooks'], $liveReleaseDir);
		} catch (DeployException $e) {
			$reason = 'Before deploy hooks failed. Deploy aborted.';
			$this->releaseFail($release, $reason);
			$this->output->writeln(sprintf('<error>%s</error>', $reason));
			return;
		}

		//symlink shared folders
		$warnings = [];
		$sharedDirsLinked = $this->linkSharedDirs($liveReleaseDir, $rootDir, $commands['sharedFolders']);
		if (!$sharedDirsLinked) {
			$reason = 'Symlinking shared folders failed.';
			$warnings[] = $reason;
		}
		$liveDeploySwitched = $this->switchLiveDeploy($liveReleaseDir, $rootDir);
		if (!$liveDeploySwitched) {
			$reason = 'Symlinking live deploy folder failed.';
			$warnings[] = $reason;
		}

		//run after deploy hooks
		try {
			$this->output->writeln('<info>Running after deploy hooks</info>');
			$this->runHooks($commands['afterDeployHooks'], $liveReleaseDir);
		} catch (DeployException $e) {
			$reason = 'After deploy hooks failed';
			$warnings[] = $reason;
			$this->output->writeln(sprintf('<error>%s</error>', $reason));
			return;
		}

		if (!empty($warnings)) {
			$this->releaseWarning($release, $warnings);
			$this->output->writeln(sprintf('<comment>Application not fully deployed. There were errors: %s</comment>', implode(' ', $warnings)));
		} else {
			$this->releaseSuccess($release);
			$this->output->writeln('<info>Application deployed!</info>');
		}
	}


	private function releaseSuccess(\Release $release)
	{
		$release->success();
		$this->dm->persist($release);
		$this->dm->flush();
	}


	private function releaseFail(\Release $release, $reason)
	{
		$release->fail($reason);
		$this->dm->persist($release);
		$this->dm->flush();
	}


	private function releaseWarning(\Release $releaase, array $reasons)
	{
		$releaase->warn($reasons);
		$this->dm->persist($releaase);
		$this->dm->flush();
	}


	private function runHooks($commands, $dir)
	{
		if (!empty($commands)) {
			chdir($dir);
			$output = [];
			$returnVar = NULL;
			foreach ($commands as $command) {
				$output = [];
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
		$releaseDir = $this->releasesDir . '/' . $release->getNumber();
		$repositoryPath = $this->repostioriesDir . '/' . $application->getRepoName();
		$this->fs->mkdir($releaseDir);
		$this->git->cloneRepository($repositoryPath, $releaseDir);

		chdir($releaseDir);
		
		//checkout the desired branch
		$output = [];
		$returnVar = NULL;
		exec(sprintf('git --git-dir=%s/.git --work-tree=%s checkout %s', $releaseDir, $releaseDir, escapeshellarg($branch)), $output, $returnVar);
		
		//delete the .git direcotry
		$this->fs->chmod('.git', 0777);
		$output = [];
		$returnVar = NULL;
		exec('rm -r -f .git 2>&1', $output, $returnVar);
		if($returnVar !== 0) {
			$this->output->writeln('Could not remove .git folder. Falling back to stupid file copy.');
		}
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
					$beforeDeployHooks = $hooks['before_deploy'];
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
			'afterDeployHooks' => $afterDeployHooks
		];
	}


	private function copyRelease($releaseDir, $targetDir, $release)
	{
		$this->output->writeln('Copying files');

		$liveReleaseDir = $targetDir . '/releases/' . $release;

		//stupid copy
		if($this->fs->exists($releaseDir.'/.git')) {
			$filesAndFolders = Finder::find('*')->in($releaseDir);
			$this->fs->mkdir($liveReleaseDir);
			foreach($filesAndFolders as $file) {
				//ignore .git files
				if(!Strings::startsWith($file->getFilename(), '.git')) {
					exec(sprintf('cp -ar %s %s', escapeshellarg($file->getRealpath()), escapeshellarg($liveReleaseDir.'/'.$file->getFilename())));
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
					$output = [];
					$returnVar = NULL;
					$command = sprintf('mklink /D %s %s', escapeshellarg($dirName), escapeshellarg($originDir));
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
			$this->fs->symlink($liveReleaseDir, 'live', TRUE);
		} catch (IOException $e) {
			$this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			//maybe we're on windows
			if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
				$output = [];
				$returnVar = NULL;
				$command = sprintf('mklink /D %s %s', escapeshellarg('live'), escapeshellarg($liveReleaseDir));
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

