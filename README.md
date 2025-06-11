# Component Block Module

## Overview

The Component Block module extends the Component Field system to create reusable custom blocks using auto-discovered Single Directory Components (SDC) from your theme. This provides a seamless way to use your theme components as blocks throughout your site while leveraging the same powerful configuration system as Component Field.

## Features

- **Multi-Component Blocks**: Create custom blocks with one or more SDC components
- **Flexible Architecture**: Add single components or build complex component collections
- **Auto-Discovery Integration**: Leverages the Component Field discovery system
- **Rich Configuration**: Same dynamic form generation and validation as Component Field
- **Component Reordering**: Drag and drop to arrange components within blocks
- **Live Preview**: See component preview while editing blocks
- **Block Management**: Admin interface for managing and monitoring component blocks
- **Version Tracking**: Automatically track component version changes
- **Statistics & Analytics**: Monitor component usage across blocks
- **Entity Reference Support**: Full support for media, nodes, and taxonomy terms
- **Rich Text Integration**: Complete CKEditor5 integration for text properties

## Requirements

- **Drupal**: 10.4+ or 11.0+
- **Component Field module**: Required dependency
- **Block Content module**: Core dependency (included with Drupal)
- **Single Directory Components (SDC)**: Your theme must contain SDC components

## Architecture

Component Block works as a companion to Component Field and Component Views:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  component_field│    │ component_block │    │ component_views │
│     (Core)      │◄───┤   (Blocks)      │    │    (Views)      │
│                 │    │                 │    │                 │
│ • Discovery     │    │ • Custom Blocks │    │ • View Plugins  │
│ • Form Gen      │    │ • Admin UI      │    │ • Field Display │
│ • Validation    │    │ • Statistics    │    │ • Bulk Ops      │
│ • Field Types   │    │ • Version Mgmt  │    │ • Workflows     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Installation

1. **Install Dependencies**
   - Ensure `component_field` module is installed and enabled
   - Verify your theme contains SDC components

2. **Install Module**
   ```bash
   # Via Composer (recommended)
   composer require drupal/component_block
   drush en component_block
   
   # Or place manually in modules/custom/component_block/
   drush en component_block
   ```

3. **Verify Installation**
   - Visit `/admin/structure/component-block` to view the admin interface
   - Check Status Report for component discovery results

## Usage

### Creating Component Blocks

1. **Navigate to Block Library**
   - Go to `/admin/structure/block/block-content`
   - Click "Add custom block"
   - Select "Component Block"

2. **Configure Your Block**
   - Enter a descriptive block name
   - Add one or more components using "Add another item"
   - Select component types from the dropdown for each
   - Configure component properties using the auto-generated forms
   - Reorder components by dragging if you have multiple
   - Use the live preview to see how your block will look

3. **Place Your Block**
   - Go to `/admin/structure/block`
   - Click "Place block" in any region
   - Find your Component Block and configure placement

### Block Strategies

Component Blocks support multiple strategies depending on your needs:

**Single Component Blocks**
- Add one component per block
- Simple, focused blocks
- Easy to manage and reuse
- Example: "Call to Action Button", "Hero Image"

**Multi-Component Blocks**
- Combine related components
- Create rich content sections
- Build complex layouts
- Example: "Feature Section" (Title + Description + Image + Button)

**Component Collections**
- Group similar components
- Create repeating patterns
- Build dynamic content areas  
- Example: "Testimonials Block" (Multiple testimonial components)

### Block Management

Visit `/admin/structure/component-block` to:

- **View All Component Blocks**: See all created blocks and their status
- **Component Statistics**: View usage analytics and popular components
- **Validation Status**: Check for blocks with configuration errors
- **Version Management**: Update blocks when components change

## Component Discovery

Component Block uses the same discovery system as Component Field:

### Supported Component Locations
- `themes/custom/your_theme/components/`
- `themes/custom/your_theme/src/components/`
- `modules/custom/your_module/components/` (if component_field supports it)

### Component Requirements
- Valid `.component.yml` file following SDC specification
- Proper SDC structure with templates
- Component props defined in YAML schema
- Compatible with Drupal's theme system

### Example Component Structure
```yaml
# components/card/card.component.yml
$schema: https://git.drupalcode.org/project/drupal/-/raw/10.1.x/core/modules/sdc/src/metadata.schema.json
name: Card
description: A flexible card component with title and content
props:
  type: object
  properties:
    title:
      type: string
      title: Card Title
      description: The main heading for the card
    content:
      type: text
      format: html
      title: Card Content
      description: Rich text content for the card body
    variant:
      type: string
      title: Card Variant
      enum: ['default', 'highlighted', 'minimal']
      default: 'default'
    image:
      type: object
      title: Card Image
      description: Optional image for the card
      properties:
        entity_type:
          const: media
        entity_id:
          type: integer
```

## Block Configuration

### Field Configuration
Component Block creates a custom block content type with:

- **Block Info**: Standard Drupal block title/description
- **Component Configuration**: Multi-value Component Field instance (unlimited)
- **Flexible Content**: Single component or multiple component collections
- **Component Ordering**: Drag-and-drop reordering of multiple components

### Widget Settings
- **Show Preview**: Enable/disable live preview (default: enabled)
- **Show Component Info**: Display component metadata (default: enabled)
- **Collapsed by Default**: Initial state of configuration sections

### Formatter Settings
- **Add Wrapper**: Include field-level container
- **Add Item Wrapper**: Include individual component wrapper
- **Enable Fallback**: Show error message for missing components
- **Show Animation**: Add CSS animation classes

## Administration

### Block Overview (`/admin/structure/component-block`)

Features:
- List all component blocks with filtering
- Component type and status for each block
- Quick access to edit and preview
- Search and filter functionality
- Validation status indicators
- Bulk operations support

### Statistics Page (`/admin/structure/component-block/stats`)

Analytics include:
- Total component blocks created
- Component usage distribution
- Most popular components
- Unused components detection
- Outdated block identification
- Export functionality for reports

### Version Management

Component Block tracks component versions by:
- Storing file modification hashes
- Detecting when components change
- Flagging outdated blocks automatically
- Bulk version update functionality
- Notification system for updates

## API

### Services

#### `component_block.manager`
Main service for component block operations:

```php
// Get all component blocks
$blocks = \Drupal::service('component_block.manager')->getComponentBlocks();

// Get blocks using specific component
$blocks = \Drupal::service('component_block.manager')->getComponentBlocksByType('card');

// Validate block configuration
$errors = \Drupal::service('component_block.manager')->validateComponentBlock($block);

// Get usage statistics
$stats = \Drupal::service('component_block.manager')->getComponentBlockStats();

// Check for outdated blocks
$outdated = \Drupal::service('component_block.manager')->getOutdatedComponentBlocks();

// Update component versions
$updated_count = \Drupal::service('component_block.manager')->updateComponentBlockVersions();
```

### Hooks

#### `hook_component_block_info_alter()`
Modify component information before block creation:

```php
function mymodule_component_block_info_alter(&$components) {
  // Hide certain components from blocks
  unset($components['internal_component']);
  
  // Modify component labels
  $components['card']['label'] = 'Custom Card Block';
  
  // Add block-specific metadata
  $components['hero']['block_description'] = 'Use for page headers';
}
```

#### `hook_component_block_validate_alter()`
Add custom validation for component blocks:

```php
function mymodule_component_block_validate_alter(&$errors, $block_content) {
  if ($block_content->bundle() === 'component_block') {
    // Add custom validation logic
    $component_field = $block_content->get('field_component_config');
    foreach ($component_field as $component) {
      // Your validation here
    }
  }
}
```

#### `hook_component_block_presave()`
Modify component blocks before saving:

```php
function mymodule_component_block_presave($block_content) {
  if ($block_content->bundle() === 'component_block') {
    // Modify block content before save
  }
}
```

## Theming

### Template Suggestions

Component Block provides several template suggestions:

```twig
{# Most specific #}
block-content--component-block--[component-type].html.twig
block-content--component-block--[block-id].html.twig
block-content--component-block.html.twig
block-content.html.twig
{# Least specific #}
```

### Custom Templates

#### Override Block Template
```twig
{# templates/block-content--component-block.html.twig #}
<div{{ attributes.addClass('my-component-block') }}>
  {% if content.field_component_config %}
    <div class="component-wrapper">
      {{ content.field_component_config }}
    </div>
  {% endif %}
</div>
```

#### Override Component Block Content Template
```twig
{# templates/component-block-content.html.twig #}
<div{{ attributes.addClass([
  'custom-component-block-wrapper',
  component_type ? 'component-block-' ~ component_type|clean_class : 'component-block-empty'
]) }}>
  {% if block_content.field_component_config and not block_content.field_component_config.isEmpty %}
    <div class="component-block-components">
      {{ block_content.field_component_config }}
    </div>
  {% else %}
    <div class="component-block-empty">
      <div class="component-block-empty-icon">🎨</div>
      <p>{{ 'Component block not configured'|t }}</p>
    </div>
  {% endif %}
</div>
```

### CSS Classes

Component Block adds several CSS classes for styling:

```css
/* Block-level classes */
.component-block { /* All component blocks */ }
.component-block-[component-type] { /* Specific component type */ }
.component-block-configured { /* Has valid configuration */ }
.component-block-empty { /* No component selected */ }
.component-block-multiple { /* Multiple components */ }
.component-block-single { /* Single component */ }

/* Content-level classes */
.component-block-content { /* Block content wrapper */ }
.component-block-wrapper { /* Component wrapper */ }
.component-block-components { /* Components container */ }
```

## Integration with Component Field

Component Block leverages Component Field's infrastructure:

### Shared Services
- **Discovery Service**: Same component discovery logic
- **Props Parser**: Same form generation system
- **Validation**: Same configuration validation
- **Rendering**: Same component rendering pipeline

### Shared Features
- **Rich Text Support**: Full CKEditor5 integration
- **Entity References**: Media, nodes, taxonomy terms
- **JSON Configuration**: Complex data types
- **Live Preview**: Real-time component rendering
- **Schema Validation**: YAML schema compliance

### Configuration Compatibility
Component configurations are fully compatible between fields and blocks, allowing:
- Export/import configurations between fields and blocks
- Template sharing across implementations
- Development workflow consistency
- Testing in fields before promoting to blocks

## Performance

### Caching Strategy
- Component discovery cached until manual refresh or file changes
- Block content cached per Drupal's block cache system
- Component version hashes for efficient change detection
- Render cache integration for optimal performance

### Optimization Features
- Lazy loading of component metadata
- Efficient validation with early returns
- Minimal DOM manipulation in admin interface
- Batch processing for bulk operations

### Cache Tags
Component blocks automatically invalidate when:
- Component files are modified
- Block content is updated
- Component Field settings change
- Theme files are updated

## Security

### Permissions
- `create component_block content` - Create new component blocks
- `edit own component_block content` - Edit your own blocks
- `edit any component_block content` - Edit any component blocks
- `delete own component_block content` - Delete your own blocks
- `delete any component_block content` - Delete any component blocks

### Validation & Security
- All component configurations validated against schemas
- XSS protection for all user inputs
- Component path restrictions to theme directories
- JSON schema validation for complex configurations
- Input sanitization for all form submissions

### Access Control
- Respects Drupal's entity access system
- Integrates with content moderation workflows
- Supports custom access control via hooks

## Troubleshooting

### No Components Available
1. Check that Component Field module is enabled
2. Verify theme has components in `components/` directory
3. Ensure components have valid `.component.yml` files
4. Refresh component discovery cache at `/admin/config/development/performance`
5. Check Status Report for component discovery issues

### Block Configuration Errors
1. Check component still exists in theme
2. Validate YAML schema in component files
3. Verify required properties are configured
4. Check browser console for JavaScript errors
5. Review validation messages in block edit form

### Preview Not Working
1. Ensure JavaScript is enabled
2. Check for CKEditor5 conflicts
3. Verify component has valid template
4. Check Drupal logs for PHP errors
5. Test component rendering outside of blocks

### Performance Issues
1. Clear all caches (`drush cr`)
2. Check for excessive component discovery calls
3. Verify database indexes on block content tables
4. Monitor JavaScript console for errors
5. Review component complexity and dependencies

### Version Management Issues
1. Check file permissions on component directories
2. Verify component file paths are accessible
3. Review version tracking in block admin interface
4. Update versions manually if automatic detection fails

## Development

### Testing
Component Block includes comprehensive testing:
- Unit tests for service classes
- Functional tests for admin interfaces
- Integration tests with Component Field
- Performance tests for large component sets

### Debugging
Enable debugging with:
```php
// In settings.php
$settings['component_block_debug'] = TRUE;
```

### Custom Component Types
Create custom component types by:
1. Implementing custom discovery logic
2. Extending the ComponentBlockManager service
3. Adding custom validation rules
4. Creating specialized admin interfaces

## Contributing

When contributing to Component Block:

1. Follow Component Field coding standards
2. Include tests for new functionality
3. Update documentation for API changes
4. Test with multiple component types and themes
5. Verify accessibility compliance (WCAG 2.1 AA)
6. Test with different Drupal configurations

### Development Setup
```bash
# Clone the repository
git clone [repository-url]

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Code standards check
./vendor/bin/phpcs --standard=Drupal
```

## Roadmap

Future planned features:
- **Component Library Integration**: Browse and install components
- **Layout Builder Integration**: Drag and drop components in Layout Builder
- **Workflow Support**: Content moderation for component blocks
- **A/B Testing**: Compare different component configurations
- **Import/Export**: Bulk import/export of component blocks
- **REST API**: Headless support for component blocks

## Support

For support and community:
- **Issue Queue**: [Drupal.org project page](https://www.drupal.org/project/component_block)
- **Documentation**: Review Component Field documentation first
- **Drupal Logs**: Check `/admin/reports/dblog` for errors
- **Community**: Join the #components channel on Drupal Slack

## License

GPL-2.0-or-later (same as Drupal core)

---

**Note**: This module requires the Component Field module and assumes familiarity with Single Directory Components (SDC) in Drupal themes. For more information about SDC, see the [official Drupal documentation](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components).
