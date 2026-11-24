# CHANGES

##### 2026-11-24 1.3.22

- BUGFIX: The imprint, privacy and accessibility pages use Pathauto ("Generate
  automatic URL alias"). Previously Pathauto was disabled for them, so TMGMT
  translated the URL alias as text and machine translation created aliases
  like "/privacy policy" (rendered as "/privacy%20policy"). Now Pathauto
  creates the alias from the translated title, e.g. "/privacy-policy".
  - "drush maintenance:create-default-content" creates these pages with
    Pathauto enabled (German aliases unchanged).
  - Post update enables Pathauto on existing sites if the alias in the
    original language stays the same, and regenerates the aliases of
    translations. With the redirect module, the old alias redirects to the
    new one.

##### 2026-11-24 1.3.21

- FEATURE: "robo update" syncs `composer-exit-on-patch-failure` from the
  starterkit, so a failing patch stops the install instead of silently
  installing the unpatched package.

##### 2026-11-12 1.3.20

- FEATURE: Post update replaces "Mrs." with "Ms." in the English "title"
  (salutation) webform options. "Mrs." implies a married woman. Customized
  labels are kept.

##### 2026-11-12 1.3.19

- FEATURE: Post update adds the gender-neutral options "Divers" and
  "Keine Anrede" to the "title" (Anrede) webform options and to their existing
  translations (en, fr, it; other languages get the English labels). Existing
  options are kept. Runs with "drush updatedb", e.g. during "robo update".

##### 2026-11-11 1.3.18

- BUGFIX: "robo golive" removes the hardcoded `$databases` block that
  "drush site:install" appends to settings.php before the file is copied to
  prod. Previously prod could connect with the dev database credentials
  instead of the ones from .env. "robo kickoff:install-drupal" uses the same
  cleanup, which no longer depends on the order of the keys in the block.
  Aborts if DB_NAME or DB_USER is missing in .env.

##### 2026-11-05 1.3.17

- BUGFIX: "drush maintenance:create-default-content" links the front page popup block to its
  block content again (misplaced parenthesis in the `strpos()` check).

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
