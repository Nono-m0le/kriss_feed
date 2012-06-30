<?php
/**
 * Useful php snippets
 *
 * PHP version 5
 *
 * Features:
 * - initPHP, isUrl, isEmail, formatBBCode, formatText, getUrl, rrmdir,
 *   humanBytes, returnBytes, getMaxFileSize, smallHash
 * TODO:
 *
 */
class MyTool
{
    /**
     * Test if php version is greater than 5, set error reporting, deal
     * with magic quotes for POST, GET and COOKIE and initialize bufferization
     */
    public static function initPhp()
    {
        if (phpversion() < 5) {
            die("Argh you don't have PHP 5 !");
        }

        error_reporting(E_ALL);

        if (get_magic_quotes_gpc()) {
            $_POST = array_map('MyTool::_stripslashesDeep', $_POST);
            $_GET = array_map('MyTool::_stripslashesDeep', $_GET);
            $_COOKIE = array_map('MyTool::_stripslashesDeep', $_COOKIE);
        }

        ob_start('ob_gzhandler');
        register_shutdown_function('ob_end_flush');
    }

    /**
     * Strip slashes when magic quotes is activated
     *
     * @param string|array $value Value to remove slashes
     *
     * @return string|array       Value with no slashes
     */
    private function _stripslashesDeep($value)
    {
        return is_array($value)
            ? array_map('MyTool::_stripslashesDeep', $value)
            : stripslashes($value);
    }

    /**
     * Test if parameter is an URL
     * use http://www.php.net/manual/en/function.filter-var.php instead ?
     * 
     * @param string $url Url to check
     *
     * @return true|false True if paramater is a URL, false otherwise
     */
    public static function isUrl($url)
    {
        $pattern= "/^(https?:\/\/)(w{0}|w{3})\.?[A-Z0-9._-]+\.[A-Z]{2, 3}\$/i";

        return preg_match($pattern, $url);
    }

    /**
     * Test if parameter is an email
     * use http://www.php.net/manual/en/function.filter-var.php instead ?
     * 
     * @param string $email Email to check
     *
     * @return true|false   True if paramater is an email, false otherwise
     */
    public static function isEmail($email)
    {
        $pattern = "/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2, 4}$/i";

        return (preg_match($pattern, $email));
    }

    /**
     * Format given text using BBCode with corresponding tags
     *
     * @param string $text BBCodeText to format
     *
     * @return string      Converted text
     */
    public static function formatBBCode($text)
    {
        $replace = array(
            '/\[m\](.+?)\[\/m\]/is'
            => '/* moderate */',
            '/\[b\](.+?)\[\/b\]/is'
            => '<strong>$1</strong>',
            '/\[i\](.+?)\[\/i\]/is'
            => '<em>$1</em>',
            '/\[s\](.+?)\[\/s\]/is'
            => '<del>$1</del>',
            '/\[u\](.+?)\[\/u\]/is'
            => '<span style="text-decoration: underline;">$1</span>',
            '/\[url\](.+?)\[\/url]/is'
            => '<a href="$1">$1</a>',
            '/\[url=(\w+:\/\/[^\]]+)\](.+?)\[\/url]/is'
            => '<a href="$1">$2</a>',
            '/\[quote\](.+?)\[\/quote\]/is'
            => '<blockquote>$1</blockquote>',
            '/\[code\](.+?)\[\/code\]/is'
            => '<code>$1</code>',
            '/\[([^[]+)\|([^[]+)\]/is'
            => '<a href="$2">$1</a>'
            );
        $text = preg_replace(
            array_keys($replace),
            array_values($replace),
            $text
        );

        return $text;
    }

    /**
     * Format text to emphasize html, php, URL and wikipedia URL
     *
     * @param string $text Original text to format
     *
     * @return string      Converted text
     */
    public static function formatText($text)
    {
        $text = preg_replace_callback(
            '/<code_html>(.*?)<\/code_html>/is',
            create_function(
                '$matches',
                'return htmlspecialchars($matches[1]);'
            ),
            $text
        );
        $text = preg_replace_callback(
            '/<code_php>(.*?)<\/code_php>/is',
            create_function(
                '$matches',
                'return highlight_string("<?php $matches[1] ?>", true);'
            ),
            $text
        );
        $text = preg_replace('/<br \/>/is', '', $text);

        $text = preg_replace(
            '#(^|\s)([a-z]+://([^\s\w/]?[\w/])*)(\s|$)#im',
            '\\1<a href="\\2">\\2</a>\\4',
            $text
        );
        $text = preg_replace(
            '#(^|\s)wp:?([a-z]{2}|):([\w]+)#im',
            '\\1<a href="http://\\2.wikipedia.org/wiki/\\3">\\3</a>',
            $text
        );
        $text = str_replace(
            'http://.wikipedia.org/wiki/',
            'http://www.wikipedia.org/wiki/',
            $text
        );
        $text = str_replace('\wp:', 'wp:', $text);
        $text = str_replace('\http:', 'http:', $text);
        $text = MyTool::formatBBCode($text);
        $text = nl2br($text);

        return $text;
    }

    /**
     * Return URL website
     *
     * @return string URL website
     */
    public static function getUrl()
    {
        $url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $url = preg_replace('/([?&].*)$/', '', $url);

        return $url;
    }

    /**
     * Remove not empty directory using unlink
     *
     * @param $string $dir Directory to remove
     */
    public static function rrmdir($dir)
    {
        if (is_dir($dir) && ($d = @opendir($dir))) {
            while (($file = @readdir($d)) !== false) {
                if ( $file == '.' || $file == '..' ) {
                    continue;
                } else {
                    unlink($dir . '/' . $file);
                }
            }
        }
    }

    /**
     * Convert a number of bytes into human readable number of bytes
     * http://www.php.net/manual/fr/function.disk-free-space.php#103382
     * 
     * @param integer $bytes Number of bytes to convert into human readable
     *
     * @return string        String of human readable number of bytes
     */
    public static function humanBytes($bytes)
    {
        $siPrefix = array( 'bytes', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
        $base = 1024;
        $class = min((int) log($bytes, $base), count($siPrefix) - 1);
        $val = sprintf('%1.2f', $bytes / pow($base, $class));

        return $val . ' ' . $siPrefix[$class];
    }

    /**
     * Convert post_max_size/upload_max_filesize (eg.'16M') parameters to bytes.
     *
     * @param string $val Value to convert
     *
     * @return interg     Number of bytes corresponding to the given value
     */
    public static function returnBytes($val)
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last)
        {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
        }

        return $val;
    }

    /**
     * Try to determine max file size for uploads (POST).
     * http://sebsauvage.net/wiki/doku.php?id=php:shaarli
     *
     * @return integer Number of bytes
    */
    public static function getMaxFileSize()
    {
        $sizePostMax   = MyTool::returnBytes(ini_get('post_max_size'));
        $sizeUploadMax = MyTool::returnBytes(ini_get('upload_max_filesize'));

        // Return the smaller of two:
        return min($sizePostMax, $sizeUploadMax);
    }

    /**
     * Returns the small hash of a string
     * http://sebsauvage.net/wiki/doku.php?id=php:shaarli
     * eg. smallHash('20111006_131924') --> yZH23w
     * Small hashes:
     * - are unique (well, as unique as crc32, at last)
     * - are always 6 characters long.
     * - only use the following characters: a-z A-Z 0-9 - _ @
     * - are NOT cryptographically secure (they CAN be forged)
     *
     * @param string $text Text to convert into small hash
     *
     * @return string      Small hash corresponding to the given text
     */
    public static function smallHash($text)
    {
        $t = rtrim(base64_encode(hash('crc32', $text, true)), '=');
        // Get rid of characters which need encoding in URLs.
        $t = str_replace('+', '-', $t);
        $t = str_replace('/', '_', $t);
        $t = str_replace('=', '@', $t);

        return $t;
    }

}