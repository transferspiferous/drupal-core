<?php

/**
 * @file
 * Contains \Drupal\system\Tests\Entity\ConfigEntityImportTestBase.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\Core\Config\Entity\EntityWithPluginBagInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Tests importing config entities.
 */
class ConfigEntityImportTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('action', 'block', 'filter', 'image', 'search', 'search_extra_type');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Configuration entity import',
      'description' => 'Tests ConfigEntity importing.',
      'group' => 'Configuration',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.staging'));
  }

  /**
   * Runs test methods for each module within a single test run.
   */
  public function testConfigUpdateImport() {
    $this->doActionUpdate();
    $this->doBlockUpdate();
    $this->doFilterFormatUpdate();
    $this->doImageStyleUpdate();
    $this->doSearchPageUpdate();
  }
  /**
   * Tests updating a action during import.
   */
  protected function doActionUpdate() {
    // Create a test action with a known label.
    $name = 'system.action.apple';
    $entity = entity_create('action', array(
      'id' => 'apple',
      'plugin' => 'action_message_action',
    ));
    $entity->save();

    $this->checkSinglePluginConfigSync($entity, 'configuration', 'message', '');

    // Read the existing data, and prepare an altered version in staging.
    $custom_data = $original_data = $this->container->get('config.storage')->read($name);
    $custom_data['configuration']['message'] = 'Granny Smith';
    $this->assertConfigUpdateImport($name, $original_data, $custom_data);

  }

  /**
   * Tests updating a block during import.
   */
  protected function doBlockUpdate() {
    // Create a test block with a known label.
    $name = 'block.block.apple';
    $block = $this->drupalPlaceBlock('system_powered_by_block', array(
      'id' => 'apple',
      'label' => 'Red Delicious',
    ));

    $this->checkSinglePluginConfigSync($block, 'settings', 'label', 'Red Delicious');

    // Read the existing data, and prepare an altered version in staging.
    $custom_data = $original_data = $this->container->get('config.storage')->read($name);
    $custom_data['settings']['label'] = 'Granny Smith';
    $this->assertConfigUpdateImport($name, $original_data, $custom_data);
  }

  /**
   * Tests updating a filter format during import.
   */
  protected function doFilterFormatUpdate() {
    // Create a test filter format with a known label.
    $name = 'filter.format.plain_text';

    /** @var $entity \Drupal\filter\Entity\FilterFormat */
    $entity = entity_load('filter_format', 'plain_text');
    $plugin_bag = $entity->getPluginBag();

    $filters = $entity->get('filters');
    $this->assertIdentical(72, $filters['filter_url']['settings']['filter_url_length']);

    $filters['filter_url']['settings']['filter_url_length'] = 100;
    $entity->set('filters', $filters);
    $entity->save();
    $this->assertIdentical($filters, $entity->get('filters'));
    $this->assertIdentical($filters, $plugin_bag->getConfiguration());

    $filters['filter_url']['settings']['filter_url_length'] = -100;
    $entity->getPluginBag()->setConfiguration($filters);
    $entity->save();
    $this->assertIdentical($filters, $entity->get('filters'));
    $this->assertIdentical($filters, $plugin_bag->getConfiguration());

    // Read the existing data, and prepare an altered version in staging.
    $custom_data = $original_data = $this->container->get('config.storage')->read($name);
    $custom_data['filters']['filter_url']['settings']['filter_url_length'] = 100;
    $this->assertConfigUpdateImport($name, $original_data, $custom_data);
  }

  /**
   * Tests updating an image style during import.
   */
  protected function doImageStyleUpdate() {
    // Create a test image style with a known label.
    $name = 'image.style.thumbnail';

    /** @var $entity \Drupal\image\Entity\ImageStyle */
    $entity = entity_load('image_style', 'thumbnail');
    $plugin_bag = $entity->getPluginBag();

    $effects = $entity->get('effects');
    $effect_id = key($effects);
    $this->assertIdentical(100, $effects[$effect_id]['data']['height']);

    $effects[$effect_id]['data']['height'] = 50;
    $entity->set('effects', $effects);
    $entity->save();
    // Ensure the entity and plugin have the correct configuration.
    $this->assertIdentical($effects, $entity->get('effects'));
    $this->assertIdentical($effects, $plugin_bag->getConfiguration());

    $effects[$effect_id]['data']['height'] = -50;
    $entity->getPluginBag()->setConfiguration($effects);
    $entity->save();
    // Ensure the entity and plugin have the correct configuration.
    $this->assertIdentical($effects, $entity->get('effects'));
    $this->assertIdentical($effects, $plugin_bag->getConfiguration());

    // Read the existing data, and prepare an altered version in staging.
    $custom_data = $original_data = $this->container->get('config.storage')->read($name);
    $effect_name = key($original_data['effects']);

    $custom_data['effects'][$effect_name]['data']['upscale'] = FALSE;
    $this->assertConfigUpdateImport($name, $original_data, $custom_data);
  }

  /**
   * Tests updating a search page during import.
   */
  protected function doSearchPageUpdate() {
    // Create a test search page with a known label.
    $name = 'search.page.apple';
    $entity = entity_create('search_page', array(
      'id' => 'apple',
      'plugin' => 'search_extra_type_search',
    ));
    $entity->save();

    $this->checkSinglePluginConfigSync($entity, 'configuration', 'boost', 'bi');

    // Read the existing data, and prepare an altered version in staging.
    $custom_data = $original_data = $this->container->get('config.storage')->read($name);
    $custom_data['configuration']['boost'] = 'asdf';
    $this->assertConfigUpdateImport($name, $original_data, $custom_data);
  }

  /**
   * Tests that a single set of plugin config stays in sync.
   *
   * @param \Drupal\Core\Config\Entity\EntityWithPluginBagInterface $entity
   *   The entity.
   * @param string $config_key
   *   Where the plugin config is stored.
   * @param string $setting_key
   *   The setting within the plugin config to change.
   * @param mixed $expected
   *   The expected default value of the plugin config setting.
   */
  protected function checkSinglePluginConfigSync(EntityWithPluginBagInterface $entity, $config_key, $setting_key, $expected) {
    $plugin_bag = $entity->getPluginBag();
    $settings = $entity->get($config_key);

    // Ensure the default config exists.
    $this->assertIdentical($expected, $settings[$setting_key]);

    // Change the plugin config by setting it on the entity.
    $settings[$setting_key] = $this->randomString();
    $entity->set($config_key, $settings);
    $entity->save();
    $this->assertIdentical($settings, $entity->get($config_key));
    $this->assertIdentical($settings, $plugin_bag->getConfiguration());

    // Change the plugin config by setting it on the plugin.
    $settings[$setting_key] = $this->randomString();
    $plugin_bag->setConfiguration($settings);
    $entity->save();
    $this->assertIdentical($settings, $entity->get($config_key));
    $this->assertIdentical($settings, $plugin_bag->getConfiguration());
  }

  /**
   * Asserts that config entities are updated during import.
   *
   * @param string $name
   *   The name of the config object.
   * @param array $original_data
   *   The original data stored in the config object.
   * @param array $custom_data
   *   The new data to store in the config object.
   */
  public function assertConfigUpdateImport($name, $original_data, $custom_data) {
    $this->container->get('config.storage.staging')->write($name, $custom_data);

    // Verify the active configuration still returns the default values.
    $config = $this->container->get('config.factory')->get($name);
    $this->assertIdentical($config->get(), $original_data);

    // Import.
    $this->configImporter()->import();

    // Verify the values were updated.
    $this->container->get('config.factory')->reset($name);
    $config = $this->container->get('config.factory')->get($name);
    $this->assertIdentical($config->get(), $custom_data);
  }

}
