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
     * Current version of php-dejure
     */
    const VERSION = '1.0.0';


    /**
     * Properties
     */

    /**
     * Path to caching directory
     *
     * @var string
     */
    public $cacheDir = ' ./tmp/';

    /**
     * Name of provider designation
     *
     * @var string
     */
    public $provider = '';

    /**
     * Contact mail address
     *
     * @var string
     */
    public $mail = '';

    /**
     * Determines whether citation should be linked completely or rather partially
     * Possible values: 'weit' | 'schmal'
     *
     * @var string
     */
    public $linkStyle = 'weit';

    /**
     * Controls `target` attribute
     *
     * @var string
     */
    public $target = '';

    /**
     * Controls `class` attribute
     *
     * @var string
     */
    public $class = '';

    /**
     * Enables linking to 'buzer.de' if legal norm not available on dejure.org
     *
     * @var bool
     */
    public $buzer = true;

    /**
     * Cache period (in days)
     *
     * @var int
     */
    public $cachePeriod = 2;

    /**
     * Timeout period for API requests (in seconds)
     *
     * @var int
     */
    public $timeout = 3;


    /*
     * Constructor
     */

    public function __construct(string $cacheDir = null)
    {
        # Define current API version
        define('DJO_VERSION', '2.22');

        # Determine path to caching path
        if (isset($cacheDir)) {
            $this->cacheDir = $cacheDir;
        }

        # Determine provider designation & webmaster mail
        if (isset($_SERVER['HTTP_HOST'])) {
            $this->domain = $_SERVER['HTTP_HOST'];

            if (empty($this->mail)) {
                $this->mail = 'webmaster@' . $this->domain;
            }
        }
    }


    /**
     * Setters & getters
     */

    public function setCacheDir(string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

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
        $this->mail = $mail;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function setLinkStyle(string $linkStyle): void
    {
        if (in_array($linkStyle, ['weit', 'schmal'])) {
            $this->linkStyle = $linkStyle;
        }
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

    public function setCachePeriod(int $cachePeriod): void
    {
        $this->cachePeriod = $cachePeriod;
    }

    public function getCachePeriod(): string
    {
        return $this->cachePeriod;
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
     * dejurify()
     *
     * Wrapper for `djo_ausgabepuffer`
     */
    public function dejurify(string $string = '')
    {
        return $this->djo_ausgabepuffer($string);
    }


    /**
     * djo_ausgabepuffer
     *
     * Hauptfunktion. Initiiert Vernetzung.
     * Prueft vorher auf zu vernetzende Inhalte.
     * Bricht ab wenn nichts zu tun ist.
     * @return String mit dem vernetzten/unvernetzten Text.
     */
    public function djo_ausgabepuffer($ausgabetext = '') {
        # zu vernetzende Inhalte im Text? Ansonsten Originaltext zurueck
        if (!preg_match("/§|&sect;|Art\.|\/[0-9][0-9](?![0-9\/])| [0-9][0-9]?[\/\.][0-9][0-9](?![0-9\.])|[0-9][0-9], /", $ausgabetext)) {
            return $ausgabetext;
        }

        $arr_parameter = [
            'Anbieterkennung'       => $this->provider . '__' . $this->mail,
            'format'                => $this->linkStyle,
            'target'                => $this->target,
            'class'                 => $this->class,
            'buzer'                 => (int)$this->buzer,
            'zeitlimit_in_sekunden' => $this->timeout,
            'version'               => 'php-' . DJO_VERSION,
            'Schema'                => 'https',
        ];

        # Abfragen des Cache, ob der Text schon gespeichert ist
        $rueckgabe = $this->vernetzen_ueber_cache($ausgabetext);

        if (empty($rueckgabe)) {
            # Kein Cache, Anfrage an dejure.org senden
            $rueckgabe = $this->vernetzen_ueber_dejure_org($ausgabetext, $arr_parameter);
        }

        # Einmal zwischen 0 und 6 Uhr alle Dateien im Cacheverzeichnis loeschen,
        # die aelter als X Tage sind. Dies loest regelmaessig eine aktualisierende
        # Neuvernetzung aus
        if (date('G') < 6) {
            $this->cache_dateien_loeschen();
        }

        return $rueckgabe;
    }


    /**
     * vernetzen_ueber_cache
     *
     * Holt eventuell vorhandenen vernetzten Text aus dem Cache.
     * @return String mit vernetzten Text oder Bool false
     */
    private function vernetzen_ueber_cache($ausgangstext) {
        # Cache-Verzeichnis und Cache-Dauer definiert?
        if (isset($this->cacheDir) && isset($this->cachePeriod)) {
            $vernetzungscache = $this->cacheDir;
            $cache_dauer = $this->cachePeriod;
        }

        if (empty($vernetzungscache) || !is_dir($vernetzungscache) || !is_numeric($cache_dauer)) {
            return false;
        }

        $ausgangstext = trim($ausgangstext);

        # Cache Vorhaltedauer in Sekunden wandeln
        $cache_dauer = $cache_dauer * 24 * 60 * 60;
        $schluessel = strlen($ausgangstext) . md5($ausgangstext);

        if (file_exists($vernetzungscache . $schluessel)) {
            # Datei aelter als Cache-Dauer? Neu vernetzen
            if (filemtime($vernetzungscache . $schluessel) < time() - $cache_dauer) {
                return false;
            }

            # Cache-Datei auslesen und zurueck geben
            return file_get_contents($vernetzungscache . $schluessel);
        }

        # Kein Cache vorhanden. Neu vernetzen
        return false;
    }


    /**
     * ablegen_in_cache
     *
     * Legt vernetzten Text im Cache ab.
     * @return none
     */
    private function ablegen_in_cache($ausgangstext, $rueckgabe) {
        if (isset($this->cacheDir)) {
            $vernetzungscache = $this->cacheDir;
        }

        if (!empty($vernetzungscache) && is_dir($vernetzungscache)) {
            $schluessel = strlen($ausgangstext).md5($ausgangstext);
            $d = fopen($vernetzungscache . $schluessel, "w");

            if ($d !== false) {
                fwrite($d, $rueckgabe);
                fclose($d);
            }
        }
    }


    /**
     * cache_dateien_loeschen
     *
     * Entfernt veraltete Cache-Dateien
     * @return Array der geloeschten Dateien fuer Debug-Zwecke
     */
    public function cache_dateien_loeschen() {
        if (isset($this->cacheDir) && isset($this->cachePeriod)) {
            $vernetzungscache = $this->cacheDir;
            $cache_dauer = $this->cachePeriod;
        }

        if (empty($vernetzungscache) || !is_dir($vernetzungscache) || !is_numeric($cache_dauer)) {
            return false;
        }

        # Cache Vorhaltedauer in Sekunden wandeln
        $cache_dauer = $cache_dauer * 24 * 60 * 60;

        # Zeitpunkt des letzten Loeschens abfragen
        if (!file_exists($vernetzungscache . 'cache_status')) {
            $this->cache_status_datei();

            return;
        }

        $cache_status = file_get_contents($vernetzungscache . 'cache_status');

        if (time() - $cache_status > 24 * 60 * 60) { # 86400 -- Wenn letztes Loeschen 24h zurueck liegt
            $dateien = scandir($vernetzungscache);
            $geloescht = [];

            if (!empty($dateien[0])) {
                foreach ($dateien as $datei) {
                    if (!in_array($datei, [". ", ".. ", "vernetzungsfunction.inc.php"])) {
                        $file_time = filemtime($vernetzungscache . $datei);

                        # Datei aelter als Vorhaltedauer Stunden, dann loeschen
                        if ($file_time < (time() - $cache_dauer)) {
                            unlink($vernetzungscache . $datei);
                            $geloescht[] = $vernetzungscache . $datei;
                        }
                    }
                }
            }

            # Status-Datei neu erzeugen
            $this->cache_status_datei($verzeichnis);

            return $geloescht;
        }
    }


    /**
     * cache_status_datei
     *
     * Legt eine Datei mit dem letzten Zeitpunkt der Cache-Saeuberung an
     * @return none
     */
    private function cache_status_datei() {
        $cache_zeitstempel = mktime(0, 0, 0, date('d'), date('m'), date('Y'));
        $file_handle = fopen($this->cacheDir . 'cache_status', 'w');

        fputs($file_handle, $cache_zeitstempel);
        fclose($file_handle);
    }


    /**
     * integritaetskontrolle_und_cache
     *
     * Prueft, ob der von der Vernetzung zurueck gegebene Text mit entfernten
     * dejure.org Links dem Originaltext gleicht. Unterscheiden sich wenn es
     * einen Fehler bei der Vernetzung gab.
     * @return String mit dem Originaltext
     */
    private function integritaetskontrolle_und_cache($ausgangstext, $neuertext) {
        # pruefen, ob zurueckgegebener, vernetzter Text bis auf die hinzugefuegten Links dem Original gleicht
        $neuertext    = trim($neuertext);
        $ausgangstext = trim($ausgangstext);

        if (preg_replace("/<a href=\"https?:\/\/dejure.org\/[^>]*>([^<]*)<\/a>/i", "\\1", $ausgangstext) == preg_replace("/<a href=\"https?:\/\/dejure.org\/[^>]*>([^<]*)<\/a>/i", "\\1", $neuertext)) {
            # Alles in Ordnung. Vernetzten Text im Cache speichern und zurueck geben
            $this->ablegen_in_cache($ausgangstext, $neuertext);

            return $neuertext;

        } else {
            # Texte unterschiedlich. Originaltext zurueck geben
            return $ausgangstext;
        }
    }


    /**
     * vernetzen_ueber_dejure_org
     *
     * Sendet den Originaltext an dejure.org und erhaelt den vernetzten Text.
     * Erste Pruefung, ob Vernetzung fehlerfrei ablief.
     * @return String Vernetzter Text bei Erfolg. Ansonsten Originaltext.
     */
    private function vernetzen_ueber_dejure_org($ausgangstext, $parameter = []) {
        # Moegliche Parameter: Anbieterkennung / Dokumentkennung / target / class / linkstil / zeitlimit_in_sekunden
        # Hinweis: Bei Aenderung dieser Einstellungen muss der Cache manuell geloescht werden
        $ausgangstext = trim($ausgangstext);
        $uebergabe = 'Originaltext=' . urlencode($ausgangstext);

        foreach ($parameter as $option => $wert) {
            if ($option == 'zeitlimit_in_sekunden') {
                $zeitlimit_in_sekunden = $wert;

            } else {
                $uebergabe .= '&' .urlencode($option) . '=' .urlencode($wert);
            }
        }

        if (empty($zeitlimit_in_sekunden) || !is_numeric($zeitlimit_in_sekunden)) {
            $zeitlimit_in_sekunden = 3;
        }

        $header = 'POST /dienste/vernetzung/vernetzen HTTP/1.0' . "\r\n";
        $header .= 'User-Agent: ' . $this->provider. ' (PHP-Vernetzung ' . DJO_VERSION. ')' . "\r\n";
        $header .= 'Content-type: application/x-www-form-urlencoded' . "\r\n";
        $header .= 'Content-length: ' .strlen($uebergabe). "\r\n";
        $header .= 'Host: rechtsnetz.dejure.org' . "\r\n";
        $header .= 'Connection: close' . "\r\n";
        $header .= "\r\n";

        if (extension_loaded('openssl')) {
            $fp = fsockopen('tls://rechtsnetz.dejure.org', 443, $errno, $errstr, $zeitlimit_in_sekunden);
        }

        if (!$fp) {
            $fp = fsockopen('rechtsnetz.dejure.org', 80, $errno, $errstr, $zeitlimit_in_sekunden);
        }

        if (!$fp) { # Verbindung gescheitert. Originaltext zurueck geben
            return $ausgangstext;

        } else {
            stream_set_timeout($fp, $zeitlimit_in_sekunden, 0); # Verbindung nach $zeitlimit_in_sekunden Sekunden abbrechen
            stream_set_blocking($fp, true);
            fputs($fp, $header . $uebergabe);

            $timeOutSock = false;
            $eofSock = false;
            $rueckgabe = '';

            while (!$eofSock && !$timeOutSock) {
                $rueckgabe .= fgets($fp, 1024); #
                $stSock = stream_get_meta_data($fp);
                $eofSock = $stSock['eof'];
                $timeOutSock = $stSock['timed_out'];
            }

            fclose($fp);

            if (!preg_match("/^(.*?)\r?\n\r?\n\r?\n?(.*)/s",$rueckgabe, $rueckgabeARR)) {
                return $ausgangstext; # Zeitueberschreitung oder Verbindungsproblem

            } else if (strpos($rueckgabeARR[1], '200 OK') === false) {
                return $ausgangstext; # sonstiges Serverproblem

            } else {
                $rueckgabe = $rueckgabeARR[2];

                # Vernetzter Text kleiner als Ausgangstext. Eventuell Fehler bei Uebertragung
                if (strlen($rueckgabe) < strlen($ausgangstext)) {
                    return $ausgangstext;
                }

                # Vernetzung erfolgreich. Rueckgabe auf Fehler pruefen
                return $this->integritaetskontrolle_und_cache($ausgangstext, $rueckgabe);
            }
        }
    }
}
