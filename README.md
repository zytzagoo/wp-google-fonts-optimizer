# WP Google Fonts Optimizer

[![PHP Version](https://img.shields.io/badge/php-5.4%2B-blue.svg?style=flat-square)](https://packagist.org/packages/zytzagoo/wp-google-fonts-optimizer)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/zytzagoo/wp-google-fonts-optimizer.svg?style=flat-square)](https://travis-ci.org/zytzagoo/wp-google-fonts-optimizer)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/zytzagoo/wp-google-fonts-optimizer.svg?style=flat-square)](https://scrutinizer-ci.com/g/zytzagoo/wp-google-fonts-optimizer/?branch=master)
[![Quality Score](https://img.shields.io/scrutinizer/g/zytzagoo/wp-google-fonts-optimizer.svg?style=flat-square)](https://scrutinizer-ci.com/g/zytzagoo/wp-google-fonts-optimizer)

WP Google Fonts Optimizer is a super-easy way to ensure your WordPress theme is
not performing unnecessary extra requests for Google Web Fonts (in cases when
you use more than one font family on a page).

It automatically scans your enqueued stylesheets and combines them into a single request when needed.

Optionally, for poorly coded themes (and/or conflicts with other
plugins), it can scan and modify the generated markup.

## Download

See [Releases](https://github.com/zytzagoo/wp-google-fonts-optimizer/releases).

Or install it to your plugin directory via Composer:

```
composer create-project zytzagoo/wp-google-fonts-optimizer --no-dev
```

## Quickstart

Install and activate the plugin, it should do it's job automatically after that.

## Details / Troubleshooting

By default, the plugin enqueues a new stylesheet (with combined font families
etc.) and removes any found/enqueued stylesheets.

If your theme doesn't enqueue the Google Fonts properly (or if there is a
potential conflict with another plugin/theme on your site), you can modify the
way the plugin works and change it so that it parses the generated markup
(instead of it inspecting the enqueued URLs). Do so by adding a filter to
your `functions.php` (or even better, use a `mu-plugin`).

```php
add_filter( 'zwf_gfo_mode', function( $mode ) {
    return 'markup';
});
```

When in _markup mode_, it replaces existing `<link>` elements with a new one
and places it in the `<head>`. This mode also supports creating a
[Web Font Loader](https://github.com/typekit/webfontloader) `<script>` tag,
if that's what you'd prefer. Turn it on using a filter:

```php
add_filter( 'zwf_gfo_markup_type', function( $type ) {
    return 'script';
});
```

## [License (MIT)](LICENSE.md)
