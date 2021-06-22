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

use Kirby\Http\Server;
use Kirby\Toolkit\Dir;

use S1SYPHOS\DejureOnline;


Kirby::plugin('s1syphos/kirby3-dejure', [
    'hooks' => [
        'kirbytext:after' => function ($text) {
            # Ensure that path to cache directory exists
            $cacheDir = kirby()->root('cache') . '/dejure-online.org/';

            if (!Dir::exists($cacheDir)) {
                Dir::make($cacheDir);
            }

            # Initialize DJO instance
            $object = new DejureOnline();

            # Set defaults
            $object->setCacheDir(option('kirby3-dejure.cacheDir', $cacheDir));
            $object->setProvider(option('kirby3-dejure.provider', Server::host()));
            $object->setMail(option('kirby3-dejure.mail', ''));
            $object->setLinkStyle(option('kirby3-dejure.linkStyle', 'weit'));
            $object->setTarget(option('kirby3-dejure.target', '_blank'));
            $object->setClass(option('kirby3-dejure.class', ''));
            $object->setBuzer(option('kirby3-dejure.buzer', true));
            $object->setCachePeriod(option('kirby3-dejure.cachePeriod', 2));
            $object->setTimeout(option('kirby3-dejure.timeout', 3));

            return $object->dejurify($text);
        }
    ],
]);
