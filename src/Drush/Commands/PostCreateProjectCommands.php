<?php

namespace Drupal\maintenance\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\pathauto\PathautoState;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\ebr_accommodation\Entity\AccommodationBase;
use Drupal\ebr\EntityBusinessrules;
use Drupal\Core\Database\Connection;
use Drupal\simple_sitemap\Manager\EntityManager as SimpleSitemapEntityManager;
use Drupal\block_content\Entity\BlockContent;

final class PostCreateProjectCommands extends DrushCommands {

  /**
   * Constructs a MaintenanceCommands object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected Connection $database,
    protected SimpleSitemapEntityManager $simpleSitemapEntityManager,
    protected KeyValueFactoryInterface $keyValue,
    protected EntityBusinessrules $ebr,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('simple_sitemap.entity_manager'),
      $container->get('keyvalue'),
      $container->get('ebr.service'),
    );
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'maintenance:create-default-content', aliases: ['def-content'])]
  #[CLI\Usage(name: 'maintenance:create-default-content', description: 'Create default users, nodes and block_content.')]
  public function createDefaultContent() {
    $fileWithSensitiveDefaultContentOnLocalDevSystem = DRUPAL_ROOT . "/../../default_content.php";
    if (file_exists($fileWithSensitiveDefaultContentOnLocalDevSystem)) {
      include_once($fileWithSensitiveDefaultContentOnLocalDevSystem);
    }
    return;

    // Frontpage (en, de) with alias, interal_id and site setting
    if (is_null($this->ebr->getEntity('node', 'frontpage'))) {
      $node = Node::create([
        'title' => 'Startseite',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'frontpage',
      ]);
      // Translation
      $node->addTranslation('en', [
        'title' => 'Frontpage',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'frontpage',
      ]);
      $node->save();
      // Create path alias manually
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/home',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      // Path alias is untranslatable entity
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/home',
        'langcode' => 'en',
      ]);
      $nodeAlias->save();
      // Disable pathauto for this node
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $config = $this->configFactory->getEditable('system.site');
      $config->set('page.front', "/node/{$node->id()}")->save();
    }

    // Enquiry (en, de) with alias, interal_id
    if (is_null($this->ebr->getEntity('node', AccommodationBase::ACTION_ENQUIRY))) {
      $node = Node::create([
        'title' => 'Anfrage',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => AccommodationBase::ACTION_ENQUIRY,
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Öffentlich, verstecken wenn externes Anfrage-Widget. Diese Notiz löschen nach Prüfung.',
      ]);
      $node->addTranslation('en', [
        'title' => 'Enquiry',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => AccommodationBase::ACTION_ENQUIRY,
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Öffentlich, verstecken wenn externes Anfrage-Widget. Diese Notiz löschen nach Prüfung.',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/anfragen',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/enquiry',
        'langcode' => 'en',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
    }

    // Online booking (en, de) with alias, interal_id
    if (is_null($this->ebr->getEntity('node', AccommodationBase::ACTION_BOOK))) {
      $node = Node::create([
        'title' => 'Online buchen',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => AccommodationBase::ACTION_BOOK,
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Muss befüllt werden. Diese Notiz löschen nach Prüfung.',
      ]);
      $node->addTranslation('en', [
        'title' => 'Book online',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => AccommodationBase::ACTION_BOOK,
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Muss befüllt werden. Diese Notiz löschen nach Prüfung.',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/online-buchen',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/book-online',
        'langcode' => 'en',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
    }

    // Webform Enquiry "show after submit" page
    if (is_null($this->ebr->getEntity('node', 'webform_sent_enquiry'))) {
      $node = Node::create([
        'title' => 'Anfrage abgeschickt',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'webform_sent_enquiry',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Öffentlich, verstecken wenn kein Bedarf. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->addTranslation('en', [
        'title' => 'Enquiry submitted',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'webform_sent_enquiry',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Öffentlich, verstecken wenn kein Bedarf. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/enquiry-sent',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/enquiry-sent',
        'langcode' => 'en',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $this->simpleSitemapEntityManager->setEntityInstanceSettings(
        $node->getEntityTypeId(),
        $node->id(),
        ['index' => '0'],
      );
    }

    // Webform Giftcard "show after submit" page
    if (is_null($this->ebr->getEntity('node', 'webform_sent_giftcard'))) {
      $node = Node::create([
        'title' => 'Gutschein bestellt',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 0,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'webform_sent_giftcard',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Versteckt, aktivieren bei Bedarf. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->addTranslation('en', [
        'title' => 'Giftcard ordered',
        'uid' => 1,
        'status' => 0,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'webform_sent_giftcard',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Versteckt, aktivieren bei Bedarf. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/giftcard-sent',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/giftcard-sent',
        'langcode' => 'en',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $this->simpleSitemapEntityManager->setEntityInstanceSettings(
        $node->getEntityTypeId(),
        $node->id(),
        ['index' => '0'],
      );
    }

    // Webform Table "show after submit" page
    if (is_null($this->ebr->getEntity('node', 'webform_sent_table'))) {
      $node = Node::create([
        'title' => 'Tisch-Anfrage abgeschickt',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 0,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'webform_sent_table',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Versteckt, aktivieren bei Bedarf. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->addTranslation('en', [
        'title' => 'Table request submitted',
        'uid' => 1,
        'status' => 0,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'webform_sent_table',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Versteckt, aktivieren bei Bedarf. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/table-sent',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/table-sent',
        'langcode' => 'en',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $this->simpleSitemapEntityManager->setEntityInstanceSettings(
        $node->getEntityTypeId(),
        $node->id(),
        ['index' => '0'],
      );
    }

    // Imprint page (used by cookies module)
    if (is_null($this->ebr->getEntity('node', 'imprint'))) {
      $node = Node::create([
        'title' => 'Impressum',
        'type' => 'page',
        EntityBusinessrules::FIELD_INTERNAL_ID => 'imprint',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Muss befüllt werden. Diese Notiz löschen nach Prüfung.',
        'langcode' => 'de',
        'uid' => 1,
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/impressum',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $config = $this->configFactory->getEditable('cookies.texts');
      $config->set('imprintUri', "/node/{$node->id()}")->save();
      $menuLinkStorage = $this->entityTypeManager->getStorage('menu_link_content');
      $menuLinkStorage->create([
        'title' => 'Impressum',
        'link' => ['uri' => "entity:{$node->getEntityTypeId()}/{$node->id()}"],
        'menu_name' => 'legal',
        'langcode' => 'de',
        'expanded' => FALSE,
        'weight' => 0,
      ])->save();
    }

    // Privacy page (used by cookies module)
    if (is_null($this->ebr->getEntity('node', 'privacy'))) {
      $node = Node::create([
        'title' => 'Datenschutz',
        'type' => 'page',
        EntityBusinessrules::FIELD_INTERNAL_ID => 'privacy',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Muss befüllt werden. Diese Notiz löschen nach Prüfung.',
        'langcode' => 'de',
        'uid' => 1,
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/datenschutz',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $config = $this->configFactory->getEditable('cookies.texts');
      $config->set('privacyUri', "/node/{$node->id()}")->save();
      $menuLinkStorage = $this->entityTypeManager->getStorage('menu_link_content');
      $menuLinkStorage->create([
        'title' => 'Datenschutz',
        'link' => ['uri' => "entity:{$node->getEntityTypeId()}/{$node->id()}"],
        'menu_name' => 'legal',
        'langcode' => 'de',
        'expanded' => FALSE,
        'weight' => 1,
      ])->save();
    }

    // cookies JS ui
    $menuLinkStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $cookiesMenuLink = $menuLinkStorage->loadByProperties([
      'title' => 'Cookies',
      'menu_name' => 'legal',
    ]);
    if ($empty($cookiesMenuLink)) {
      $config = $this->configFactory->getEditable('cookies.config');
      $menuLinkStorage->create([
        'title' => 'Cookies',
        'link' => ['uri' => "internal:#{$config->get('open_settings_hash')}"],
        'menu_name' => 'legal',
        'langcode' => 'de',
        'expanded' => FALSE,
        'weight' => 2,
      ])->save();
    }

    // Error 403
    if (is_null($this->ebr->getEntity('node', 'error_403'))) {
      $node = Node::create([
        'title' => 'Fehler 403 - Nicht erlaubt',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'error_403',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Muss befüllt werden. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/error-403',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $config = $this->configFactory->getEditable('system.site');
      $config->set('page.403', "/node/{$node->id()}")->save();
      $this->simpleSitemapEntityManager->setEntityInstanceSettings(
        $node->getEntityTypeId(),
        $node->id(),
        ['index' => '0'],
      );
    }

    // Error 404
    if (is_null($this->ebr->getEntity('node', 'error_404'))) {
      $node = Node::create([
        'title' => 'Fehler 404 - Seite nicht gefunden',
        'type' => 'page',
        'langcode' => 'de',
        'uid' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'error_404',
        EntityBusinessrules::FIELD_INTERAL_NOTES => 'Muss befüllt werden. Diese Notiz löschen nach Prüfung.',
        'field_seo' => '{"robots":"noindex, nofollow, noarchive, nosnippet, noimageindex"}',
      ]);
      $node->save();
      $nodeAlias = PathAlias::create([
        'path' => "/node/{$node->id()}",
        'alias' => '/error-404',
        'langcode' => 'de',
      ]);
      $nodeAlias->save();
      $this->keyValue->get('pathauto_state.node')->set($node->id(), PathautoState::SKIP);
      $config = $this->configFactory->getEditable('system.site');
      $config->set('page.404', "/node/{$node->id()}")->save();
      $this->simpleSitemapEntityManager->setEntityInstanceSettings(
        $node->getEntityTypeId(),
        $node->id(),
        ['index' => '0'],
      );
    }

    // Frontpage popup block (used by ebr_popup module)
    if (is_null($this->ebr->getEntity('block_content', 'popup_frontpage'))) {
      $block = BlockContent::create([
        'info' => 'Startseite Popup',
        'type' => 'text',
        'langcode' => 'de',
        'reuseable' => 1,
        'status' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'popup_frontpage',
        'body' => [
          'value' => '<p>Ein Popup-Fenster für die Startseite.</p>',
          'format' => 'full_html',
        ]
      ]);
      $block->addTranslation('en', [
        'status' => 1,
        'reuseable' => 1,
        EntityBusinessrules::FIELD_INTERNAL_ID => 'popup_frontpage',
        'body' => [
          'value' => '<p>A popup window on the frontpage.</p>',
          'format' => 'full_html',
        ]
      ]);
      $block->save();

      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $blockConfig */
      $blockConfig = $this->entityTypeManager->getStorage('block')->load('popup');
      if ($blockConfig &&
          $blockConfig->get('theme') == 'frontend' &&
          $blockConfig->get('region') == 'popup' &&
          strpos($blockConfig->get('plugin'), 'block_content:' === 0)
      ) {
        $existingUuid = str_replace('block_content:', '', $blockConfig->get('plugin'));
        $block->set('uuid', $existingUuid)->save();
      }
    }
  }
}
