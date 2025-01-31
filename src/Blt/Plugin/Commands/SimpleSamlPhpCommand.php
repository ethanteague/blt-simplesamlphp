<?php

namespace Acquia\BltSimplesamlphp\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Helper\FormatterHelper;

/**
 * Defines commands in the "simplesamlphp:*" namespace.
 */
class SimpleSamlPhpCommand extends BltTasks {

  /**
   * BLT root.
   *
   * @var string
   */
  protected $pluginRoot;

  /**
   * Repo root.
   *
   * @var string
   */
  protected $repoRoot;

  /**
   * Deploy directory.
   *
   * @var string
   */
  protected $deployDir;

  /**
   * Formatter helper.
   *
   * @var \Symfony\Component\Console\Helper\FormatterHelper
   */
  protected $formatter;

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize() {
    $this->pluginRoot = $this->getConfigValue('blt.root') . '/../blt-simplesamlphp';
    $this->repoRoot = $this->getConfigValue('repo.root');
    $this->deployDir = $this->getConfigValue('deploy.dir');
    $this->formatter = new FormatterHelper();
  }

  /**
   * Initializes SimpleSAMLphp for project.
   *
   * @command recipes:simplesamlphp:init
   * @aliases rsi saml simplesamlphp:init
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function initializeSimpleSamlPhp() {
    $this->initializeConfig();
    $this->setSimpleSamlPhpInstalled();
    $this->symlinkDocrootToLibDir();
    $this->addHtaccessPatch();
    $this->outputCompleteSetupInstructions();
  }

  /**
   * Copies configuration templates from SimpleSamlPHP to the repo root.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  protected function initializeConfig() {
    $destinationDirectory = "{$this->repoRoot}/simplesamlphp/config";

    $this->say("Copying config files to ${destinationDirectory}...");
    $result = $this->taskFileSystemStack()
      ->copy("{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/config-templates/authsources.php", "${destinationDirectory}/authsources.php", TRUE)
      ->copy("{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/config-templates/config.php", "${destinationDirectory}/config.php", TRUE)
      ->copy("{$this->pluginRoot}/scripts/simplesamlphp/acquia_config.php", "${destinationDirectory}/acquia_config.php", TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to copy SimpleSamlPhp config files.");
    }

    $config_file = "{$this->repoRoot}/simplesamlphp/config/config.php";
    $result = $this->taskWriteToFile($config_file)
      ->text("include 'acquia_config.php';")
      ->append()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable modify $config_file.");
    }

    $this->say("Copying config files to {$this->repoRoot}/simplesamlphp/metadata...");
    $result = $this->taskFileSystemStack()
      ->copy("{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/metadata/saml20-idp-remote.php.dist", "{$this->repoRoot}/simplesamlphp/metadata/saml20-idp-remote.php", TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to initialize SimpleSamlPhp configuration.");
    }
  }

  /**
   * Copies custom config files to SimpleSamlPHP in deploy artifact.
   *
   * @command artifact:build:simplesamlphp-config
   * @aliases absc
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function simpleSamlPhpDeployConfig() {
    $this->say('Copying config files to the appropriate place in simplesamlphp library in the deploy artifact...');
    $result = $this->taskCopyDir(["{$this->repoRoot}/simplesamlphp" => "{$this->deployDir}/vendor/simplesamlphp/simplesamlphp"])
      ->overwrite(TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to copy SimpleSamlPhp files into deployment artifact.");
    }

    $result = $this->taskFileSystemStack()
      ->copy("{$this->pluginRoot}/scripts/simplesamlphp/gitignore.txt", "{$this->deployDir}/vendor/simplesamlphp/simplesamlphp/.gitignore", TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to copy SimpleSamlPhp .gitignore into deployment artifact.");
    }
  }

  /**
   * Sets value in blt.yml to let targets know simplesamlphp is installed.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  protected function setSimpleSamlPhpInstalled() {
    $project_yml = $this->getConfigValue('blt.config-files.project');

    $this->say("Updating ${project_yml}...");

    $project_config = YamlMunge::parseFile($project_yml);
    $project_config['simplesamlphp'] = TRUE;

    try {
      YamlMunge::writeFile($project_yml, $project_config);
    }
    catch (\Exception $e) {
      throw new BltException("Unable to update $project_yml.");
    }
  }

  /**
   * Creates a symlink from the docroot to the web accessible library dir.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  protected function symlinkDocrootToLibDir() {
    $docroot = $this->getConfigValue('docroot');

    $this->say("Creating a symbolic link from ${docroot}/simplesaml to web accessible directory in the simplesamlphp library...");
    $result = $this->taskFilesystemStack()
      //phpcs:ignore
      ->symlink("../vendor/simplesamlphp/simplesamlphp/www", "${docroot}/simplesaml")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Unable create symlink.");
    }
  }

  /**
   * Copies customized config files into vendored SimpleSamlPHP.
   *
   * @command source:build:simplesamlphp-config
   * @aliases sbsc
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function simpleSamlPhpBuildConfig() {
    $this->say('Copying config files to the appropriate place in simplesamlphp library...');
    $result = $this->taskCopyDir(["{$this->repoRoot}/simplesamlphp" => "{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp"])
      ->overwrite(TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to copy configuration into SimpleSamlPhp.");
    }

    $result = $this->taskFileSystemStack()
      ->copy("{$this->pluginRoot}/scripts/simplesamlphp/gitignore.txt", "{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/.gitignore", TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to copy .gitignore into SimpleSamlPhp.");
    }
  }

  /**
   * Ensures SimpleSamlPhp enabled repos have config copied on composer runs.
   *
   * @hook post-command source:build:composer
   */
  public function postComposerHook() {
    if ($this->getConfig()->has('simplesamlphp') && $this->getConfigValue('simplesamlphp')) {
      $this->invokeCommand('source:build:simplesamlphp-config');
    }
  }

  /**
   * Outputs a message to edit the new config files.
   */
  protected function outputCompleteSetupInstructions() {
    $instructions = [
      'To complete the setup you must manually modify several files:',
      '',
      "* {$this->repoRoot}/simplesamlphp/config/acquia_config.php",
      "* {$this->repoRoot}/simplesamlphp/config/authsources.php",
      "* {$this->repoRoot}/simplesamlphp/metadata/saml20-idp-remote.php",
      '',
      'After editing these files execute the following command to copy the modified files to the correct location in the SimpleSAMLphp library:',
      '',
      "'blt source:build:simplesamlphp-config'",
      '',
      "See https://docs.acquia.com/blt/tech-architect/simplesamlphp-setup/ for details on how to modify the files.",
      '',
      'If you need assistance configuring SimpleSAMLphp, please contact Acquia Support or your Technical Account Manager, or visit https://docs.acquia.com/resource/using-simplesamlphp-acquia-cloud-site/. The BLT team cannot assist with SimpleSAMLphp configuration or implementation issues.',
    ];
    $formattedBlock = $this->formatter->formatBlock($instructions, 'comment', TRUE);
    $this->writeln($formattedBlock);
  }

  /**
   * Add a patch to .htaccess.
   */
  protected function addHtaccessPatch() {
    $this->taskFilesystemStack()
      ->copy("{$this->pluginRoot}/scripts/simplesamlphp/htaccess-saml.patch",
        $this->repoRoot . "/patches/htaccess-saml.patch")
      ->run();
    $composer_json = json_decode(file_get_contents("{$this->repoRoot}/composer.json"));
    $composer_json->scripts->{"post-drupal-scaffold-cmd"}[] = "cd docroot && patch -p1 <../patches/htaccess-saml.patch";
    file_put_contents("{$this->repoRoot}/composer.json",
      json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->taskExecStack()
      ->dir($this->repoRoot)
      ->exec("composer post-drupal-scaffold-cmd")
      ->run();
    // @todo throw exceptions.
  }

}
