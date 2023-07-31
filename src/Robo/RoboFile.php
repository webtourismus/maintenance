<?php

declare(strict_types=1);

use Robo\Symfony\ConsoleIO;
use Robo\Exception\AbortTasksException;

require_once('load.environment.php');

class RoboFile extends \Robo\Tasks
{

  /**
   * The directory pattern for all projects on the dev environment.
   */
  public const DEV_ENV_DIR_PATTERN = '|^/var/www/vhosts/([a-z0-9\-_]+\.webtourismus\.at)/(([a-z0-9\-_]+)\.\1)/?$|';

  /**
   * The directory pattern for all projects on the prod environment.
   */
  public const PROD_ENV_DIR_PATTERN = '|^/user/home/([a-z0-9\-_]+)/public_html/?$|';

  public function __construct() {
    $this->ensureProjectDir();
  }

  /**
   * Ensures the script is run inside a project root dir on the dev server.
   */
  protected function ensureDevDir() {
    if (preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__)) !== 1) {
      throw new AbortTasksException('This script must be executed in the root directory of a Drupal project on the dev server.');
    }
  }

  /**
   * Ensures the script is run inside a project root dir on the prod server.
   */
  protected function ensureProdDir() {
    if (preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__)) !== 1) {
      throw new AbortTasksException('This script must be executed in the root directory of a Drupal project on the dev server.');
    }
  }

  protected function ensureProjectDir() {
    if (
      preg_match(self::PROD_ENV_DIR_PATTERN, dirname(__FILE__)) !== 1 &&
      preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__)) !== 1
    ) {
      throw new AbortTasksException('This script must be executed in the root directory of a webtourismus/drupal-starterkit project.');
    }
  }

  /**
   * Returns the name of the dev project (without subdomain).
   *
   * Running this script inside "~/example.dev1.webtourismus.at/" returns "example".
   */
  protected function detectDevProjectName() {
    preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__), $results);
    return $results[3];
  }
  /**
   * Creates files required for an automated Drupal installation process.
   */
  public function kickoffInitDev(ConsoleIO $io) {
    $this->ensureDevDir();
    $projectName = $this->detectDevProjectName();
    if (!file_exists('./private/scaffold/default.settings.php.append') || !file_exists('../.env')) {
      throw new AbortTasksException('This script can only be used by webtourismus/drupal-starterkit projects.');
    }
    if (!empty($_ENV['PROJECT_NAME']) || !empty($_ENV['DB_NAME'])) {
      throw new AbortTasksException('Project specific ".env" settings detected. Aborting.');
    }
    if (file_exists('./web/sites/default/settings.php')) {
      throw new AbortTasksException('A "settings.php" file was found. Aborting.');
    }
    $this->taskWriteToFile('.env')
      ->line("PROJECT_NAME=\"{$projectName}\"")
      ->line("DB_NAME=\"dev1_{$projectName}\"")
      ->run();
    $io->say("Created minimal env and settings file for dev system.");
  }

  /**
   * Installs a Drupal website with default config and default data from webtourismus/drupal-starterkit.
   */
  public function kickoffInstallDrupal(ConsoleIO $io) {
    $this->ensureDevDir();
    $projectName = $this->detectDevProjectName();
    if (!file_exists('./.env') || !file_exists('./web/sites/default/settings.php')) {
      throw new AbortTasksException('"settings.php" or ".env" file is missing. Run "robo kickoff:init-dev" first.');
    }
    if (file_exists('./web/sites/default/files/')) {
      throw new AbortTasksException('Drupal "files" storage directory found. This project already seems to be installed.');
    }
    $this->stopOnFail(TRUE);
    $this->_exec("chmod -R u+w ./web/sites/default");
    $this->_exec("cp ./web/sites/default/default.settings.php ./web/sites/default/settings.php");
    // Drush's install routine can't access $_ENV from composer autoloader (unknown bug?).
    // So robo (which does know $_ENV) passes them as hardcoded values to the CLI.
    $this->_exec("./vendor/bin/drush site:install --existing-config --site-name=\"{$projectName}\" --account-name=entwicklung --account-mail=entwicklung@webtourismus.at --db-url=\"mysql://{$_ENV['DB_USER']}:{$_ENV['DB_PASS']}@{$_ENV['DB_HOST']}:{$_ENV['DB_PORT']}/{$_ENV['DB_NAME']}\" --no-interaction");
    $this->_exec("chmod -R u+w ./web/sites/default");
    // Convert hardcoded credentials from CLI back into $_ENV settings.
    $this->taskReplaceInFile('./web/sites/default/settings.php')
      ->from([
        " 'database' => '{$_ENV['DB_NAME']}',",
        " 'username' => '{$_ENV['DB_USER']}',",
        " 'password' => '{$_ENV['DB_PASS']}',",
        " 'host' => '{$_ENV['DB_HOST']}',",
        " 'port' => '{$_ENV['DB_PORT']}',",
      ])
      ->to([
        " 'database' => \$_ENV['DB_NAME'],",
        " 'username' => \$_ENV['DB_USER'],",
        " 'password' => \$_ENV['DB_PASS'],",
        " 'host' => \$_ENV['DB_HOST'] ?? 'localhost',",
        " 'port' => \$_ENV['DB_PORT'] ?? 3306,",
      ])
      ->run();
    $this->_exec("./vendor/bin/drush maintenance:create-default-content -y");
    $this->_exec("./vendor/bin/drush cache:rebuild");
    $io->say("Site {$projectName} was created.");
    $this->_exec("git init");
    $this->_exec("git remote add origin git@bitbucket.org:webtourismus/{$projectName}.git");
    $this->_exec("./vendor/bin/drush config:export -y --commit --message=\"Initial commit\"");
    $this->_exec("git push origin master");
    $io->say("Intial commit to Bitbucket done.");
  }

  /**
   * Like "drush config:export && git push".
   */
  function push(ConsoleIO $io, $message="") {
    $this->ensureProjectDir();
    exec("git fetch && git status", $output);
    foreach($output as $line) {
      if (str_contains($line, ' behind ') || str_contains($line, ' behind ')) {
        throw new AbortTasksException('Environment is behind origin repository.');
      }
    }
    if (empty($message) && $_ENV['ENV']) {
      $date = date('Y-m-d H:i:s');
      $message = "Sync from {$_ENV['ENV']} on {$date}";
    }
    if (empty($message)) {
      $message = $io->ask('Commit message: ');
    }
    $this->stopOnFail(TRUE);
    $this->_exec("./vendor/bin/drush config:export -y --commit --message=\"{$message}\"");
    $this->_exec("git push origin master");
    $io->say("Pushed to origin repository.");
  }

  /**
   * Like "git pull && composer install && drush config:import".
   */
  function pull(ConsoleIO $io) {
    $this->ensureProjectDir();
    exec("git fetch && git status", $output);
    foreach($output as $line) {
      if (str_contains($line, ' ahead ') || str_contains($line, ' vor ')) {
        throw new AbortTasksException('Environment is ahead of origin repository.');
      }
    }
    exec("drush config:status", $output);
    $dirty = TRUE;
    foreach($output as $line) {
      if (str_contains($line, 'No differences between DB and sync directory.')) {
        $dirty = FALSE;
        break;
      }
    }
    if ($dirty) {
      $answer = $io->ask('There are config changes between DB and sync directory. If you continue you\'ll loose changes in active config. Continue? (y/n) ', 'n');
      if (strtolower($answer) !== 'y') {
        throw new AbortTasksException('Aborted due changes in active config.');
      }
    }
    $this->stopOnFail(TRUE);
    $this->_exec("./vendor/bin/drush state:set system.maintenance_mode 1");
    $this->_exec("git pull origin master");
    $this->_exec("git clean -fd config/sync");
    $this->_exec("./composer.phar install --no-dev --prefer-dist");
    $this->_exec("./vendor/bin/drush deploy");
    $this->_exec("./vendor/bin/drush state:set system.maintenance_mode 0");
    $io->say("Pulled everything from origin repository.");
  }

  /**
   * Copies a project from dev environment to prod environment.
   */
  public function golive(ConsoleIO $io) {
    $io->say("Not yet implemented.");
  }
}
