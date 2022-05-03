<?php

namespace hb;

class Exception extends \Exception
{
    /* array */ public $payload;      // optional hash payload

    function __construct(string $msg, int $code = 0, array $payload = []) {
        $this->payload = $payload;
        parent::__construct($msg, $code);
    }
}

// Non recoverable Error
// thrown by error_if, error_unless
class Error extends \Error
{
    /* array */ public $payload;      // optional hash payload

    function __construct(string $msg, int $code = 0, array $payload = []) {
        $this->payload = $payload;
        parent::__construct($msg, $code);
    }
}

/**
 * Functions in HB namespace.
 *
 * Utility functions
 * Place framework specific methods in \hb\HB class
 *
 * @param mixed $spec
 */

/**
 * Dependency Injection Container
 * I::service()             // Autogenerated class with return class Tips for Editors
 * I(service)
 * I(service, $spec)        // service instance spec scalar or [key => value]
 * Example: i('db', "ConnectionName"), i('log', ['host' => 'x', 'port' => 514])
 *
 * @see I::set($service, $spec, $instance)
 * @see I::reset($service, $spec)
 */
function I(string $name, /* array | string */ $spec = []) {
    // instance
    $key = '[]';
    if (!\is_array($spec)) {
        $spec = [$spec];
    }
    if ($spec) {
        if (\count($spec) > 1) {
            ksort($spec);   // key order is unimportant
        }
        $key = json_encode($spec);
    }
    if ($o = \hb\HB::$I[$name][$key] ?? null) {
        return $o;
    }

    return \hb\I::_get($name, $spec, $key);
}

/**
 * \hb\Object instances created using their own factory controller: method i(...$params)
 *
 * @param array ...$p
 *
 * @return Instance
 */
function iNew(string $className, ...$p) {
    // Instance
    error_unless($className, 'iNew(empty-string)');
    if (is_a($className, '\hb\contracts\IConfig', true)) {
        return $className::i(...$p);
    }
    // v("iNew: $className");
    return new $className(...$p);
}

/**
 * Decorator pattern:.
 *
 * 1.  Decorate Callable with Callable, return Callable
 *     options passed to decorator via $decorator_opts = []
 *
 *     $o         = Callable or "Class::method"
 *     $decorator = Callable or "Class::method" or "DecoratorName"
 *
 * 2.  Decorate Instance with Callable, return new Instance
 *     all methods in new Instance decorated.
 *     options passed to decorator via $decorator_opts = []
 *
 *     $o         = Instance or "Class"
 *     $decorator = Callable or "Class::method" or "DecoratorName"
 *
 * 3.  Decorate Instance($o) with Instance($d), return new Instance
 *     methods from $d replace $o-methods
 *     if decorator instance implements \hb\contracts\DecoratorInstance
 *     it can wrap replaced methods
 *
 *     $o         = Instance or "Class"
 *     $decorator = Instance (optionally implementing \hb\contracts\DecoratorInstance)
 *
 * 4. PHP-DOC decorator - get decorators+decorators_options from phpDoc comments, cache them in APC
 *    Usage: decorate($o)
 *    sample php-doc decorations:
 *    /**
 *     * @@DecoratorName
 *     * @@DecoratorName("option1", ...)
 *     *
 *     ...
 *    use decorate($o)->__phpDoc() to see decorated classes
 *
 * Named Decorators:
 *    Framework provides lots of build-in decorators.
 *    Profiling, Logging, Tracking, Caching,
 *    check \hbc\decorator\Bundle for complete list
 *
 * Decorator specification:
 *    function(callable $method, array $args, array $opts)
 *    - when decorating instance $method is [$instance, "methodName"]
 *    - when decorating closure/callable $method is that callable
 *
 * @param mixed $o
 * @param mixed $decorator
 */
function decorate(/* mixed */ $o, /* callable | "default" */ $decorator = '', array $decorator_opts = []) {
    if (!$decorator) {
        return i('decorator')->get($o, ...$decorator_opts);
    }

    return \hbc\decorator\Decorator::decorate($o, $decorator, $decorator_opts);
}

// same as decorate($o, $decorator) but with strict type check
function decorateInstance(object $o, object $decorator): object {
    return \hbc\decorator\Decorator::mix($o, $decorator);
}

/**
 * decorate object $o instance with several object decorators
 * !! different from nested decorateInstance calls,
 * all decorators decorate $o, not each other !!
 */
function decorateMany(object $o, array $decorators): object {
    return \hbc\decorator\Decorator::mixMany($o, $decorators);
}

/**
 * all external method calls are profiled.
 *
 * profileInstance($i)->method(...)
 */
function profileInstance(object $i): object {
    return decorate($i, 'profiler');
}

/**
 * Cache method/closure/direct - default adapter is APC (Shared Memory Cache).
 *
 * Basic Usage:
 *     cache([$key_prefix => $mixed], $options=[])
 *     cache($mixed, $options=[])
 *
 *     cache($instance | "ClassName")->method(...)
 *     cache()[$KEY]  <=> get/set specific keys
 *
 * Usage:
 *     cache(['key_prefix' => $instance])->method(...)
 *     cache($instance)->_update("method", ...)
 *     cache($instance)->_delete("method", ...)
 *
 *     cache("class::method")->call(...)
 *     cache(["key_prefix" => $closure])->call(...)     // key-prefix required for closures
 *
 *     cache()[$KEY]  <=> get/set specific keys
 *     cache(['key_prefix' => 0])[$KEY]  <=> get/set specific keys
 *
 * Advanced `cache()` Usage:
 *     cache()->ttl([$sec, $add_rnd_percent])[$KEY]
 *     ->add($key, $value): bool
 *     ->inc($key, $by): bool
 *     ->dec($key, $by): bool
 *     ->cas($key, $old, $new): bool
 *
 * Advanced*2
 *     Attention! this methods do not know anything about key_prefix and ttl
 *     cache()->nativeAdapterMethod(...)   << does not know about TTL and key_prefixes
 *
 * Options:
 *     (array)  ttl      - [timeout, timeout_randomize_prc] - default [3600, 30] -- 3600..3600+30%
 *     (string) adapter  - default 'cache/shm'
 *     (string) ds       - DS name (replace adapter)
 *
 * Limitations:
 *
 *   1. cached methods should not depend on object state.
 *      pass your object state in key_prefix
 *   2. cached method parameters should be JSON-able
 *   3. final KEY should have reasonable length (classname + state + arguments)
 *
 * @param mixed $o
 */
function cache(/* mixed */ $o = 0, array $opts = []) {
    // cached-value | false | \Error Exception
    return \hbc\cache\Wrapper::i($o, $opts);
}

/*
 * No-Cache cache() replacements.
 * used for debug.
 * Usage: "use function hb\noCache as cache"
 */
function noCache($o = 0, array $opts = []) {
    return \hbc\cache\Wrapper::noCache($o, $opts);
}

/**
 * Network Cache - i('cache') wrapper, default - memcache(host='memcache').
 *
 * @see cache for examples
 *
 * @param mixed $o
 */
function iCache(/* mixed */ $o = 0, array $opts = []) {
    // @todo - use \hbc\decorator\CacheWrapper($o, [cacher => iCacheNotNull, deleter => iCacheDelete])
    return \hbc\cache\Wrapper::i($o, ['adapter' => i('cache')] + $opts);
}

/**
 * Permanent Cache Storage in any DS - - default i("ds", "cache")
 * use mysql, riak, redis, json-file or whatever.
 *
 *     'ds'                   - DS (Data Storage Object or Name
 *                            - string $ds as in i("ds", $ds) or DS object
 *
 * @see cache for examples
 *
 * @param mixed $o
 */
function cacheDS(/* mixed */ $o = 0, string $ds = 'cache') {
    return \hbc\cache\Wrapper::i($o, ['ds' => $ds]);
}

/**
 * Project+App prefixed iCache.
 *
 * @param mixed $o
 */
function appCache(/* mixed */ $o = 0, array $opts = []) {
    return icache($o, ['key_prefix' => UNAME] + $opts);
}

/**
 * Git Revision Cache (SHM-Cache).
 *
 * @param mixed $o
 */
function gitCache($o, array $opts = []) {
    return cache($o, ['key_prefix' => HB::gitRevision()] + $opts);
}

/**
 * First Match
 *
 * @TODO - deprecate - use Str::fm()
 */
function fm(string $regexp, string $str) { // First Match
    preg_match($regexp, $str, $m);

    return @$m[1];
}

// anything to ~ PHP string with unprintable characters replaced
// ATTENTION: may/will intentionally lose data !!
// will try to fit result in ~200 characters
function x2s(/* mixed */ $x, int $deep = 0): string {
    if ($deep > 10) {
        return "'nesting too deep!!'";
    }
    if (\is_string($x)) {
        $x = _x2s_cut($x, 200, 50);
        // all unprintable characters presented as \$ASCII_CODE_2DIGIT-HEX
        // \r and \n presented as \r and \n
        $f = function ($a) {
            $o = \ord($a[0]);
            if (0xd === $o) {
                return '\r';
            }
            if (0xa === $o) {
                return '\n';
            }

            return sprintf('\\%02x', $o);
        };

        return var_export(preg_replace_callback('/[^[:print:]]/', $f, $x), true);
    }
    if (null === $x) {
        return 'NULL';
    }
    if (\is_bool($x)) {
        return $x ? 'true' : 'false';
    }
    if (\is_object($x)) {
        return '"Class:'.\get_class($x).'"';
    }
    if (\is_int($x)) {
        return $x;
    }
    if (\is_float($x)) {
        return sprintf('%G', $x); // short presentation of float
    }
    if (!\is_array($x)) {
        return x2s($x, $deep + 1);
    }
    if (($cnt = \count($x)) > 20) { // slice long arrays
        $x = array_merge(\array_slice($x, 0, 10), ['...['.(\count($x) - 19).']...'], \array_slice($x, -9));
        // return "\"... $cnt items\"";
    }
    $t = [];
    $i = 0;
    foreach ($x as $k => $v) {
        $q = ($i === $k) ? '' : "\"{$k}\"=>";
        ++$i;
        $t[] = $q.x2s($v, $deep + 1);
    }
    $s = _x2s_cut(implode(', ', $t), 200, 50);

    return "[{$s}]";
}

// x2s helper
function _x2s_cut($s, $len, $at) {
    if (\strlen($s) <= $len) {
        return $s;
    }
    $skip = \strlen($s) - $len;

    return '"'.substr($s, 0, $len - $at)."...({$skip})...".substr($s, -($at - 12));
}

// conditional sprintf
// Ex: cs(", %s", $word) << WRONG ORDER
function cs(string $fmt_true, /* mixed */ $val, string $fmt_false = '') {
    // @TODO - DEPRECATE !!! >> or move to CS($str, $format_true, $format_false)
    return $val ? sprintf($fmt_true, $val) : ($fmt_false ? sprintf($fmt_false, $val) : '');
}

// caller file & line as string
function caller(int $level = 1) {
    // "file:line"
    $t = debug_backtrace()[$level];

    return "{$t['file']}:{$t['line']}";
}

/**
 * return Number-of-Missed-Events once during $timeout (for all php processes)
 * statistics kept in APC.
 *
 * Useful for throtling error messages.
 * $key can be ommited, in this case "$filename:$line" will be used as a key
 *
 * Example 1:
 * while(1) {
 *   if(once("event-name", 5))
 *     echo "text will be printed once, every 5 seconds";
 *   usleep(100000);
 * }
 * Example 2:
 *  if ($c = once($key, 10, 5))
 *      I::Log()->error("$c events occured for key=$key in last 10 seconds"); // only cases with 6+ events !!
 * Example 3: - log once every 10+ seconds
 * if ($cnt = once())
 *   i('log')->error("$cnt errors in last 10 seconds");
 */
function once(string $key = '', int $timeout = 10, int $skip_events = 0) {
    // int|false
    if (!$key) {
        $key = caller();
    }
    $data = apcu_fetch($key);
    $now = time();
    // first time
    if (!$data) {
        return (int) apcu_add($key, [1, $now], $timeout) && !$skip_events; // return 1
    }
    // increment
    if ($data[1] + $timeout >= $now || ($skip_events && $skip_events > $data[0])) {
        return !apcu_store($key, [++$data[0], $data[1]], $timeout); // return false
    }
    // expired
    apcu_store($key, [1, $now], $timeout);

    return $data[0]; // return inc
}

/**
 * Perls qw ( Quote Words ) alike
 * non string input returned w/o processing
 * supports hash definition.
 *
 * entry_delimiter   - entry delimiter
 * key_value_delimiter   - key/value delimiter
 *
 * example: qw("a b c:Data") == ["a", "b" , "c" => "Data"]
 *
 * @param mixed $data
 */
function qw($data, string $entry_delimiter = ' ', string $key_value_delimiter = ':'): array {
    if (!\is_string($data)) {
        return $data;
    }
    if (!$data) {
        return [];
    }
    $res = ' ' === $entry_delimiter ? preg_split('/\s+/', trim($data)) : explode($entry_delimiter, $data);
    if (!strpos($data, $key_value_delimiter)) {
        return $res;
    }
    $ret = [];
    foreach ($res as $r) {
        if ($p = strpos($r, $key_value_delimiter)) {
            $ret[substr($r, 0, $p)] = substr($r, $p + 1);
        } else {
            $ret[] = $r;
        }
    }

    return $ret;
}

/**
 * qw like function, Quote Keys
 * example: qk("a b c:Data") == array( "a" => true, "b"=> true , "c" => "Data").
 *
 * @param mixed $data
 */
function qk($data, string $entry_delimiter = ' ', string $key_value_delimiter = ':'): array {
    // hash
    if (!\is_string($data)) {
        return $data;
    }
    if (!$data) {
        return [];
    }
    $res = ' ' === $entry_delimiter ? preg_split('/\s+/', trim($data)) : explode($entry_delimiter, $data);
    $ret = [];
    foreach ($res as $r) {
        if ($p = strpos($r, $key_value_delimiter)) {
            $ret[substr($r, 0, $p)] = substr($r, $p + 1);
        } else {
            $ret[$r] = true;
        }
    }

    return $ret;
}

/**
 * is_admin - is web-visitor is admin
 *            return "cli" for cli clients.
 *
 * use default method specified in
 *
 * In order to use admin methods -
 *  configure existing is_admin methods
 *  or provide your method
 *
 *  @see config "is_admin" node
 *
 * TODO - PROVIDE SAMPLE IMPLEMENTATION FOR IS_ADMIN
 *   a) Specific IPs / IP blocks
 *   b) Cookie
 *   c) HTTP-HEADER
 *   d) client HTTPS certificate (recommended) - http://nategood.com/client-side-certificate-authentication-in-ngi
 */
function is_admin(string $name = ''): string {
    // "current-admin-name" | ""
    if ($name) {
        return $name === is_admin() ? $name : '';
    }

    return 1; /** @todo FIX-ME */
    $a = &HB::$CONFIG['.is_admin'];
    if (null !== $a) {
        return $a; // 99%
    }
    //if (! @HB::$CONFIG['is_admin'])
    //    return; // still initializing
    if (\PHP_SAPI === 'cli') { // already set in HB::initCli
        return $a = 'cli';
    }
    //$m = (string) C("is_admin.method", ""); // "Class::method"
    $m = (string) @HB::$CONFIG['is_admin']['method']; // "Class::method"  - is_admin can be called before CONFIG init

    return $a = $m ? $m() : '';
}

class TODO_Exception extends \hb\Error
{
}
function todo(string $str = '') {
    throw new TODO_Exception($str);
}

// COLORED sprintf for (mostly) for CLI mode
// @ see i('cli')
// Ex:
//  \hb\e("{red}{bold}Sample {bg_green}{white}$text{/}")      << as is, no sprintf
//  \hb\e("{red}{bold}Sample {bg_green}{white}%s{/}", $text)  << use sprintf
function e($format, ...$args) {
    // @todo("implement stylish array presenation");
    if (\is_array($format)) {
        $format = x2s($format);
    }
    if (\PHP_SAPI === 'cli') {
        i('cli')->e($format."\n", ...$args);

        return;
    }
    if (!is_admin()) {
        return;
    }
    $text = $args ? sprintf($format, $args) : $format;
    $text = preg_replace('!\{[\w\/]+\}!', ' ', $text);
    echo "\n<div class=admin>{$text}</div>\n";
}

/**
 * COLORED STDERR sprintf for CLI mode
 * i(CLI) wrapper.
 *
 * @see \hb\e(..), i('cli')
 * Ex:
 *  \hb\err("{red}{bold}Error Condition: $error{/}")    << as is, no sprintf
 *  \hb\err("{red}{bold}Error Condition: %s{/}", $a)    << use sprintf
 *
 * @param mixed $format
 */
function err($format, ...$args) {
    // STDERR
    // @todo("implement stylish array presenation");
    if (\is_array($format)) {
        $format = x2s($format);
    }
    if (\PHP_SAPI === 'cli') {
        i('cli')->err($format."\n", ...$args);

        return;
    }
    if (!is_admin()) {
        return;
    }
    $text = $args ? sprintf($format, $args) : $format;
    $text = preg_replace('!\{[\w\/]+\}!', ' ', $text);
    echo "\n<div class=admin style='background: #f00; color: #fff'>{$text}</div>\n";
}

// HTML Escape
// if $text is array - join it
function h($text) {
    // escaped text
    return htmlspecialchars(\is_array($text) ? implode('', $text) : $text, ENT_QUOTES, 'utf-8', false);
}

/**
 * Dump debug data for Admins
 * This messages are hidden unless debug option specified:
 * CLI:
 *   --debug=$level    >> Debug messages in STDOUT
 *   --debug is the same as --debug=1
 * WEB:
 *   ?DEBUG=$level     >> Debug messages in Profiler
 *   ?DEBUG is the same as ?DEBUG=1.
 *
 * Level 1 is considered most important
 * messages level=$level and below are shown
 *
 * Example:
 *  $level=1  show ONLY level=1 messages
 *  $level=3  show level=1,2,3 messages
 *
 * @param mixed $data
 */
function debug(/* mixed */ $data, int $level = 1) {
    if (!is_admin()) {
        return;
    }
    if (!(HB::$Q['DEBUG'] ?? 0)) {
        return;
    }
    $d = HB::$Q['DEBUG'] ?: 1;
    if ($level > $d) {
        return; // debug message is too low
    }
    if (\is_string($data)) {
        $data = x2s($data);
    }
    if (\PHP_SAPI === 'cli') {
        e("{grey}%s{/}\n", $data);
    } else {
        iprofiler()->info(Str::afterLast(caller(), '/'), [$data], ['tag' => 'debug', 'skip' => 3]);   // Profiler::info($filename, $message)
    }
}

// json_encode + default params
function json($data) {
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * @param  "ClassName" | Instance
 * @param mixed $class_or_instance
 */
function instance($class_or_instance, ...$args): object {
    return \is_object($class_or_instance) ? $class_or_instance : iNew($class_or_instance, ...$args);
}

/**
 * if value is a closure - resolve it.
 *
 * @param any|Closure $value
 *
 * @return mixed
 */
function value($value) {
    // resolved Closure
    return $value instanceof \Closure ? $value() : $value;
}

/**
 * short function helper
 *   usage: $m = fn($a,$b) \hb\then($acc+=$a, $b)
 *
 * @param mixed $a
 * @param mixed $b
 */
function then($a, $b) {
    return $b;
}

/**
 * DataBase Engine.
 *
 * @see  config: dbe.$name
 */
function DB(string $name = '') // : \hb\db\DB
{
    return i('DB', $name);
}

function DS(string $name): contracts\DS {
    return i('DS', $name);
}

/**
 * remove key from hash.
 *
 * @return removed-value
 */
function hash_unset(array &$hash, string $key) {
    $vl = $hash[$key] ?? null;
    unset($hash[$key]);

    return $vl;
}

/**
 * First regex match.
 *
 * @param string $regexp
 * @param string $str
 * @param mixed  $ttl
 *
 * @return string
 */
//function fm($regexp, $str)  << wrong order
//{
//    // First Match
//    preg_match($regexp, $str, $m);
//
//    return @$m[1];
//}

/**
 * Time-to-Live calculation by different cache implementations
 * supported ttl: (int) seconds, [(int) seconds, (int) randomize-prc].
 *
 * @param mixed $ttl
 */
function ttl($ttl = [3600, 33]): int {
    // ttl .. ttl+rnd(%)
    if (\is_array($ttl)) {
        error_unless(\is_int($ttl[0]), 'ttl-part must be int');

        return $ttl[0] + rand(0, $ttl[0] * $ttl[1] / 100);
    }
    error_unless(\is_int($ttl), 'ttl must be int');

    return $ttl;
}

/**
 * Build "<a href>" tag + escaping.
 *
 * @param  $url
 * @param  $args_or_text
 * @param  $text
 * @param  $html         extra html
 *
 * @return HTML
 *              Ex: a("url", ['param' => 'value'], "text")
 *              Ex: a("url", "text")
 */
function a(string $url, $args_or_text = '', string $text = '', string $html = ''): string {
    // "<a href=.."
    if ('' === $text) {
        $text = $args_or_text;
        $args_or_text = [];
    }
    $url = url($url, $args_or_text); // args

    return "<a href=\"{$url}\"".($html ? ' '.$html : '').'>'.h($text).'</a>';
}

function url(string $url, array $args) {
    // @todo $url is [0=>url, "a-attribute" => 'value'] | ['url'=>url, "a-attribute" => 'value']
    // @todo $url is '@XXX' << use URL-aliaser
    return $args ? $url.'?'.http_build_query($args) : $url;
}

// DEPRACATED:
//   use $a ?: "default" instead
// oracle NVL - first non empty value | null
// returns first-empty value or last-argument
// nvl($a, $b, "default");
// nvl($a, $b, "0")        // return $a ? $a : ($b ? $b : "0");
function nvl(...$args) {
    // non-empty-value | last-argument
    if (\count($args) < 2) {
        throw new Exception('NVL(...) - 2+ args expected');
    }
    foreach ($args as $a) {
        if ($a) {
            return $a;
        }
        $l = $a;
    }

    return $l;
}

/**
 * is value between $from .. $to (inclusive)
 *
 * @param mixed $v
 * @param mixed $from
 * @param mixed $to
 */
function between($v, $from, $to): bool {
    return $v >= $from && $v <= $to;
}

/**
 * benchmark function
 *
 * @param callable $fn        [description]
 * @param int      $seconds   [description]
 * @param mixed    $fn_params
 */
function benchmark(callable $fn, $seconds = 3, $fn_params = []) { // [$time_per_iteration, iterations]
    $start = microtime(1);
    $end = $start + $seconds;
    $cnt = 0;
    while (microtime(1) < $end) {
        $fn($fn_params);
        ++$cnt;
    }
    $end = microtime(1);

    return ['μs' => round(($end - $start) / $cnt * 1000000, 1), 'count' => $cnt];
}

// is @method called
function isSuppressed() {
    $t = error_reporting();
    // as of php8 default suppressed reporting value is
    //   E_USER_NOTICE | E_ERROR | E_WARNING | E_PARSE |  E_CORE_ERROR | E_CORE_WARNING | E_USER_DEPRECATED
    return 0 === $t || 4437 === $t;
}

/**
 * non recoverable Error -  developer uses Code Incorrect Way
 * throw \hb\Error exception if ...
 *
 * @param mixed $boolean
 */
function error_if($boolean, string $message) {
    if ($boolean) {
        throw new \hb\Error($message);  // \Error descendant
    }
}

/**
 * non recoverable Error -  developer uses Code Incorrect Way
 * throw \hb\Error exception if ...
 *
 * @param mixed $boolean
 */
function error_unless($boolean, string $message) {
    if (!$boolean) {
        throw new \hb\Error($message);  // \Error descendant
    }
}

function error(string $message) {
    throw new \hb\Error($message);  // \Error descendant
}
