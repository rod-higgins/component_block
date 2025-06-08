# Component Block Module

## Overview

The Component Block module extends the Component Field system to create reusable custom blocks using auto-discovered Single Directory Components (SDC) from your theme. This provides a seamless way to use your theme components as blocks throughout your site while leveraging the same powerful configuration system as Component Field.

## Features

- **Flexible Component Blocks**: Create custom blocks using any discovered SDC component
- **Auto-Discovery Integration**: Leverages the Component Field discovery system
- **Rich Configuration**: Same dynamic form generation and validation as Component Field
- **Live Preview**: See component preview while editing blocks
- **Block Management**: Admin interface for managing and monitoring component blocks
- **Version Tracking**: Automatically track component version changes
- **Statistics & Analytics**: Monitor component usage across blocks

## Architecture

Component Block works as a companion to Component Field and Component Views:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  component_field│    │ component_block │    │ component_views │
│     (Core)      │◄───┤   (Blocks)      │    │    (Views)      │
│                 │    │                 │    │                 │
│ • Discovery     │    │ • Custom Blocks │    │ • View Plugins  │
│ • Form Gen      │    │ • Admin UI      │    │ • Field Display │
│ • Field Types   │    │ • Statistics    │    │ • Bulk Ops      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Installation

1. Ensure `component_field` module is installed and enabled
2. Place this module in `web/modules/custom/component_block/`
3. Enable the module: `drush en component_block`
4. Visit `/admin/structure/component-block` to view the admin interface

## Usage

### Creating Component Blocks

1. **Navigate to Block Library**
   - Go to `/admin/structure/block/block-content`
   - Click "Add custom block"
   - Select "Component Block"

2. **Configure Your Block**
   - Enter a descriptive block name
   - Select a component from the dropdown
   - Configure component properties using the auto-generated form
   - Use the live preview to see how your block will look

3. **Place Your Block**
   - Go to `/admin/structure/block`
   - Click "Place block" in any region
   - Find your Component Block and configure placement

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

### Component Requirements
- Valid `.component.yml` file
- Proper SDC structure
- Component props defined in YAML schema

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
```

## Block Configuration

### Field Configuration
Component Block creates a custom block content type with:

- **Block Info**: Standard Drupal block title/description
- **Component Configuration**: Single Component Field instance
- **Required Selection**: Component type must be selected

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
- List all component blocks
- Component type and status for each block
- Quick access to edit and preview
- Filter and search functionality
- Validation status indicators

### Statistics Page (`/admin/structure/component-block/stats`)

Analytics include:
- Total component blocks created
- Component usage distribution
- Most popular components
- Unused components
- Outdated block detection

### Version Management

Component Block tracks component versions by:
- Storing file modification hashes
- Detecting when components change
- Flagging outdated blocks
- Bulk version update functionality

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
}
```

#### `hook_component_block_validate_alter()`
Add custom validation for component blocks:

```php
function mymodule_component_block_validate_alter(&$errors, $block_content) {
  // Add custom validation logic
  if ($block_content->bundle() === 'component_block') {
    // Your validation here
  }
}
```

## Theming

### Template Suggestions

Component Block provides several template suggestions:

```twig
{# Most specific #}
block-content--component-block--[component-type].html.twig
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

#### Override Component Template
```twig
{# templates/component-block-content.html.twig #}
<div class="custom-component-block-wrapper">
  {% if component_type %}
    {{ block_content.field_component_config }}
  {% else %}
    <div class="no-component-selected">
      Please configure this block.
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

/* Content-level classes */
.component-block-content { /* Block content wrapper */ }
.component-block-wrapper { /* Component wrapper */ }
```

## Integration with Component Field

Component Block leverages Component Field's infrastructure:

### Shared Services
- **Discovery Service**: Same component discovery logic
- **Props Parser**: Same form generation system
- **Validation**: Same configuration validation

### Shared Features
- **Rich Text Support**: Full CKEditor5 integration
- **Entity References**: Media, nodes, taxonomy terms
- **JSON Configuration**: Complex data types
- **Live Preview**: Real-time component rendering

### Configuration Compatibility
Component configurations are fully compatible between fields and blocks, allowing:
- Export/import configurations
- Template sharing
- Development workflow consistency

## Performance

### Caching Strategy
- Component discovery cached until manual refresh
- Block content cached per Drupal's block cache system
- Component version hashes for change detection

### Optimization Features
- Lazy loading of component metadata
- Efficient validation with early returns
- Minimal DOM manipulation in admin interface

## Security

### Permissions
- `create component_block content`
- `edit own component_block content`
- `edit any component_block content`
- `delete own component_block content`
- `delete any component_block content`

### Validation
- All component configurations validated against schemas
- XSS protection for all user inputs
- Component path restrictions to theme directories
- JSON schema validation for complex configurations

## Troubleshooting

### No Components Available
1. Check that Component Field module is enabled
2. Verify theme has components in `components/` directory
3. Ensure components have valid `.component.yml` files
4. Refresh component discovery cache

### Block Configuration Errors
1. Check component still exists in theme
2. Validate YAML schema in component files
3. Verify required properties are configured
4. Check browser console for JavaScript errors

### Preview Not Working
1. Ensure JavaScript is enabled
2. Check for CKEditor5 conflicts
3. Verify component has valid template
4. Check Drupal logs for errors

### Performance Issues
1. Clear all caches (`drush cr`)
2. Check for excessive component discovery calls
3. Verify database indexes on block content tables
4. Monitor JavaScript console for errors

## Contributing

When contributing to Component Block:

1. Follow Component Field coding standards
2. Include tests for new functionality
3. Update documentation for API changes
4. Test with multiple component types
5. Verify accessibility compliance

## Support

For support:
- Check Component Field documentation first
- Review Drupal logs at `/admin/reports/dblog`
- Test with minimal component configurations
- Verify theme component structure

## License

GPL-2.0-or-later (same as Drupal core)