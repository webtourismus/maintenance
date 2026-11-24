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

/**
 * Enables Pathauto for the imprint, privacy and accessibility pages.
 *
 * With Pathauto disabled, TMGMT sends the URL alias to the translator, which
 * creates aliases like "/privacy policy" (rendered as "/privacy%20policy").
 * With Pathauto enabled, TMGMT skips the alias and Pathauto creates it from the
 * translated title, e.g. "/privacy-policy".
 *
 * A page is only switched if Pathauto creates the same alias as the current one
 * in its original language, so existing URLs do not change. Aliases of
 * translations are regenerated; with the redirect module ("auto_redirect"), the
 * old alias redirects to the new one.
 */
function maintenance_post_update_legal_pages_pathauto(): string {
  if (!\Drupal::moduleHandler()->moduleExists('pathauto') || !\Drupal::hasService('ebr.service')) {
    return 'Pathauto or ebr not available.';
  }
  /** @var \Drupal\pathauto\PathautoGeneratorInterface $generator */
  $generator = \Drupal::service('pathauto.generator');
  $aliasManager = \Drupal::service('path_alias.manager');
  $state = \Drupal::keyValue('pathauto_state.node');

  $messages = [];
  foreach (['imprint', 'privacy', 'accessibility'] as $internalId) {
    $node = \Drupal::service('ebr.service')->getEntity('node', $internalId);
    if (!$node instanceof \Drupal\node\NodeInterface) {
      continue;
    }
    if ((int) $state->get($node->id()) === \Drupal\pathauto\PathautoState::CREATE) {
      $messages[] = "{$internalId}: already enabled";
      continue;
    }
    $original = $node->getUntranslated();
    $path = '/node/' . $node->id();
    $current = $aliasManager->getAliasByPath($path, $original->language()->getId());
    $generated = $generator->createEntityAlias($original, 'return');
    if ($generated === NULL || $generated !== $current) {
      $messages[] = "{$internalId}: kept, {$current} would change to " . ($generated ?? 'no alias');
      continue;
    }

    $state->set($node->id(), \Drupal\pathauto\PathautoState::CREATE);
    $translations = [];
    foreach (array_keys($node->getTranslationLanguages(FALSE)) as $langcode) {
      $translation = $node->getTranslation($langcode);
      $before = $aliasManager->getAliasByPath($path, $langcode);
      $generator->updateEntityAlias($translation, 'bulkupdate', ['force' => TRUE]);
      $aliasManager->cacheClear($path);
      $after = $aliasManager->getAliasByPath($path, $langcode);
      $translations[] = $before === $after ? "{$langcode} {$after}" : "{$langcode} {$before} => {$after}";
    }
    $messages[] = "{$internalId}: enabled" . ($translations ? ' (' . implode(', ', $translations) . ')' : '');
  }

  return $messages ? 'Pathauto for legal pages: ' . implode('; ', $messages) . '.' : 'No legal pages found.';
}
