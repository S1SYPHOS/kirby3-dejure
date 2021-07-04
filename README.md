# Kirby3 Dejure
[![Release](https://img.shields.io/github/release/S1SYPHOS/kirby3-dejure.svg)](https://github.com/S1SYPHOS/kirby3-dejure/releases) [![License](https://img.shields.io/github/license/S1SYPHOS/kirby3-dejure.svg)](https://github.com/S1SYPHOS/kirby3-dejure/blob/main/LICENSE) [![Issues](https://img.shields.io/github/issues/S1SYPHOS/kirby3-dejure.svg)](https://github.com/S1SYPHOS/kirby3-dejure/issues)

This plugin automatically turns legal norms into links to their respective`dejure.org`.


## Getting started
Use one of the following methods to install & use `kirby3-dejure`:

### Git submodule

If you know your way around Git, you can download this plugin as a [submodule](https://github.com/blog/2104-working-with-submodules):

```text
git submodule add https://github.com/S1SYPHOS/kirby3-dejure.git site/plugins/kirby3-dejure
```

### Composer

```text
composer require s1syphos/kirby3-dejure
```

### Clone or download

1. [Clone](https://github.com/S1SYPHOS/kirby3-dejure.git) or [download](https://github.com/S1SYPHOS/kirby3-dejure/archive/main.zip) this repository.
2. Unzip / Move the folder to `site/plugins`.


## Configuration

You may change certain options from your `config.php` globally (`'kirby3-dejure.optionName'`):

| Option            | Type   | Default          | Description                       |
| ----------------- | ------ | ---------------- | --------------------------------- |
| `'enabled'`       | bool   | `false`          | Enables `kirbytext:after` hook    |
| `'allowList'`     | array  | `[]`             | Allowed template names            |
| `'blockList'`     | array  | `[]`             | Blocked template names            |
| `'ignore'`        | string | `''`             | Global file number ignore         |
| `'email'`         | string | `''`             | Contact mail                      |
| `'buzer'`         | bool   | `false`          | Fallback linking to 'buzer.de'    |
| `'class'`         | string | `''`             | Controls `class` attribute        |
| `'lineBreak'`     | string | `'auto'`         | Controls links across line breaks. Possible values: `'ohne'`, `'mit'`, `'auto'` |
| `'linkStyle'`     | string | `'weit'`         | Controls link range. Possible values: `'weit'`, `'schmal'` |
| `'target'`        | string | `'_blank'`       | Controls `target` attribute       |
| `'tooltip'`       | string | `'beschreibend'` | Controls `title` attribute. Possible values: `'ohne'`, `'neutral'`, `'beschreibend'`, `'Gesetze'`, `'halb'` |
| '`driver`'        | string | `'file'`         | For all cache drivers, see [here](https://github.com/terrylinooo/simple-cache) |
| '`caching`'       | array  | `[]`             | For all config options, see [here](https://github.com/terrylinooo/simple-cache) |
| `'cacheDuration'` | int    | `2`              | Cache duration (days)             |
| `'streamTimeout'` | int    | `10`             | Response stream timeout (seconds) |
| `'timeout'`       | int    | `3`              | Request timeout (seconds)         |
| `'userAgent'`     | string | `null`           | Controls `User-Agent` header      |

When enabling the plugin via `kirby3-dejure.enabled`, auto-linking is applied to all `kirbytext()` / `kt()` calls, with two exceptions:

1. If a page's `intendedTemplate()` name is allow(list)ed, this overrides `kirby3-dejure.enabled` being `false`
1. If a page's `intendedTemplate()` name is block(list)ed, this overrides `kirby3-dejure.enabled` being `true`

Besides that, there are additional methods you can use:

## Methods

There are several ways to do this, you can either use a standalone function, a page method or a field method:

### Method: `dejurify(string $text, string $ignore = ''): string`

Processes linkable citations & caches text (if uncached or expired)


### Method: `clearDJO(): bool`

Clears DJO cache


### Page method: `$page->dejurify(string $text, string $ignore = '')`

Same as `dejurify`


### Field method: `$field->dejurify(string $text, string $ignore = '', bool $useKirbytext = true)`

Same as `dejurify`, but supports applying `kirbytext()` out-of-the-box via its third parameter `$useKirbytext`.


## Roadmap

- [ ] Add tests
- [ ] Cache entries per-site (?)


## Credits / License
`kirby3-dejure` is based on [`php-dejure`](https://github.com/S1SYPHOS/php-dejure) library (an OOP port of `vernetzungsfunction.inc.php`, which can be [downloaded here](https://dejure.org/vernetzung.html). It is licensed under the [MIT License](LICENSE), but **using Kirby in production** requires you to [buy a license](https://getkirby.com/buy).

## Special Thanks
I'd like to thank everybody that's making great software - you people are awesome. Also I'm always thankful for feedback and bug reports :)
