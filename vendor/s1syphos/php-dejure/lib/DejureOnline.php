<?php

/**
 * php-dejure - Linking texts with dejure.org, the Class(y) way.
 *
 * @link https:#github.com/S1SYPHOS/php-dejure
 * @license https:#opensource.org/licenses/MIT MIT
 */

namespace S1SYPHOS;


/**
 * Class DejureOnline
 *
 * Adds links to dejure.org & caches results
 *
 * @package php-dejure
 */
class DejureOnline
{
    /**
     * Constants
     */

    /**
     * Current version of php-dejure
     */
    const VERSION = '1.2.0';

    /**
     * Current API version
     */

    const DJO_VERSION = '2.22';


    /**
     * Properties
     */

    /**
     * Cache driver
     *
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $cache;

    /**
     * Determines whether output was fetched from cache
     *
     * @var bool
     */
    public $fromCache = false;

    /**
     * Holds tokens of all possible cache drivers
     *
     * See https://github.com/terrylinooo/simple-cache
     *
     * @var array
     */
    protected $cacheDrivers = [
        'file',
        'redis',
        'mongo',
        'mysql',
        'sqlite',
        'apc',
        'apcu',
        'memcache',
        'memcached',
        'wincache',
    ];

    /**
     * Defines provider designation
     *
     * @var string
     */
    protected $provider = '';

    /**
     * Defines contact email address
     *
     * @var string
     */
    protected $email = '';

    /**
     * Determines whether citation should be linked completely or rather partially
     * Possible values: 'weit' | 'schmal'
     *
     * @var string
     */
    protected $linkStyle = 'weit';

    /**
     * Controls `target` attribute
     *
     * @var string
     */
    protected $target = '';

    /**
     * Controls `class` attribute
     *
     * @var string
     */
    protected $class = '';

    /**
     * Enables linking to 'buzer.de' if legal norm not available on dejure.org
     *
     * @var bool
     */
    protected $buzer = true;

    /**
     * Defines cache duration (in days)
     *
     * @var int
     */
    protected $cacheDuration = 2;

    /**
     * Defines timeout for API requests (in seconds)
     *
     * @var int
     */
    protected $timeout = 3;


    /*
     * Constructor
     */

    public function __construct(string $cacheDir = './.cache', string $cacheDriver = 'file', array $cacheConfig = null) {
        # Provide sensible defaults, like ..
        if (isset($_SERVER['HTTP_HOST'])) {
            # (1) .. current domain for provider designation
            $this->domain = $_SERVER['HTTP_HOST'];

            # (2) .. 'webmaster' @ current domain for contact email
            if (empty($this->email)) {
                $this->email = 'webmaster@' . $this->domain;
            }
        }

        # Initialize cache
        # (1) Create  path to caching directory (if not existent)
        $this->createDir($cacheDir);

        # (2) Determine caching options
        $cacheConfig = $cacheConfig ?? [
            'storage' => $cacheDir,
            'gc_enable' => true,
        ];

        # (3) Check provided cache driver
        if (in_array($cacheDriver, $this->cacheDrivers) === false) {
            throw new \Exception(sprintf('Cache driver "%s" cannot be initiated', $cacheDriver));
        }

        # (4) Initialize new cache object
        $this->cache = new \Shieldon\SimpleCache\Cache($cacheDriver, $cacheConfig);

        # (5) Build database when using SQLite for the first time
        # TODO: Add check for MySQL, see https://github.com/terrylinooo/simple-cache/issues/8
        if ($cacheDriver == 'sqlite' && !file_exists(join([$cacheDir, 'cache.sqlite3']))) {
            $this->cache->rebuild();
        }
    }


    /**
     * Setters & getters
     */

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setMail(string $mail): void
    {
        $this->email = $mail;
    }

    public function getMail(): string
    {
        return $this->email;
    }

    public function setLinkStyle(string $linkStyle): void
    {
        $this->linkStyle = $linkStyle;
    }

    public function getLinkStyle(): string
    {
        return $this->linkStyle;
    }

    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setBuzer(bool $buzer): void
    {
        $this->buzer = $buzer;
    }

    public function getBuzer(): string
    {
        return $this->buzer;
    }

    public function setCacheDuration(int $cacheDuration): void
    {
        $this->cacheDuration = $cacheDuration;
    }

    public function getCacheDuration(): string
    {
        return $this->cacheDuration;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getTimeout(): string
    {
        return $this->timeout;
    }


    /**
     * Functionality
     */

    /**
     * Processes linkable citations & caches text (if uncached or expired)
     *
     * @param string $text Original (unprocessed) text
     * @return string Processed text if successful, otherwise unprocessed text
     */
    public function dejurify(string $text = ''): string
    {
        # Return text as-is if no linkable citations are found
        if (!preg_match("/§|&sect;|Art\.|\/[0-9][0-9](?![0-9\/])| [0-9][0-9]?[\/\.][0-9][0-9](?![0-9\.])|[0-9][0-9], /", $text)) {
            return $text;
        }

        # Remove whitespaces from both ends of the string
        $text = trim($text);

        # Check if text was processed & cached before ..
        $result = false;

        # Build unique caching key
        $hash = $this->text2hash($text);

        # If cache file exists & its content is valid (= not expired) ..
        if ($this->cache->has($hash) === true) {
            # (1) .. report back
            $this->fromCache = true;

            # (2) .. load processed text from cache
            return $this->cache->get($hash);
        }

        # .. otherwise, process text & cache it
        return $this->connect($text);
    }


    /**
     * Processes text by connecting to API:
     * (1) Sends unprocessed text
     * (2) Receives processed text
     * (3) Checks data integrity
     * (4) Stores result in cache
     *
     * @param string $text Original (unprocessed) text
     * @return string Processed text if successful, otherwise unprocessed text
     */
    protected function connect(string $text): string
    {
        # Normalize input
        # (1) Link style only supports two possible options
        $linkStyle = in_array($this->linkStyle, ['weit', 'schmal']) === true
            ? $this->linkStyle
            : 'weit'
        ;

        # (2) Whether linking unknown legal norms to `buzer.de` or not needs to be an integer
        $buzer = (int)$this->buzer;

        # Note: Changing parameters requires manual cache reset!
        $parameters = [
            'Anbieterkennung' => $this->provider . '__' . $this->email,
            'format'          => $linkStyle,
            'target'          => $this->target,
            'class'           => $this->class,
            'buzer'           => $buzer,
            'version'         => 'php-' . self::DJO_VERSION,
            'Schema'          => 'https',
        ];

        # Build URL-encoded request string ..
        # (1) .. from unprocessed text
        $request = 'Originaltext=' . urlencode($text);

        # (2) .. required parameters
        foreach ($parameters as $key => $value) {
            $request .= '&' . urlencode($key) . '=' . urlencode($value);
        }

        # (3) .. and prepare request header
        $header = 'POST /dienste/vernetzung/vernetzen HTTP/1.0' . "\r\n";
        $header .= 'User-Agent: ' . $this->provider . ' (PHP-Vernetzung ' . self::DJO_VERSION. ')' . "\r\n";
        $header .= 'Content-type: application/x-www-form-urlencoded' . "\r\n";
        $header .= 'Content-length: ' . strlen($request) . "\r\n";
        $header .= 'Host: rechtsnetz.dejure.org' . "\r\n";
        $header .= 'Connection: close' . "\r\n";
        $header .= "\r\n";

        # Connect to API ..
        # (1) .. over encrypted connection
        if (extension_loaded('openssl')) {
            $handle = fsockopen('tls://rechtsnetz.dejure.org', 443, $errorCode, $errorMessage, $this->timeout);
        }

        # (2) .. alternatively, over unencrypted connection
        if ($handle === false) {
            $handle = fsockopen('rechtsnetz.dejure.org', 80, $errorCode, $errorMessage, $this->timeout);
        }

        # Return unprocessed text if connection ultimately fails ..
        if ($handle === false) {
            return $text;
        }

        # .. otherwise, send text for processing (until reaching timeout)
        stream_set_timeout($handle, $this->timeout, 0);
        stream_set_blocking($handle, true);
        fputs($handle, $header . $request);

        $socketTimeout = false;
        $socketEOF = false;
        $response = '';

        while (!$socketEOF && !$socketTimeout) {
            $response .= fgets($handle, 1024);
            $socketStatus = stream_get_meta_data($handle);
            $socketEOF = $socketStatus['eof'];
            $socketTimeout = $socketStatus['timed_out'];
        }

        fclose($handle);

        # Handle problems with data transmission, returning unprocessed text if ..
        # (1) .. timeout is reached or connection broke down
        if (!preg_match("/^(.*?)\r?\n\r?\n\r?\n?(.*)/s", $response, $matches)) {
            return $text;
        }

        # (2) .. status code indicates something other than successful transfer
        if (strpos($matches[1], '200 OK') === false) {
            return $text;
        }

        # (3) .. otherwise, transmission *may* have worked
        $response = $matches[2];

        # Check if processed text is shorter than unprocessed one, which indicates corrupted data
        if (strlen($response) < strlen($text)) {
            return $text;
        }

        # Verify data integrity by comparing original & modified text
        # (1) Remove whitespaces from both ends of the string
        $result = trim($response);

        # (2) Check if processed text (minus `dejure.org` links) matches original (unprocessed) text ..
        if (preg_replace("/<a href=\"https?:\/\/dejure.org\/[^>]*>([^<]*)<\/a>/i", "\\1", $text) == preg_replace("/<a href=\"https?:\/\/dejure.org\/[^>]*>([^<]*)<\/a>/i", "\\1", $result)) {
            # Build unique caching key & store result in cache
            $this->cache->set($this->text2hash($text), $result, $this->days2seconds($this->cacheDuration));

            return $result;
        }

        # .. otherwise, return original (unprocessed) text
        return $text;
    }


    /**
     * Helpers
     */

    /**
     * Converts days to seconds
     *
     * @param int $days
     * @return int
     */
    protected function days2seconds(int $days): int
    {
        return $days * 24 * 60 * 60;
    }


    /**
     * Builds hash from text length & content
     *
     * @param string $text
     * @return string
     */
    protected function text2hash(string $text): string
    {
        return strlen($text) . md5($text);
    }


    /**
     * Creates a new directory
     *
     * Source: Kirby v3 - Bastian Allgeier
     * See https://getkirby.com/docs/reference/objects/toolkit/dir/make
     *
     * @param string $dir The path for the new directory
     * @param bool $recursive Create all parent directories, which don't exist
     * @return bool True: the dir has been created, false: creating failed
     */
    protected function createDir(string $dir, bool $recursive = true): bool
    {
        if (empty($dir) === true) {
            return false;
        }

        if (is_dir($dir) === true) {
            return true;
        }

        $parent = dirname($dir);

        if ($recursive === true) {
            if (is_dir($parent) === false) {
                $this->createDir($parent, true);
            }
        }

        if (is_writable($parent) === false) {
            throw new Exception(sprintf('The directory "%s" cannot be created', $dir));
        }

        return mkdir($dir);
    }
}
