# Operations Runbook

This document provides instructions for monitoring and troubleshooting the dynamic branding feature.

## Monitoring

- **Cache Hit Ratio**: Monitor the `X-Cache-Hit` header in the responses from the `/branding/{organization}/styles.css` endpoint. A high cache hit ratio (e.g., > 90%) indicates that the caching is working effectively.
- **SASS Compilation Time**: Monitor the response time of the `/branding/{organization}/styles.css` endpoint. A high response time might indicate a problem with the SASS compilation.
- **Errors**: Monitor the application logs for errors related to branding. Look for messages containing "Branding CSS generation failed" or "SASS compilation failed".

## Troubleshooting

- **500 Error on `/branding/{organization}/styles.css`**:
  - Check the application logs for error messages.
  - The most common cause is a missing or invalid SASS template file. Make sure that the `resources/sass/branding/theme.scss` and `resources/sass/branding/dark.scss` files exist and are valid.
  - Another possible cause is an invalid color value in the `WhiteLabelConfig` model. Check the `theme_config` of the organization.
- **Branding not updating**:
  - Clear the application cache by running `php artisan cache:clear`.
  - Clear your browser cache.
  - Check the `updated_at` timestamp of the `WhiteLabelConfig` model. The cache key is based on this timestamp.
