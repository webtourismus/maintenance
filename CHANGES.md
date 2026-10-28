# CHANGES

##### 2026-10-28 1.3.13

- FEATURE: "robo kickoff:init-dev" writes ENV="dev" to the project's .env, so
  stage systems run in dev mode. Without ENV, settings.php of
  webtourismus/drupal-starterkit falls back to "prod" outside of DDEV.

##### 2025-06-11 1.3.7

- BUGFIX: Fixed robo file developer settings.
- BUGFIX: Fixed robo file db sync.
