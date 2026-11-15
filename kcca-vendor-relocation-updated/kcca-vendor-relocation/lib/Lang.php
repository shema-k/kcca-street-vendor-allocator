<?php
/**
 * KCCA VRSP — languages.
 *
 * The trader-facing surfaces exist in English, Luganda, Runyankole, Lusoga
 * and Acholi. The choice is a cookie as well as a session value, so it
 * survives the sign-in that follows it, and the language is applied before
 * first paint the same way the theme is.
 *
 * A missing string falls back to English rather than to a blank. A trader
 * reading a half-translated page is worse off than one reading English, and a
 * half-translated page is the normal state while a catalogue is being filled.
 *
 * Translations are catalogued per language in lang/<code>.php, returning
 * key => string. %{name} is interpolated.
 */

declare(strict_types=1);

final class Lang
{
    /** Languages offered, in the order they are offered. */
    public const LANGUAGES = [
        'en'   => 'English',
        'lg'   => 'Oluganda',
        'rn'   => 'Orunyankole',
        'so'   => 'Olusoga',
        'ach'  => 'Acholi',
    ];

    public const COOKIE = 'kcca-lang';

    private static string $code   = 'en';
    private static ?array $strings = null;
    private static ?array $english = null;

    public static function boot(): void
    {
        $wanted = $_COOKIE[self::COOKIE] ?? ($_SESSION['lang'] ?? null);
        if (is_string($wanted) && isset(self::LANGUAGES[$wanted])) {
            self::$code = $wanted;
            $_SESSION['lang'] = $wanted;
            return;
        }
        /* No choice made: follow the browser, which is what a trader with
           their phone set to Acholi expects to happen without asking. */
        $accept = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (self::LANGUAGES as $code => $label) {
            if ($code !== 'en' && stripos($accept, $code) === 0) {
                self::$code = $code;
                $_SESSION['lang'] = $code;
                return;
            }
        }
    }

    public static function code(): string
    {
        return self::$code;
    }

    public static function label(?string $code = null): string
    {
        return self::LANGUAGES[$code ?? self::$code] ?? 'English';
    }

    public static function isEnglish(): bool
    {
        return self::$code === 'en';
    }

    /** Switch, persist, and report the choice so the caller can redirect. */
    public static function set(string $code): bool
    {
        if (!isset(self::LANGUAGES[$code])) {
            return false;
        }
        self::$code   = $code;
        self::$strings = null;
        $_SESSION['lang'] = $code;
        setcookie(self::COOKIE, $code, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
        return true;
    }

    /**
     * A string in the chosen language, falling back to English.
     *
     * @param array<string,string|int|float> $vars
     */
    public static function t(string $key, array $vars = []): string
    {
        if (self::$strings === null) {
            self::$strings = self::load(self::$code);
        }
        $text = self::$strings[$key] ?? self::english()[$key] ?? $key;

        foreach ($vars as $name => $value) {
            $text = str_replace('%{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    /** True when the chosen language has its own text for this key. */
    public static function translated(string $key): bool
    {
        if (self::$code === 'en') {
            return false;
        }
        if (self::$strings === null) {
            self::$strings = self::load(self::$code);
        }
        return isset(self::$strings[$key]) && self::$strings[$key] !== self::english()[$key];
    }

    /** Keys the chosen language is still missing, for the coverage report. */
    public static function coverage(): array
    {
        $en = self::english();
        $total = count($en);
        $out = [];
        foreach (array_keys(self::LANGUAGES) as $code) {
            if ($code === 'en') {
                $out[$code] = ['done' => $total, 'total' => $total];
                continue;
            }
            $strings = self::load($code);
            $done = 0;
            foreach ($en as $k => $v) {
                if (isset($strings[$k]) && $strings[$k] !== '') {
                    $done++;
                }
            }
            $out[$code] = ['done' => $done, 'total' => $total];
        }
        return $out;
    }

    private static function load(string $code): array
    {
        $file = ROOT_PATH . '/lang/' . $code . '.php';
        if (!is_file($file)) {
            return [];
        }
        $strings = require $file;
        return is_array($strings) ? $strings : [];
    }

    private static function english(): array
    {
        if (self::$english === null) {
            self::$english = self::load('en');
        }
        return self::$english;
    }
}

/** Shorthand used inside templates. */
function t(string $key, array $vars = []): string
{
    return Lang::t($key, $vars);
}