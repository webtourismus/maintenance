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
   * The directory pattern for all projects on the dev environment.
   */
  public const DEV_ENV_DIR_PATTERN = '|^/var/www/vhosts/(([a-z0-9\-_]+)\.webtourismus\.at)/(([a-z0-9\-_]+)\.\1)/?$|';

  /**
   * The relative/symlinked directory pattern for all projects on the prod environment.
   */
  public const PROD_ENV_DIR_PATTERN_REL = '|^/user/home/([a-z0-9\-_]+)/public_html/?$|';
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
    if (preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__)) !== 1) {
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
   * Returns the name of the dev project (without subdomain).
   *
   * Running this script inside "~/example.dev1.webtourismus.at/" returns "example"
   */
  protected function detectDevProjectName() {
    preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__), $results);
    return $results[4];
  }

  /**
   * Returns the name of the dev project (without subdomain).
   *
   * Running this script inside "~/example.dev1.webtourismus.at/" returns "dev1"
   */
  protected function detectDevFamilyName() {
    preg_match(self::DEV_ENV_DIR_PATTERN, dirname(__FILE__), $results);
    return $results[2];
  }

  /**
   * Creates files required for an automated Drupal installation process
   */
  public function kickoffInitDev(ConsoleIO $io) {
    $this->ensureDevDir();
    $familyName = $this->detectDevFamilyName();
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
    $this->stopOnFail(TRUE);
    $this->taskWriteToFile('.env')
      ->line("PROJECT_NAME=\"{$projectName}\"")
      ->line("DB_NAME=\"{$familyName}_{$projectName}\"")
      ->run();
    $this->_exec("cp ./web/sites/default/default.settings.php ./web/sites/default/settings.php");
    $io->say("Created minimal env and settings file for dev system.");
  }

  /**
   * Installs a Drupal website with default config and default data from webtourismus/drupal-starterkit
   */
  public function kickoffInstallDrupal(ConsoleIO $io) {
    $this->ensureDevDir();
    $familyName = $this->detectDevFamilyName();
    $projectName = $this->detectDevProjectName();
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
    $dbName = "{$familyName}_{$projectName}";
    $this->taskReplaceInFile('./web/sites/default/settings.php')
      ->regex('/\$databases\[\'default\'\]\[\'default\'\]\s*=\s*array\s*\(\s*\'database\'\s*=>\s*\'' . $dbName . '\'.+\,\s*\);/s')
      ->to('')
      ->run();
    $this->_exec("./vendor/bin/drush twig:debug on");
    $this->_exec('./vendor/bin/drush state:set disable_rendered_output_cache_bins 1 --input-format=integer');
    $this->_exec("./vendor/bin/drush cache:rebuild");
    if (is_dir('../factory.dev1.webtourismus.at/web/sites/default/files/amenity')) {
      $this->_copyDir(
        '../factory.dev1.webtourismus.at/web/sites/default/files/amenity',
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
    $io->say("Site {$projectName} was created. Have fun with your project. Some hints:");
    $io->listing([
      'Start with designing the custom frontend theme.',
      'Use "robo push" & "robo pull" often!',
      'Use "drush css" update frontend styles in the admin theme (like headers or buttons in CKeditor).',
      'Use "robo sync" to keep the project\'s composer.json in sync with updates in the starterkit (like patches).',
    ]);
  }

  /**
   * Initializes and connects a local Drupal installtion with a Bitbucket repo
   */
  public function kickoffInitGit(ConsoleIO $io) {
    $this->ensureDevDir();
    $projectName = $this->detectDevProjectName();
    if (!is_dir('./web/sites/default/files/')) {
      throw new AbortTasksException('Drupal "files" storage directory not found. Run "robo kickoff:install-drupal" first.');
    }
    $this->stopOnFail(TRUE);
    $this->_exec("git init");
    $this->_exec("git remote add origin git@bitbucket.org:webtourismus/{$projectName}.git");
    $this->_exec("./vendor/bin/drush config:export -y");
    $this->_exec("git add -A");
    $this->_exec("git commit -m \"Initial commit\"");
    $this->_exec("git push origin master");
    $io->say("Initial commit to Bitbucket done.");
  }

  /**
   * Like "drush config:export && git push"
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
      'Public SSH key of prod server is already provided on Bitbucket.',
      'PHP is configured on prod server (remote URLs, memory limit, upload size, max_input_vars, ImageMagick)',
      'Backup of old site (files + database) is saved in prod user\'s home directory.',
    ]);
    $sshHost = $io->choice('Select live host', ['dedi103.your-server.de', 'dedi1661.your-server.de'], $_ENV['PROD_SSH_HOST'] ?? NULL);
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
    $this->_exec('./vendor/bin/drush @prod site:ssh "ssh-keyscan bitbucket.org >> ~/.ssh/known_hosts"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "echo -e \"export PATH=\$PATH:./vendor/bin\n\" >> ~/.bashrc"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "git clone git@bitbucket.org:webtourismus/' . $_ENV['PROJECT_NAME'] .'.git ."');
    $this->_exec('./vendor/bin/drush @prod site:ssh "./composer.phar install --no-dev --prefer-dist"');
    $this->_exec('./vendor/bin/drush core:rsync ./ @prod:. --exclude-paths=.git:.vscode:.idea:vendor:web/core:web/modules/contrib:web/themes/contrib:web/libraries');
    $this->_exec('./vendor/bin/drush @prod site:ssh "chmod o+rx ."');
    $this->_exec('./vendor/bin/drush @prod site:ssh "cp .env.prod .env"');
    $this->_exec('./vendor/bin/drush @prod site:ssh "rm .env.prod"');
    $this->_exec('rm .env.prod');
    // The following command needs some manual error prevention:
    // 1. The ENV variable is set changes the golive function, but the _exec is not yet aware of this change --> export PROD_URI
    // 2. MariaDB adds an "enable sandbox" command on dump, which might break the import --> delete that line from the dump file
    //    @see https://github.com/drush-ops/drush/issues/6027
    $this->_exec('export PROD_URI=https://' . $domain . ' && ./vendor/bin/drush sql:sync @self @prod --extra-dump=" | awk \'NR==1 {if (/enable the sandbox mode/) next} {print}\'"');
    $this->_exec('./vendor/bin/drush @prod cache:rebuild');
    $this->_exec('./vendor/bin/drush @prod php:eval "\Drupal::keyValue(\'development_settings\')->setMultiple([\'disable_rendered_output_cache_bins\' => FALSE, \'twig_debug\' => FALSE, \'twig_cache_disable\' => FALSE]);"');
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
   * Re-syncs the root project's composer.json with the starterkit.
   */
  public function sync(ConsoleIO $io) {
    $this->ensureDevDir();
    $this->stopOnFail(TRUE);

    $starterkit = json_decode(file_get_contents('../_dev/drupal-starterkit/composer.json'));
    $project = json_decode(file_get_contents('./composer.json'));

    /* sync mandatory repo sources */
    foreach ($starterkit->repositories as $key => $item) {
      $project->repositories->{$key} = $item;
    }

    /* sync mandatory dependencies */
    foreach ($starterkit->require as $key => $item) {
      $project->require->{$key} = $item;
    }

    /* sync mandatory patches */
    $this->_exec("cp ~/_dev/drupal-starterkit/private/patches/* ./private/patches/");
    foreach ($starterkit->extra->patches as $package => $patch) {
      if (!property_exists($project->extra->patches, $package)) {
        $project->extra->patches->{$package} = new stdClass();
      }
      foreach ($patch as $key => $file) {
        $project->extra->patches->{$package}->{$key} = $file;
      }
    }

    /* remove deprecated patches */
    $composerRemove = json_decode(file_get_contents('./private/patches/COMPOSER.REMOVE.json'));
    foreach ($composerRemove->remove->extra->patches as $package => $patch) {
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

    $this->_exec("./composer.phar update --root-reqs --no-audit -W --no-dev --prefer-dist -o");
  }
}
