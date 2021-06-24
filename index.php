<?php

/**
 * Kirby3 Dejure - Auto-linking legal norms to dejure.org for Kirby v3
 *
 * @package   Kirby CMS
 * @author    S1SYPHOS <hello@twobrain.io>
 * @link      http://twobrain.io
 * @version   1.0.0
 * @license   MIT
 */

@include_once __DIR__ . '/vendor/autoload.php';

use S1SYPHOS\DejureOnline;


function dejurify(string $text): string
{
    # Leave text unmodified if plugin is disabled (default)
    if (!option('kirby3-dejure.enabled', false)) {
        return $text;
    }

    # Ensure that path to cache directory exists
    $cacheDir = kirby()->root('cache') . '/dejure-online.org/';

    # Initialize DJO instance
    $object = new DejureOnline($cacheDir,
        option('kirby3-dejure.driver', 'file'),
        option('kirby3-dejure.caching')
    );

    # Set defaults
    $object->setProvider(option('kirby3-dejure.provider', Kirby\Http\Server::host()));
    $object->setMail(option('kirby3-dejure.mail', ''));
    $object->setLinkStyle(option('kirby3-dejure.linkStyle', 'weit'));
    $object->setTarget(option('kirby3-dejure.target', '_blank'));
    $object->setClass(option('kirby3-dejure.class', ''));
    $object->setBuzer(option('kirby3-dejure.buzer', true));
    $object->setCacheDuration(option('kirby3-dejure.cachePeriod', 2));
    $object->setTimeout(option('kirby3-dejure.timeout', 3));

    return $object->dejurify($text);
}


Kirby::plugin('s1syphos/kirby3-dejure', [
    'hooks' => [
        'kirbytext:after' => function (string $text): string
        {
            return dejurify($text);
        }
    ],
    'pageMethods' => [
        'dejurify' => function (string $text): string
        {
            return dejurify($text);
        }
    ],
    'fieldMethods' => [
        'dejurify' => function (Kirby\Cms\Field $field, bool $useKirbytext = true): string
        {
            if ($useKirbytext === true) {
                return dejurify($field->kt());
            }

            return dejurify($field);
        }
    ],
]);
