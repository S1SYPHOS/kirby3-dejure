<?php

/**
 * Kirby3 Dejure - Auto-linking legal norms to dejure.org for Kirby v3
 *
 * @package   Kirby CMS
 * @author    S1SYPHOS <hello@twobrain.io>
 * @link      http://twobrain.io
 * @version   1.4.3
 * @license   MIT
 */

@include_once __DIR__ . '/vendor/autoload.php';

use S1SYPHOS\DejureOnline;


/**
 * Initializes new DejureOnline (DJO) instance
 *
 * @return \S1SYPHOS\DejureOnline
 */
function dejureInit(): \S1SYPHOS\DejureOnline
{
    # Ensure that path to cache directory exists
    $cacheDir = kirby()->root('cache') . '/dejure-online.org/';

    # Initialize DJO instance
    return new DejureOnline(
        option('kirby3-dejure.driver', 'file'),
        option('kirby3-dejure.caching', ['storage' => $cacheDir])
    );
}


/**
 * Processes linkable citations & caches text (if uncached or expired)
 *
 * @param string $text Original (unprocessed) text
 * @param string $ignore Judicial file numbers to be ignored
 * @return string Processed text if successful, otherwise unprocessed text
 */
function dejurify(string $text, string $ignore = null): string
{
    # Create DJO instance
    $object = dejureInit();

    # Set defaults
    # (1) General information
    $object->setEmail(option('kirby3-dejure.email', ''));

    # (2) Text processing
    $object->setBuzer(option('kirby3-dejure.buzer', false));
    $object->setClass(option('kirby3-dejure.class', ''));
    $object->setLineBreak(option('kirby3-dejure.lineBreak', 'auto'));
    $object->setLinkStyle(option('kirby3-dejure.linkStyle', 'weit'));
    $object->setTarget(option('kirby3-dejure.target', '_blank'));
    $object->setTooltip(option('kirby3-dejure.tooltip', 'beschreibend'));

    # (3) Connection
    $object->setStreamTimeout(option('kirby3-dejure.streamTimeout', 10));
    $object->setTimeout(option('kirby3-dejure.timeout', 3));
    $object->setUserAgent(option('kirby3-dejure.userAgent', 'kirby3-dejure @ ' . Kirby\Http\Server::host()));

    # (4) Caching
    $object->setCacheDuration(option('kirby3-dejure.cacheDuration', 2));

    # Fallback to global ignore
    if (!isset($ignore)) {
        $ignore = option('kirby3-dejure.ignore', '');
    }

    return $object->dejurify($text, $ignore);
}


/**
 * Clears DJO cache
 *
 * @return bool Whether cache was cleared
 */
function clearDJO(): bool
{
    # Create DJO instance
    $object = dejureInit();

    # Clear cache
    return $object->clearCache();
}


Kirby::plugin('s1syphos/kirby3-dejure', [
    'hooks' => [
        'kirbytext:after' => function (string $text): string
        {
            # Process text by all means when page template is allowlisted
            if (in_array(page()->intendedTemplate(), option('kirby3-dejure.allowList', [])) === true) {
                return dejurify($text);
            }

            # Leave text unmodified if ..
            # (1) .. plugin is disabled (default)
            if (!option('kirby3-dejure.enabled', false)) {
                return $text;
            }

            # (2) .. page template is blocklisted
            if (in_array(page()->intendedTemplate(), option('kirby3-dejure.blockList', [])) === true) {
                return $text;
            }

            return dejurify($text);
        },
    ],
    'pageMethods' => [
        'dejurify' => function (string $text, string $ignore = ''): string
        {
            return dejurify($text, $ignore);
        },
    ],
    'fieldMethods' => [
        'dejurify' => function (Kirby\Cms\Field $field, string $ignore = '', bool $useKirbytext = true): string
        {
            if ($useKirbytext === true) {
                return dejurify($field->kt(), $ignore);
            }

            return dejurify($field, $ignore);
        },
    ],
]);
