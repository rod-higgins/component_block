<?php

namespace Drupal\component_block\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\component_block\Service\ComponentBlockManager;
use Drupal\component_field\Service\ComponentDiscovery;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\block_content\BlockContentInterface;

/**
 * Controller for Component Block administration.
 */
class ComponentBlockAdminController extends ControllerBase {

  use DependencySerializationTrait;

  /**
   * The component block manager.
   */
  protected ComponentBlockManager $componentBlockManager;

  /**
   * The component discovery service.
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * Constructor.
   */
  public function __construct(
    ComponentBlockManager $component_block_manager,
    ComponentDiscovery $component_discovery
  ) {
    $this->componentBlockManager = $component_block_manager;
    $this->componentDiscovery = $component_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('component_block.manager'),
      $container->get('component_field.discovery')
    );
  }

  /**
   * Overview page for component blocks.
   */
  public function overview() {
    $build = [];

    // Action buttons
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-block-overview-actions']],
    ];

    $build['actions']['create'] = [
      '#type' => 'link',
      '#title' => $this->t('➕ Create Component Block'),
      '#url' => Url::fromRoute('block_content.add_form', ['block_content_type' => 'component_block']),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['actions']['stats'] = [
      '#type' => 'link',
      '#title' => $this->t('📊 View Statistics'),
      '#url' => Url::fromRoute('component_block.admin.stats'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    $build['actions']['refresh'] = [
      '#type' => 'link',
      '#title' => $this->t('🔄 Refresh Components'),
      '#url' => Url::fromRoute('component_field.admin.refresh'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Component blocks table
    $blocks = $this->componentBlockManager->getComponentBlocks();
    
    $header = [
      $this->t('Block'),
      $this->t('Component'),
      $this->t('Status'),
      $this->t('Created'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($blocks as $block) {
      $operations = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('entity.block_content.edit_form', ['block_content' => $block->id()]),
          ],
          'preview' => [
            'title' => $this->t('Preview'),
            'url' => Url::fromRoute('component_block.preview', ['block_content' => $block->id()]),
          ],
        ],
      ];

      // Get component information
      $component_types = [];
      $component_status = $this->t('No components');
      
      if ($block->hasField('field_component_config') && !$block->get('field_component_config')->isEmpty()) {
        foreach ($block->get('field_component_config') as $component_field) {
          if ($component_field) {
            $component_type = $component_field->get('component_type')->getValue();
            if ($component_type) {
              $component_types[] = $component_type;
            }
          }
        }
        
        if (!empty($component_types)) {
          // Validate all components
          $validation_errors = $this->componentBlockManager->validateComponentBlock($block);
          if (empty($validation_errors)) {
            $component_status = '✅ ' . $this->t('Valid (@count components)', ['@count' => count($component_types)]);
          } else {
            $component_status = '❌ ' . $this->t('Invalid (@count errors)', ['@count' => count($validation_errors)]);
          }
        }
      }

      $rows[] = [
        'data' => [
          [
            'data' => [
              '#markup' => '<strong>' . $block->label() . '</strong><br><small>ID: ' . $block->id() . '</small>',
            ],
          ],
          implode(', ', $component_types) ?: $this->t('None'),
          [
            'data' => ['#markup' => $component_status],
          ],
          $this->dateFormatter()->format($block->getCreatedTime(), 'short'),
          [
            'data' => $operations,
          ],
        ],
        'class' => ['component-block-admin-item'],
        'data-block-id' => $block->id(),
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No component blocks found. <a href="@url">Create your first component block</a>.', [
        '@url' => Url::fromRoute('block_content.add_form', ['block_content_type' => 'component_block'])->toString(),
      ]),
      '#attributes' => [
        'class' => ['component-block-overview'],
      ],
    ];

    // Component summary
    $components = $this->componentDiscovery->discoverComponents();
    $stats = $this->componentBlockManager->getComponentBlockStats();

    $build['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-block-summary']],
    ];

    $build['summary']['info'] = [
      '#type' => 'markup',
      '#markup' => '<div class="component-block-summary-content">' .
        '<h3>' . $this->t('Summary') . '</h3>' .
        '<ul>' .
        '<li>' . $this->t('@block_count component blocks created', ['@block_count' => $stats['total_blocks']]) . '</li>' .
        '<li>' . $this->t('@component_count components available', ['@component_count' => count($components)]) . '</li>' .
        '<li>' . $this->t('@used_count component types in use', ['@used_count' => count($stats['blocks_by_component'])]) . '</li>' .
        '</ul>' .
        '</div>',
    ];

    $build['#attached']['library'][] = 'component_block/admin';

    return $build;
  }

  /**
   * Statistics page for component blocks.
   */
  public function stats() {
    $build = [];

    $stats = $this->componentBlockManager->getComponentBlockStats();
    $components = $this->componentDiscovery->discoverComponents();

    // Statistics overview
    $build['overview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-block-stats-overview']],
    ];

    $build['overview']['content'] = [
      '#type' => 'markup',
      '#markup' => '<div class="stats-grid">' .
        '<div class="stat-box">' .
        '<h3>' . $stats['total_blocks'] . '</h3>' .
        '<p>' . $this->t('Total Component Blocks') . '</p>' .
        '</div>' .
        '<div class="stat-box">' .
        '<h3>' . count($components) . '</h3>' .
        '<p>' . $this->t('Available Components') . '</p>' .
        '</div>' .
        '<div class="stat-box">' .
        '<h3>' . count($stats['blocks_by_component']) . '</h3>' .
        '<p>' . $this->t('Components in Use') . '</p>' .
        '</div>' .
        '<div class="stat-box">' .
        '<h3>' . count($stats['unused_components']) . '</h3>' .
        '<p>' . $this->t('Unused Components') . '</p>' .
        '</div>' .
        '</div>',
    ];

    // Usage by component
    if (!empty($stats['blocks_by_component'])) {
      $header = [$this->t('Component'), $this->t('Blocks Created'), $this->t('Percentage')];
      $rows = [];

      foreach ($stats['blocks_by_component'] as $component_type => $count) {
        $percentage = round(($count / $stats['total_blocks']) * 100, 1);
        $rows[] = [
          $component_type,
          $count,
          $percentage . '%',
        ];
      }

      $build['usage'] = [
        '#type' => 'details',
        '#title' => $this->t('Component Usage'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
        ],
      ];
    }

    // Unused components
    if (!empty($stats['unused_components'])) {
      $build['unused'] = [
        '#type' => 'details',
        '#title' => $this->t('Unused Components'),
        '#open' => FALSE,
        'list' => [
          '#theme' => 'item_list',
          '#items' => $stats['unused_components'],
          '#empty' => $this->t('All components are being used.'),
        ],
      ];
    }

    // Check for outdated blocks
    $outdated_blocks = $this->componentBlockManager->getOutdatedComponentBlocks();
    if (!empty($outdated_blocks)) {
      $build['outdated'] = [
        '#type' => 'details',
        '#title' => $this->t('Outdated Component Blocks'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['component-block-outdated']],
      ];

      $build['outdated']['warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' .
          '<p>' . $this->t('@count component blocks may be using outdated component versions.', ['@count' => count($outdated_blocks)]) . '</p>' .
          '</div>',
      ];

      $build['outdated']['update'] = [
        '#type' => 'button',
        '#value' => $this->t('Update All Versions'),
        '#ajax' => [
          'callback' => [$this, 'updateVersionsCallback'],
          'wrapper' => 'component-block-outdated-wrapper',
        ],
        '#attributes' => ['class' => ['button--primary']],
      ];

      $outdated_list = [];
      foreach ($outdated_blocks as $outdated) {
        $block = $outdated['block'];
        $item = $block->label() . ' (ID: ' . $block->id() . ')';
        if (isset($outdated['missing']) && $outdated['missing']) {
          $item .= ' - Component "' . $outdated['component_type'] . '" no longer exists';
        } else {
          $item .= ' - Component "' . $outdated['component_type'] . '" has been updated';
        }
        $outdated_list[] = $item;
      }

      $build['outdated']['list'] = [
        '#theme' => 'item_list',
        '#items' => $outdated_list,
      ];
    }

    $build['#attached']['library'][] = 'component_block/admin';

    return $build;
  }

  /**
   * Preview page for a component block.
   */
  public function preview(BlockContentInterface $block_content) {
    $build = [];

    // Block information
    $build['info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-block-preview-info']],
    ];

    $build['info']['content'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Preview: @label', ['@label' => $block_content->label()]) . '</h2>' .
        '<p><strong>' . $this->t('Block ID:') . '</strong> ' . $block_content->id() . '</p>' .
        '<p><strong>' . $this->t('Created:') . '</strong> ' . $this->dateFormatter()->format($block_content->getCreatedTime()) . '</p>',
    ];

    // Validation
    $validation_errors = $this->componentBlockManager->validateComponentBlock($block_content);
    if (!empty($validation_errors)) {
      $build['errors'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--error']],
      ];

      $build['errors']['content'] = [
        '#type' => 'markup',
        '#markup' => '<h4>' . $this->t('Validation Errors') . '</h4>' .
          '<ul><li>' . implode('</li><li>', $validation_errors) . '</li></ul>',
      ];
    }

    // Preview
    $build['preview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-block-preview-container']],
    ];

    $build['preview']['header'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Component Preview') . '</h3>',
    ];

    $build['preview']['content'] = $this->componentBlockManager->renderComponentBlockPreview($block_content);

    // Component information
    if ($block_content->hasField('field_component_config') && !$block_content->get('field_component_config')->isEmpty()) {
      $build['component_info'] = [
        '#type' => 'details',
        '#title' => $this->t('Component Information'),
        '#open' => FALSE,
      ];
      
      $component_count = $block_content->get('field_component_config')->count();
      $build['component_info']['summary'] = [
        '#type' => 'markup',
        '#markup' => '<p><strong>' . $this->t('Components in Block:') . '</strong> ' . $component_count . '</p>',
      ];

      foreach ($block_content->get('field_component_config') as $delta => $component_field) {
        if ($component_field) {
          $component_type = $component_field->get('component_type')->getValue();
          $configuration = $component_field->getConfiguration();

          $build['component_info']['component_' . $delta] = [
            '#type' => 'details',
            '#title' => $this->t('Component @num: @type', ['@num' => $delta + 1, '@type' => $component_type]),
            '#open' => FALSE,
          ];

          if (!empty($configuration)) {
            $build['component_info']['component_' . $delta]['config'] = [
              '#type' => 'markup',
              '#markup' => '<pre>' . json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>',
            ];
          } else {
            $build['component_info']['component_' . $delta]['no_config'] = [
              '#type' => 'markup',
              '#markup' => '<p><em>' . $this->t('No configuration') . '</em></p>',
            ];
          }
        }
      }
    }

    // Action buttons
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-block-preview-actions']],
    ];

    $build['actions']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Block'),
      '#url' => Url::fromRoute('entity.block_content.edit_form', ['block_content' => $block_content->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $build['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Overview'),
      '#url' => Url::fromRoute('component_block.admin.overview'),
      '#attributes' => ['class' => ['button']],
    ];

    $build['#attached']['library'][] = 'component_block/admin';

    return $build;
  }

  /**
   * AJAX callback to update component block versions.
   */
  public function updateVersionsCallback(array &$form, $form_state) {
    $updated_count = $this->componentBlockManager->updateComponentBlockVersions();
    
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('.component-block-outdated', 
      '<div class="messages messages--status">' .
      '<p>' . $this->t('Updated @count component blocks.', ['@count' => $updated_count]) . '</p>' .
      '</div>'
    ));
    
    return $response;
  }

  /**
   * Update component block versions endpoint.
   */
  public function updateVersions() {
    try {
      $updated_count = $this->componentBlockManager->updateComponentBlockVersions();
      
      $this->messenger()->addStatus($this->t(
        'Updated @count component blocks.',
        ['@count' => $updated_count]
      ));
      
      return new JsonResponse([
        'status' => 'success',
        'updated_count' => $updated_count,
      ]);
      
    } catch (\Exception $e) {
      $this->getLogger('component_block')->error('Error updating versions: @error', ['@error' => $e->getMessage()]);
      
      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 500);
    }
  }
}