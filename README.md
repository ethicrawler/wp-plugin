# Ethicrawler WordPress Plugin

A WordPress plugin that detects AI bot activity and enables pay-per-crawl monetization for content publishers.

## Features

- Automatic AI bot detection and logging
- Whitelist support for legitimate crawlers (Googlebot, Bingbot)
- Non-blocking data transmission to Ethicrawler backend
- Minimal performance impact on WordPress sites
- Easy configuration through WordPress admin interface

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Configure your site ID in the plugin settings

## Configuration

Navigate to **Settings > Ethicrawler** in your WordPress admin to configure:
- Site ID (provided by your Ethicrawler agency)
- Backend API endpoint
- Bot detection sensitivity

## Testing

### Prerequisites
- WordPress test environment with PHPUnit
- WP-CLI installed for test setup

### Running Tests

1. Set up WordPress test environment:
```bash
# Install WordPress test suite
wp scaffold plugin-tests ethicrawler-bot-detector

# Set up test database
mysql -u root -p -e "CREATE DATABASE wp_test;"
```

2. Run the test suite:
```bash
# From the plugin directory
phpunit tests/test-bot-detector.php

# Or run all tests
phpunit
```

### Manual Testing

You can manually test bot detection by:

1. Configure the plugin with a test site ID
2. Use curl to simulate bot requests:
```bash
# Test AI bot detection (should be logged)
curl -H "User-Agent: GPTBot/1.0" http://your-site.com/
curl -H "User-Agent: python-requests/2.28.1" http://your-site.com/
curl -H "User-Agent: Custom-Scraper/1.0" http://your-site.com/

# Test legitimate crawler (should be whitelisted and not logged)
curl -H "User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)" http://your-site.com/
curl -H "User-Agent: Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)" http://your-site.com/

# Test with different IP headers
curl -H "User-Agent: GPTBot/1.0" -H "X-Forwarded-For: 203.0.113.1" http://your-site.com/
```

3. Check WordPress error logs for detection activity:
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

4. Verify API calls in your backend logs
5. Use the simple test runner for quick validation:
```bash
php test-runner.php
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- cURL extension enabled

## License

MIT License - see LICENSE file for details