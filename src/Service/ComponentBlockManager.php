<?php

namespace Drupal\component_block\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\component_field\Service\ComponentDiscovery;

/**
 * Service for managing Component Block functionality.
 */
class ComponentBlockManager {

  use DependencySerializationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The component discovery service.
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * The logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ComponentDiscovery $component_discovery,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->componentDiscovery = $component_discovery;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get all component blocks.
   */
  public function getComponentBlocks(): array {
    try {
      $query = $this->entityTypeManager
        ->getStorage('block_content')
        ->getQuery()
        ->condition('type', 'component_block')
        ->accessCheck(TRUE)
        ->sort('info');
        
      $block_ids = $query->execute();
      
      if (empty($block_ids)) {
        return [];
      }
      
      return $this->entityTypeManager
        ->getStorage('block_content')
        ->loadMultiple($block_ids);
        
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error loading component blocks: @error', 
        ['@error' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Get component blocks by component type.
   */
  public function getComponentBlocksByType(string $component_type): array {
    try {
      $blocks = $this->getComponentBlocks();
      $filtered_blocks = [];
      
      foreach ($blocks as $block) {
        if ($block->hasField('field_component_config') && !$block->get('field_component_config')->isEmpty()) {
          $component_field = $block->get('field_component_config')->first();
          if ($component_field && $component_field->get('component_type')->getValue() === $component_type) {
            $filtered_blocks[] = $block;
          }
        }
      }
      
      return $filtered_blocks;
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error filtering component blocks by type @type: @error', 
        ['@type' => $component_type, '@error' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Get component block statistics.
   */
  public function getComponentBlockStats(): array {
    $stats = [
      'total_blocks' => 0,
      'blocks_by_component' => [],
      'most_used_component' => null,
      'unused_components' => [],
    ];
    
    try {
      $blocks = $this->getComponentBlocks();
      $stats['total_blocks'] = count($blocks);
      
      // Count blocks by component type
      foreach ($blocks as $block) {
        if ($block->hasField('field_component_config') && !$block->get('field_component_config')->isEmpty()) {
          $component_field = $block->get('field_component_config')->first();
          if ($component_field) {
            $component_type = $component_field->get('component_type')->getValue();
            if ($component_type) {
              if (!isset($stats['blocks_by_component'][$component_type])) {
                $stats['blocks_by_component'][$component_type] = 0;
              }
              $stats['blocks_by_component'][$component_type]++;
            }
          }
        }
      }
      
      // Find most used component
      if (!empty($stats['blocks_by_component'])) {
        $stats['most_used_component'] = array_search(
          max($stats['blocks_by_component']), 
          $stats['blocks_by_component']
        );
      }
      
      // Find unused components
      $all_components = $this->componentDiscovery->discoverComponents();
      $used_components = array_keys($stats['blocks_by_component']);
      $stats['unused_components'] = array_diff(array_keys($all_components), $used_components);
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error calculating component block stats: @error', 
        ['@error' => $e->getMessage()]
      );
    }
    
    return $stats;
  }

  /**
   * Validate component block configuration.
   */
  public function validateComponentBlock($block_content): array {
    $errors = [];
    
    try {
      if (!$block_content || !method_exists($block_content, 'hasField')) {
        $errors[] = 'Invalid block content entity';
        return $errors;
      }
      
      if (!$block_content->hasField('field_component_config')) {
        $errors[] = 'Block is missing component configuration field';
        return $errors;
      }
      
      if ($block_content->get('field_component_config')->isEmpty()) {
        $errors[] = 'No component configuration provided';
        return $errors;
      }
      
      $component_field = $block_content->get('field_component_config')->first();
      if (!$component_field) {
        $errors[] = 'Component configuration field is invalid';
        return $errors;
      }
      
      $component_type = $component_field->get('component_type')->getValue();
      if (empty($component_type)) {
        $errors[] = 'No component type selected';
        return $errors;
      }
      
      // Check if component still exists
      $components = $this->componentDiscovery->discoverComponents();
      if (!isset($components[$component_type])) {
        $errors[] = "Component '{$component_type}' no longer exists or is not discoverable";
        return $errors;
      }
      
      // Validate configuration against component schema
      $component_info = $components[$component_type];
      $configuration = $component_field->getConfiguration();
      
      if (isset($component_info['props']) && is_array($component_info['props'])) {
        foreach ($component_info['props'] as $prop_name => $prop_schema) {
          // Check required properties
          if (isset($prop_schema['required']) && $prop_schema['required'] === TRUE) {
            if (!isset($configuration[$prop_name]) || 
                (is_string($configuration[$prop_name]) && trim($configuration[$prop_name]) === '')) {
              $errors[] = "Required property '{$prop_name}' is missing";
            }
          }
        }
      }
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error validating component block: @error', 
        ['@error' => $e->getMessage()]
      );
      $errors[] = 'Validation error: ' . $e->getMessage();
    }
    
    return $errors;
  }

  /**
   * Render component block preview.
   */
  public function renderComponentBlockPreview($block_content): array {
    try {
      $validation_errors = $this->validateComponentBlock($block_content);
      if (!empty($validation_errors)) {
        return [
          '#type' => 'markup',
          '#markup' => '<div class="component-block-preview-error">' .
            '<p><strong>Preview Error:</strong></p>' .
            '<ul><li>' . implode('</li><li>', $validation_errors) . '</li></ul>' .
            '</div>',
        ];
      }
      
      // Use the component field formatter to render the preview
      $component_field = $block_content->get('field_component_config');
      $formatter = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load('block_content.component_block.default')
        ->getRenderer('field_component_config');
        
      if ($formatter) {
        return $formatter->viewElements($component_field, 'default');
      }
      
      // Fallback rendering
      return [
        '#type' => 'markup',
        '#markup' => '<div class="component-block-preview-fallback">' .
          '<p>Component block preview (using fallback renderer)</p>' .
          '</div>',
      ];
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error rendering component block preview: @error', 
        ['@error' => $e->getMessage()]
      );
      
      return [
        '#type' => 'markup',
        '#markup' => '<div class="component-block-preview-error">' .
          '<p><strong>Preview Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
          '</div>',
      ];
    }
  }

  /**
   * Get component blocks that need updates.
   */
  public function getOutdatedComponentBlocks(): array {
    $outdated_blocks = [];
    
    try {
      $blocks = $this->getComponentBlocks();
      $current_components = $this->componentDiscovery->discoverComponents();
      
      foreach ($blocks as $block) {
        if ($block->hasField('field_component_config') && !$block->get('field_component_config')->isEmpty()) {
          $component_field = $block->get('field_component_config')->first();
          if ($component_field) {
            $component_type = $component_field->get('component_type')->getValue();
            $component_version = $component_field->getComponentVersion();
            
            if ($component_type && isset($current_components[$component_type])) {
              $current_version = md5_file($current_components[$component_type]['yml_file'] ?? '');
              
              if ($component_version && $component_version !== $current_version) {
                $outdated_blocks[] = [
                  'block' => $block,
                  'component_type' => $component_type,
                  'old_version' => $component_version,
                  'new_version' => $current_version,
                ];
              }
            } elseif ($component_type) {
              // Component no longer exists
              $outdated_blocks[] = [
                'block' => $block,
                'component_type' => $component_type,
                'old_version' => $component_version,
                'new_version' => null,
                'missing' => TRUE,
              ];
            }
          }
        }
      }
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error checking for outdated component blocks: @error', 
        ['@error' => $e->getMessage()]
      );
    }
    
    return $outdated_blocks;
  }

  /**
   * Update component block versions.
   */
  public function updateComponentBlockVersions(): int {
    $updated_count = 0;
    
    try {
      $blocks = $this->getComponentBlocks();
      $current_components = $this->componentDiscovery->discoverComponents();
      
      foreach ($blocks as $block) {
        if ($block->hasField('field_component_config') && !$block->get('field_component_config')->isEmpty()) {
          $component_field = $block->get('field_component_config')->first();
          if ($component_field) {
            $component_type = $component_field->get('component_type')->getValue();
            
            if ($component_type && isset($current_components[$component_type])) {
              $current_version = md5_file($current_components[$component_type]['yml_file'] ?? '');
              $component_field->setComponentVersion($current_version);
              $block->save();
              $updated_count++;
            }
          }
        }
      }
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('component_block')->error(
        'Error updating component block versions: @error', 
        ['@error' => $e->getMessage()]
      );
    }
    
    return $updated_count;
  }
}