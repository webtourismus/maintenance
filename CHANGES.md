# CHANGES

##### 2026-11-03 1.3.16

- FEATURE: "robo settings:migrate" adds `error_reporting(E_ALL & ~E_DEPRECATED
  & ~E_USER_DEPRECATED)` to an existing project's settings.php, so PHP
  deprecation notices no longer fill the watchdog table. Real errors and
  warnings are still logged. Runs with "robo pull" and "robo update".

##### 2026-11-02 1.3.15

- BUGFIX: Version in maintenance.info.yml matches the release.

##### 2026-10-28 1.3.14

- FEATURE: New command "robo settings:migrate": replaces outdated code in an
  existing project's settings.php (deprecated assert_options(), ENV default)
  and sets ENV="dev" in .env on the dev server. Run by "robo pull" and
  "robo update", and on prod after the forward sync of "robo update".
- FEATURE: "robo update" also syncs the composer scripts from the starterkit,
  e.g. the htaccess patch command.

##### 2026-10-28 1.3.13

- FEATURE: "robo kickoff:init-dev" writes ENV="dev" to the project's .env, so
  stage systems run in dev mode. Without ENV, settings.php of
  webtourismus/drupal-starterkit falls back to "prod" outside of DDEV.

##### 2025-06-11 1.3.7

- BUGFIX: Fixed robo file developer settings.
- BUGFIX: Fixed robo file db sync.
