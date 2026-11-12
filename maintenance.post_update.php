<?php

declare(strict_types=1);

use Drupal\Core\Serialization\Yaml;

/**
 * Adds gender-neutral options to the salutation list of webforms.
 *
 * Adds "Divers" and "Keine Anrede" to the "title" webform options and to their
 * existing translations. Options a site already has are kept.
 */
function maintenance_post_update_webform_title_neutral_options(): string {
  $name = 'webform.webform_options.title';
  $config = \Drupal::configFactory()->getEditable($name);
  if ($config->isNew() || !is_string($config->get('options'))) {
    return 'No "title" webform options found.';
  }

  $labels = [
    'de' => ['d' => 'Divers', 'x' => 'Keine Anrede'],
    'en' => ['d' => 'Diverse', 'x' => 'No salutation'],
    'fr' => ['d' => 'Divers', 'x' => 'Sans civilité'],
    'it' => ['d' => 'Diverso', 'x' => 'Nessun appellativo'],
  ];
  $addOptions = static function (string $yaml, array $new): ?string {
    $options = Yaml::decode($yaml);
    if (!is_array($options)) {
      return NULL;
    }
    $changed = FALSE;
    foreach ($new as $key => $label) {
      if (!array_key_exists($key, $options) && !in_array($label, $options, TRUE)) {
        $options[$key] = $label;
        $changed = TRUE;
      }
    }
    return $changed ? rtrim(Yaml::encode($options)) : NULL;
  };

  $updated = [];
  $sourceLangcode = $config->get('langcode') ?: 'de';
  $options = $addOptions($config->get('options'), $labels[$sourceLangcode] ?? $labels['en']);
  if ($options !== NULL) {
    $config->set('options', $options)->save();
    $updated[] = $sourceLangcode;
  }

  $languageManager = \Drupal::languageManager();
  if (method_exists($languageManager, 'getLanguageConfigOverride')) {
    foreach (array_keys($languageManager->getLanguages()) as $langcode) {
      if ($langcode === $sourceLangcode) {
        continue;
      }
      $override = $languageManager->getLanguageConfigOverride($langcode, $name);
      if ($override->isNew() || !is_string($override->get('options'))) {
        continue;
      }
      $options = $addOptions($override->get('options'), $labels[$langcode] ?? $labels['en']);
      if ($options !== NULL) {
        $override->set('options', $options)->save();
        $updated[] = $langcode;
      }
    }
  }

  return $updated
    ? 'Added gender-neutral salutation options (' . implode(', ', $updated) . ').'
    : 'Gender-neutral salutation options already present.';
}

/**
 * Uses "Ms." instead of "Mrs." in the English salutation list of webforms.
 *
 * "Mrs." implies a married woman. Only an unchanged "Mrs." is replaced.
 */
function maintenance_post_update_webform_title_ms(): string {
  $name = 'webform.webform_options.title';
  $languageManager = \Drupal::languageManager();
  $configs = [];
  $source = \Drupal::configFactory()->getEditable($name);
  if (!$source->isNew() && ($source->get('langcode') ?? '') === 'en') {
    $configs[] = $source;
  }
  if (method_exists($languageManager, 'getLanguageConfigOverride') && $languageManager->getLanguage('en')) {
    $override = $languageManager->getLanguageConfigOverride('en', $name);
    if (!$override->isNew()) {
      $configs[] = $override;
    }
  }

  foreach ($configs as $config) {
    $options = is_string($config->get('options')) ? Yaml::decode($config->get('options')) : NULL;
    if (!is_array($options)) {
      continue;
    }
    $key = array_search('Mrs.', $options, TRUE);
    if ($key === FALSE) {
      continue;
    }
    $options[$key] = 'Ms.';
    $config->set('options', rtrim(Yaml::encode($options)))->save();
    return 'Replaced "Mrs." with "Ms." in the English salutation list.';
  }
  return 'No English "Mrs." salutation found.';
}
