# SASS Template Variables

This document describes the SASS variables that can be used to customize the branding of the application.

## Theme Variables

These variables are defined in the `WhiteLabelConfig` model and are passed to the SASS compiler.

- `$primary-color`: The primary color of the application.
- `$secondary-color`: The secondary color of the application.
- `$font-family`: The font family to use.

## Example

```php
// WhiteLabelConfig model
'theme_config' => [
    'primary_color' => '#ff0000',
    'secondary_color' => '#00ff00',
    'font_family' => 'Roboto, sans-serif',
]
```
