<?php

declare(strict_types=1);

use Robo\Symfony\ConsoleIO;
use Robo\Exception\AbortTasksException;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Composer\Semver\Comparator;

class RoboFile extends \Robo\Tasks
{

  /**
   * List of possible hostnames for dev environments.
   */
  public const DEV_HOSTNAMES = [
    'dedi3828.your-server.de',
  ];

  /**
   * List of possible hostnames for prod environments.
   */
  public const PROD_HOSTNAMES = [
    'dedi103.your-server.de',
    'dedi1661.your-server.de',
    'www659.your-server.de',
  ];

  /**
   * The relative/symlinked directory pattern for all projects on the dev environment.
   */
  public const DEV_ENV_DIR_PATTERN_REL = '|^/usr/home/wtdev\d+/public_html/([a-z0-9\-_]+)/?$|';

  /**
   * The absolute directory pattern for all projects on the dev environment.
   */
  public const DEV_ENV_DIR_PATTERN_ABS = '|^/usr/www/users/wtdev\d+/([a-z0-9\-_]+)/?$|';

  /**
   * The relative/symlinked directory pattern for all projects on the prod environment.
   */
  public const PROD_ENV_DIR_PATTERN_REL = '|^/user/home/([a-z0-9\-_]+)/?$|';
  /**
   * The absolute directory path pattern for all projects on the prod environment.
   */
  public const PROD_ENV_DIR_PATTERN_ABS = '|^/usr/www/users/([a-z0-9\-_]+)/?$|';

  public function __construct() {
    $this->ensureProjectDir();
  }

  /**
   * Returns true if the script is run inside a project root dir on the dev server.
   */
  protected function isDevDir(): bool {
    if (
      preg_match(self::DEV_ENV_DIR_PATTERN_REL, dirname(__FILE__)) !== 1 &&
      preg_match(self::DEV_ENV_DIR_PATTERN_ABS, dirname(__FILE__)) !== 1
    ) {
      return FALSE;
    }
    if (!in_array(gethostname(), self::DEV_HOSTNAMES)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Ensures the script is run inside a project root dir on the dev server.
   * @throws AbortTasksException
   */
  protected function ensureDevDir() {
    if (!$this->isDevDir()) {
      throw new AbortTasksException('This script must be executed in the root directory of a Drupal project on the dev server.');
    }
  }

  /**
   * Returns true if the script is run inside a project root dir on the prod server.
   */
  protected function isProdDir(): bool {
    if (preg_match(self::PROD_ENV_DIR_PATTERN_REL, dirname(__FILE__)) !== 1 &&
      preg_match(self::PROD_ENV_DIR_PATTERN_ABS, dirname(__FILE__)) !== 1
    ) {
      return FALSE;
    }
    if (!in_array(gethostname(), self::PROD_HOSTNAMES)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Ensures the script is run inside a project root dir on the prod server.
   * @throws AbortTasksException
   */
  protected function ensureProdDir() {
    if (!$this->isProdDir()) {
      throw new AbortTasksException('This script must be executed in the root directory of a Drupal project on the dev server.');
    }
  }

  /**
   * Ensures the script is run inside a webtourismus/drupal-starterkit project dir.
   * @throws AbortTasksException
   */
  protected function ensureProjectDir() {
    if (!$this->isDevDir() && !$this->isProdDir()) {
      throw new AbortTasksException('This script must be executed in the root directory of a webtourismus/drupal-starterkit project.');
    }
  }

  /**
   * Returns the project name (must be same as directory name and git repo name).
   */
  protected function getProjectName() {
    return basename(dirname(__FILE__));
  }

  /**
   * Returns true if the current project has a prod environment configuration.
   */
  protected function hasProdEnv() {
    $envFile = file_get_contents('.env');
    if (str_contains($envFile, 'PROD_SSH_HOST') && str_contains($envFile, 'PROD_SSH_USER')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Creates files required for an automated Drupal installation process
   */
  public function kickoffInitDev(ConsoleIO $io) {
    $this->ensureDevDir();
    $projectName = $this->getProjectName();
    $dbPattern = str_replace('-', '_', $projectName);
    $dbPattern = substr($dbPattern, 0, 14);
    $dbPattern = str_pad($dbPattern, 6, '_', STR_PAD_RIGHT);
    $dbName = $io->ask('Enter database name on dev ', $_ENV['DB_NAME'] ?? $dbPattern);
    $dbUser = $io->ask('Enter database user on dev ', $_ENV['DB_USER'] ?? $dbPattern);
    if (!file_exists('./private/scaffold/default.settings.php.append') || !file_exists('../.env')) {
      throw new AbortTasksException('This script can only be used by webtourismus/drupal-starterkit projects.');
    }
    if (!empty($_ENV['PROJECT_NAME']) || !empty($_ENV['DB_NAME'])) {
      throw new AbortTasksException('Project specific ".env" settings detected. Aborting.');
    }
    if (file_exists('./web/sites/default/settings.php')) {
      throw new AbortTasksException('A "settings.php" file was found. Aborting.');
    }
    $this->stopOnFail(TRUE);
    $this->taskWriteToFile('.env')
      ->line("PROJECT_NAME=\"{$projectName}\"")
      ->line("DB_NAME=\"{$dbName}\"")
      ->line("DB_USER=\"{$dbUser}\"")
      ->run();
    $this->_exec("cp ./web/sites/default/default.settings.php ./web/sites/default/settings.php");
    $io->say("Created minimal env and settings file for dev system.");
  }

  /**
   * Installs a Drupal website with default config and default data from webtourismus/drupal-starterkit
   */
  public function kickoffInstallDrupal(ConsoleIO $io) {
    $this->ensureDevDir();
    $projectName = $this->getProjectName();
    if (!file_exists('./.env') || !file_exists('./web/sites/default/settings.php')) {
      throw new AbortTasksException('"settings.php" or ".env" file is missing. Run "robo kickoff:init-dev" first.');
    }
    if (file_exists('./web/sites/default/files/')) {
      throw new AbortTasksException('Drupal "files" storage directory found. This project already seems to be installed.');
    }
    $this->stopOnFail(TRUE);
    $this->_exec("chmod -R u+w ./web/sites/default");
    // ensure variables_order in php.ini includes the "E" option, e.g. variables_order = "EGPCS"
    $this->_exec("./vendor/bin/drush site:install --existing-config --site-name=\"{$projectName}\" --account-name=entwicklung --account-mail=entwicklung@webtourismus.at --no-interaction");
    $this->_exec("chmod -R u+w ./web/sites/default");
    // remove hardcoded $database settings created by drush site:install again
    $this->taskReplaceInFile('./web/sites/default/settings.php')
      ->regex('/\$databases\[\'default\'\]\[\'default\'\]\s*=\s*array\s*\(\s*\'database\'\s*=>\s*\'[^\']+\'.+\,\s*\);/s')
      ->to('')
      ->run();
    $this->_exec("./vendor/bin/drush php:eval \"\Drupal::keyValue('development_settings')->setMultiple(['disable_rendered_output_cache_bins' => TRUE, 'twig_debug' => TRUE, 'twig_cache_disable' => TRUE]);\"");
    $this->_exec("./vendor/bin/drush cache:rebuild");
    if (is_dir('../factory/web/sites/default/files/amenity')) {
      $this->_copyDir(
        '../factory/web/sites/default/files/amenity',
        './web/sites/default/files/amenity',
      );
    }
    $this->_exec("./vendor/bin/drush maintenance:create-default-content -y");
    // import translations for modules not available on drupal.org
    $this->_exec("./vendor/bin/drush locale:import de modules/contrib/ebr/translations/ebr.de.po");
    $this->_exec("./vendor/bin/drush locale:import de modules/contrib/gin_custom/translations/gin_custom.de.po");
    $this->_exec("./vendor/bin/drush locale:import de modules/contrib/seasonal_paragraphs/translations/seasonal_paragraphs.de.po");
    $this->_exec("./vendor/bin/drush locale:import de modules/custom/backend/translations/backend.de.po");
    // create TailwindCSS classes used by Twig layouts and backend modules for Gin theme
    $this->_exec("./vendor/bin/drush css");
  }

  /**
   * Initializes and connects a local Drupal installtion with a Github repo
   */
  public function kickoffInitGit(ConsoleIO $io) {
    $this->ensureDevDir();
    $projectName = $this->getProjectName();
    if (!is_dir('./web/sites/default/files/')) {
      throw new AbortTasksException('Drupal "files" storage directory not found. Run "robo kickoff:install-drupal" first.');
    }
    $this->stopOnFail(TRUE);
    $this->_exec("git init -b master");
    $this->_exec("~/bin/gh repo create webtourismus/{$projectName} --private");
    $this->_exec("git remote add origin git@github.com:webtourismus/{$projectName}.git");
    $this->_exec("./vendor/bin/drush config:export -y");
    $this->_exec("git add -A");
    $this->_exec("git commit -m \"Initial commit\"");
    $this->_exec("git push origin master");
    $this->_exec("git branch --set-upstream-to=origin/master");
    $io->say("Initial commit to Github done.");
    $io->say("Site {$projectName} was created. Have fun with your project. Some hints:");
    $io->listing([
      'Start with designing the custom frontend theme.',
      'Use "robo push" & "robo pull" often!',
      'Use "drush css" update frontend styles in the admin theme (like headers or buttons in CKeditor).',
      'Use "robo update" to keep the project\'s composer.json in sync with updates in the starterkit (like patches).',
    ]);
  }

  /**
   * Like "drush config:export && git push"
   */
  function push(ConsoleIO $io, $message="") {
    $this->ensureProjectDir();
    exec("git fetch && git status", $output);
    foreach($output as $line) {
      if (str_contains($line, ' behind ') || str_contains($line, ' hinter ')) {
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
    $this->_exec("./vendor/bin/drush config:export -y");
    $this->_exec("./vendor/bin/drush backend:css");
    $this->_exec("git add -A");
    $this->_exec("git diff-index --quiet HEAD || git commit -m \"{$message}\"");
    $this->_exec("git push origin master");
    $io->say("Pushed to origin repository.");
  }

  /**
   * Like "git pull && composer install && drush config:import"
   */
  function pull(ConsoleIO $io) {
    $this->ensureProjectDir();
    exec("git fetch && git status", $output);
    foreach($output as $line) {
      if (str_contains($line, ' ahead ') || str_contains($line, ' vor ')) {
        throw new AbortTasksException('Environment is ahead of origin repository.');
      }
    }
    unset($output);
    exec("./vendor/bin/drush config:status --format=table", $output);
    $line = array_shift($output);
    if (!empty($line) && strpos($line, 'No differences between DB and sync directory.') === FALSE) {
      $io->block($output, NULL, 'fg=yellow');
      $answer = $io->confirm('There are config changes between active config in DB and sync directory. If you continue you\'ll loose changes in active config. Continue? ', FALSE);
      if (!$answer) {
        throw new AbortTasksException('Aborted due changes in active config.');
      }
    }
    $this->stopOnFail(TRUE);
    $this->_exec("./vendor/bin/drush state:set system.maintenance_mode 1");
    $this->_exec("git pull origin master");
    $this->_exec("git clean -fd config/sync");
    $optimizeAutoloader = "";
    // always use the project's own local composer to prevent version incompatibilities
    $this->_exec("./composer.phar install --no-dev --prefer-dist -o");
    $this->_exec("./vendor/bin/drush deploy");
    $this->_exec("./vendor/bin/drush backend:css");
    $this->_exec("./vendor/bin/drush state:set system.maintenance_mode 0");
    if ($this->isProdDir()) {
      unset($output);

      exec('./vendor/bin/drush php:eval "echo (string) \Drupal::keyValue(\'development_settings\')->get(\'twig_debug\');"', $output);
      foreach($output as $line) {
        if ($line == '1') {
          $this->_exec('./vendor/bin/drush php:eval "\Drupal::keyValue(\'development_settings\')->setMultiple([\'disable_rendered_output_cache_bins\' => FALSE, \'twig_debug\' => FALSE, \'twig_cache_disable\' => FALSE]);"');
          $this->_exec('./vendor/bin/drush cache:rebuild');
          break;
        }
      }
      $curlHandle = curl_init();
      curl_setopt($curlHandle, CURLOPT_URL, $_ENV['PROD_URI']);
      curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, TRUE);
      $html = curl_exec($curlHandle);
      if (curl_getinfo($curlHandle, CURLINFO_HTTP_CODE) !== 200) {
        throw new AbortTasksException("Production website not available after pulling! Check {$_ENV['PROD_URI']} in your browser!");
      }
    }
    $io->say("Pulled everything from origin repository.");
  }

  /**
   * Copies a project from dev server to prod server
   */
  public function golive(ConsoleIO $io) {
    $this->ensureDevDir();
    $this->stopOnFail(TRUE);
    $io->say('Before starting the launch process, make sure that:');
    $io->listing([
      'Public SSH key of dev server is already provided on the prod server.',
      'If old Drupal project exists: unset old Drush prod SSH credentials in dev project dir',
      'If old Drupal project exists: remove deploy key from old Github repo',
      'Deploy key of prod server is already added to Github repo',
      'PHP is configured on prod server (remote URLs, memory limit, upload size, max_input_vars, ImageMagick)',
      'Backup of old site (files + database) is saved in prod user\'s home directory.',
    ]);
    $sshHost = $io->choice('Select live host', self::PROD_HOSTNAMES, $_ENV['PROD_SSH_HOST'] ?? NULL);
    $sshUser = $io->ask('Enter SSH username on prod host', $_ENV['PROD_SSH_USER'] ?? NULL);
    $domain = $io->ask('Enter production domain (usually starting with www.)', $_ENV['PROD_DOMAIN'] ?? NULL);
    $this->taskWriteToFile('.env')
      ->append(TRUE)
      // if the setting exists, replace it
      ->regexReplace('/^PROD_SSH_HOST=.*$/m', "PROD_SSH_HOST=\"{$sshHost}\"")
      // otherwise append the setting
      ->appendUnlessMatches('/^PROD_SSH_HOST=.*$/m', "PROD_SSH_HOST=\"{$sshHost}\"\n")
      ->regexReplace('/^PROD_SSH_USER=.*$/m', "PROD_SSH_USER=\"{$sshUser}\"")
      ->appendUnlessMatches('/^PROD_SSH_USER=.*$/m', "PROD_SSH_USER=\"{$sshUser}\"\n")
      ->regexReplace('/^PROD_DOMAIN=.*$/m', "PROD_DOMAIN=\"{$domain}\"")
      ->appendUnlessMatches('/^PROD_DOMAIN=.*$/m', "PROD_DOMAIN=\"{$domain}\"\n")
      ->regexReplace('/^PROD_URI=.*$/m', "PROD_URI=\"https://{$domain}\"")
      ->appendUnlessMatches('/^PROD_URI=.*$/m', "PROD_URI=\"https://{$domain}\"\n")
      ->run();
    $this->_exec('cp .env.example .env.prod');
    $dbName = $io->ask('Enter database name on prod', "{$sshUser}_db1");
    $dbUser = $io->ask('Enter database user on prod', "{$sshUser}_1");
    $dbHost = $io->ask('Enter database host on prod', "localhost");
    $dbPass = $io->ask('Enter database password on prod');
    $this->taskWriteToFile('.env.prod')
      ->append(TRUE)
      ->regexReplace('/^ENV=.*$/m', "ENV=\"prod\"")
      ->regexReplace('/^PROJECT_NAME=.*$/m', "PROJECT_NAME=\"{$_ENV['PROJECT_NAME']}\"")
      ->regexReplace('/^PROD_SSH_HOST=.*$/m', "PROD_SSH_HOST=\"{$sshHost}\"")
      ->regexReplace('/^PROD_SSH_USER=.*$/m', "PROD_SSH_USER=\"{$sshUser}\"")
      ->regexReplace('/^PROD_DOMAIN=.*$/m', "PROD_DOMAIN=\"{$domain}\"")
      ->regexReplace('/^PROD_URI=.*$/m', "PROD_URI=\"https://{$domain}\"")
      ->regexReplace('/^DB_NAME=.*$/m', "DB_NAME=\"{$dbName}\"")
      ->regexReplace('/^DB_USER=.*$/m', "DB_USER=\"{$dbUser}\"")
      ->regexReplace('/^DB_HOST=.*$/m', "DB_HOST=\"{$dbHost}\"")
      ->regexReplace('/^DB_PASS=.*$/m', "DB_PASS=\"{$dbPass}\"")
      ->run();
    $io->newLine(3);
    $io->say('Please verify .env settings for prod');
    $io->block(file_get_contents('.env.prod'), NULL, 'fg=yellow');
    $answer = $io->confirm('Are the settings above correct?');
    if (!$answer) {
      throw new AbortTasksException('Aborted due incorrect settings.');
    }

    // "cron" and "cache:rebuilt" greatly reduce the size of the DB
    $this->_exec('./vendor/bin/drush cron');
    $this->_exec('./vendor/bin/drush cache:rebuild');

    $date = date('Y-m-d H:i:s');
    $message = "===== GO LIVE on {$date} =====";
    $this->push($io, $message);
    $this->_exec('./vendor/bin/drush @prod site:ssh "rm index.html || true"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "ssh-keyscan github.com >> ~/.ssh/known_hosts"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "grep -q ":./vendor/bin" ~/.bashrc || echo -e \"\nexport PATH=\$PATH:./vendor/bin\n\" >> ~/.bashrc"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "git clone git@github.com:webtourismus/' . $_ENV['PROJECT_NAME'] .'.git ."');
    $this->_exec('./vendor/bin/drush @prod site:ssh "git branch --set-upstream-to=origin/master"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "./composer.phar install --no-dev --prefer-dist"');
    $this->_exec('./vendor/bin/drush core:rsync ./ @prod:. --exclude-paths=.git:.vscode:.idea:vendor:web/core:web/modules/contrib:web/themes/contrib:web/libraries');
    $this->_exec('./vendor/bin/drush @prod site:ssh "chmod o+rx ."');
    $this->_exec('./vendor/bin/drush @prod site:ssh "cp .env.prod .env"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "rm .env.prod"');
    $this->_exec('rm .env.prod');
    // The ".env" file got changed during this golive function, but those changes are not yet propagated to the local env -->
    // manually refresh the PROD_URI because the subsequent "drush @prod ..." commands need it
    $_ENV['PROD_URI'] = 'https://' . $domain;
    putenv("PROD_URI=https://{$domain}");
    $this->_exec('./vendor/bin/drush sql:sync @self @prod');
    $this->_exec('./vendor/bin/drush @prod cache:rebuild');
    // Fixed escapes with " instead of ' for this command.
    $this->_exec("./vendor/bin/drush @prod php:eval \"\Drupal::keyValue('development_settings')->setMultiple(['disable_rendered_output_cache_bins' => FALSE, 'twig_debug' => FALSE, 'twig_cache_disable' => FALSE]);\"");
    $this->_exec('./vendor/bin/drush @prod cache:rebuild');
    $io->say('Files and database copied to prod server. To finish go live:');
    $io->listing([
      'Set Apache docroot to "/web" on prod server.',
      'Install Let\'s encrypt SSL certificate on prod server.',
      'Add Drupal\'s cronjob on prod server.',
    ]);
    $io->say('Optional checks:');
    $io->listing([
      'Webcam / FTP account needed?',
      'Deactivate old Drupal project on dev server?',
      'Redirect Addon Domains?',
    ]);
  }

  /**
   * Re-syncs the project's "composer.json" with the starterkit and updates all packages
   */
  public function update(ConsoleIO $io) {
    $this->ensureDevDir();
    $this->stopOnFail(TRUE);

    if (
      hash_file('xxh3', '../factory/web/modules/contrib/maintenance/src/Robo/RoboFile.php') !=
      hash_file('xxh3', './RoboFile.php')
    ) {
      $io->say('RoboFile in current project differs from factory project, temporarily copying new version...');
      // The RoboFile can't "composer update webtourismus/maintenace" itself while it is running.
      $this->_exec('cp ../factory/web/modules/contrib/maintenance/src/Robo/RoboFile.php ./');
      throw new AbortTasksException('Sync failed due outdated RoboFile. RoboFile is now updated. Re-run "robo update" now.');
    }

    if ($this->hasProdEnv()) {
      $doBackSync = $io->ask('Prod environment detected. Do you want do push & pull from prod first? (y/n)', 'y');
      if (strtolower($doBackSync) == 'y') {
        $this->_exec('./vendor/bin/drush @prod site:ssh ./vendor/bin/robo push');
        $this->_exec('./vendor/bin/robo pull');
      }
    }

    $starterkit = json_decode(file_get_contents('../_dev/drupal-starterkit/composer.json'));
    $project = json_decode(file_get_contents('./composer.json'));

    /* sync mandatory repo sources */
    foreach ($starterkit->repositories as $key => $item) {
      $project->repositories->{$key} = $item;
    }

    /* sync mandatory packages */
    foreach ($starterkit->require as $key => $item) {
      $project->require->{$key} = $item;
    }

    /* sync mandatory patches */
    $this->_exec("cp ../_dev/drupal-starterkit/private/patches/* ./private/patches/");
    foreach ($starterkit->extra->patches as $package => $patch) {
      if (!property_exists($project->extra->patches, $package)) {
        $project->extra->patches->{$package} = new stdClass();
      }
      foreach ($patch as $key => $file) {
        $project->extra->patches->{$package}->{$key} = $file;
      }
    }

    /* sync scaffold files */
    $this->_exec("cp ../_dev/drupal-starterkit/private/scaffold/* ./private/scaffold/");

    $composerRemove = json_decode(file_get_contents('./private/patches/COMPOSER.REMOVE.json'));

    /* remove deprecated repos */
    foreach ($composerRemove?->remove?->repositories as $repo) {
      if (property_exists($project->repositories, $repo)) {
        unset($project->repositories->{$repo});
      }
    }

    /* remove deprecated packages */
    foreach ($composerRemove?->remove?->require as $package) {
      if (property_exists($project->require, $package)) {
        unset($project->require->{$package});
      }
    }

    /* remove deprecated patches */
    foreach ($composerRemove?->remove?->extra?->patches as $package => $patch) {
      foreach ($patch as $key => $description) {
        if (
          property_exists($project->extra->patches, $package) &&
          property_exists($project->extra->patches->{$package}, $key)
        ) {
          unset($project->extra->patches->{$package}->{$key});
          if (count(get_object_vars($project->extra->patches->{$package})) == 0) {
            unset($project->extra->patches->{$package});
          }
        }
      }
    }

    $jsonIndentedBy4 = json_encode($project, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $jsonIndentedBy2 = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $jsonIndentedBy4);
    file_put_contents('./composer.json', $jsonIndentedBy2);

    // force installing the new versions from the json file
    $this->_exec("rm ./composer.lock");
    // always use the project's own local composer to prevent version incompatibilities
    $this->_exec("./composer.phar install --no-dev --prefer-dist -o");
    $this->_exec("./vendor/bin/drush updatedb");
    $this->_exec("./vendor/bin/drush cache:rebuild");
    $this->_exec('./vendor/bin/robo push "Re-sync with webtourimus/drupal-starterkit and update packages"');

    if ($this->hasProdEnv()) {
      $doForwardSync = $io->ask('Do you want do pull the updates into the prod environment? (y/n)', 'y');
      if (strtolower($doForwardSync) == 'y') {
        $this->_exec('./vendor/bin/drush @prod site:ssh ./vendor/bin/robo pull');
      }
    }
  }
}
