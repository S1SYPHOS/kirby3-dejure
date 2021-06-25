<?php

/**
 * Kirby3 Dejure - Auto-linking legal norms to dejure.org for Kirby v3
 *
 * @package   Kirby CMS
 * @author    S1SYPHOS <hello@twobrain.io>
 * @link      http://twobrain.io
 * @version   1.1.0
 * @license   MIT
 */

@include_once __DIR__ . '/vendor/autoload.php';

use S1SYPHOS\DejureOnline;


function dejurify(string $text, string $ignore = ''): string
{
    # Leave text unmodified if plugin is disabled (default)
    if (!option('kirby3-dejure.enabled', false)) {
        return $text;
    }

    # Ensure that path to cache directory exists
    $cacheDir = kirby()->root('cache') . '/dejure-online.org/';

    # Initialize DJO instance
    $object = new DejureOnline(
        option('kirby3-dejure.driver', 'file'),
        option('kirby3-dejure.caching', ['storage' => $cacheDir])
    );

    # Set defaults

    # (1) General information
    $object->setEmail(option('kirby3-dejure.mail', ''));

    # (2) Text processing
    $object->setBuzer(option('kirby3-dejure.buzer', true));
    $object->setClass(option('kirby3-dejure.class', ''));
    $object->setLineBreak(option('kirby3-dejure.lineBreak', 'auto'));
    $object->setLinkStyle(option('kirby3-dejure.linkStyle', 'weit'));
    $object->setTarget(option('kirby3-dejure.target', '_blank'));
    $object->setTooltip(option('kirby3-dejure.tooltip', 'neutral'));

    # (3) Connection
    $object->setStreamTimeout(option('kirby3-dejure.streamTimeout', 10));
    $object->setTimeout(option('kirby3-dejure.timeout', 3));
    $object->setUserAgent(option('kirby3-dejure.userAgent', 'kirby3-dejure @ ' . Kirby\Http\Server::host()));

    # (4) Caching
    $object->setCacheDuration(option('kirby3-dejure.cachePeriod', 2));

    return $object->dejurify($text, $ignore);
}


Kirby::plugin('s1syphos/kirby3-dejure', [
    'hooks' => [
        'kirbytext:after' => function (string $text): string
        {
            return dejurify($text, option('kirby3-dejure.ignore', ''));
        }
    ],
    'pageMethods' => [
        'dejurify' => function (string $text, string $ignore = ''): string
        {
            return dejurify($text, $ignore);
        }
    ],
    'fieldMethods' => [
        'dejurify' => function (Kirby\Cms\Field $field, string $ignore = '', bool $useKirbytext = true): string
        {
            if ($useKirbytext === true) {
                return dejurify($field->kt(), $ignore);
            }

            return dejurify($field, $ignore);
        }
    ],
]);
