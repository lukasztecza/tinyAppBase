<?php declare(strict_types=1);
namespace LightApp\Model\System;

class Response
{
    protected const DEFAULT_RULE = 'sanitize';

    protected const SANITIZE = 'sanitize';
    protected const HTML_ESCAPE = 'html';
    protected const URL_ESCAPE = 'url';
    protected const FILE_ESCAPE = 'file';
    protected const NO_ESCAPE = 'raw';

    protected const ALLOWED_HTML_TAGS = ['h3', 'p', 'i', 'b', 'table', 'tr', 'th', 'td', 'ul', 'ol', 'li'];

    private $file;
    private $variables;
    private $rules;
    private $headers;
    private $cookies;

    public function __construct(string $file = null, array $variables = [], array $rules = [], array $headers = [], array $cookies = [])
    {
        $this->file = $file;
        $this->variables = $variables;
        $this->rules = $rules;
        $this->headers = $headers;
        $this->cookies = $cookies;
    }

    public function getFile() : string
    {
        $filename = $this->file;
        $this->fileEscapeValue($filename);
        return $filename;
    }

    public function getVariables() : array
    {
        $variables = $this->variables;
        $counter = 0;
        $this->escapeArray($counter, $variables, '');

        return $variables;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function getCookies() : array
    {
        $cookies = $this->cookies;
        foreach ($cookies as $key => $cookie) {
            if (empty($cookie['name']) || empty($cookie['value'])) {
                throw new \Exception('Cookie name and value is required ' . var_export($cookie, true));
            }

            $cookies[$key]['expire'] = $cookie['expire'] ?? 0;
            $cookies[$key]['path'] = $cookie['path'] ?? '/';
            $cookies[$key]['domain'] = $cookie['domain'] ?? '';
            $cookies[$key]['secure']  = $cookie['secure'] ?? false;
            $cookies[$key]['httponly'] = $cookie['httponly'] ?? true;
        }

        return $cookies;
    }

    private function escapeArray(int &$counter, array &$array, string $keyString) : void
    {
        foreach ($array as $key => $value) {
            $this->handleCounter($counter);

            $key = (string) $key;
            $originalKey = $key;
            $this->sanitizeValue($key);
            if ($key !== $originalKey) {
                unset($array[$originalKey]);
                if (!empty($key)) {
                    $array[$key] = $value;
                    trigger_error('Sanitized improper key ' . $originalKey . ' into ' . $key . ' from ' . var_export($array, true) , E_USER_NOTICE);
                    continue;
                }
                trigger_error('Dropped improper key ' . $originalKey . ' from ' . var_export($array, true), E_USER_NOTICE);
                continue;
            }

            $currentKeyString = empty($keyString) ? $key : $keyString . '.' . $key;
            if (is_string($value) || is_numeric($value)) {
                $array[$key] = (string) $value;
                $this->selectAndApplyRuleForValue($array[$key], $currentKeyString);
            }
            if (is_array($value)) {
                $this->escapeArray($counter, $array[$key], $currentKeyString);
            }
        }
    }

    private function handleCounter(int &$counter) : void
    {
        $counter++;
        if (10000 < $counter) {
            throw new \Exception('Too big or deep array or danger of infinite recurrence, reached counter ' . var_export($counter, true));
        }
    }

    private function selectAndApplyRuleForValue(string &$value, string $currentKeyString) : void
    {
        $selectedRule = static::DEFAULT_RULE;
        foreach ($this->rules as $ruleKeyString => $rule) {
            if (strpos($currentKeyString, $ruleKeyString) === 0) {
                $selectedRule = $rule;
                unset($rule);unset($ruleKeyString);
                break;
            }
        }

        switch($selectedRule) {
            case static::SANITIZE:
                $this->sanitizeValue($value);
                break;
            case static::HTML_ESCAPE:
                $this->htmlEscapeValue($value);
                break;
            case static::URL_ESCAPE:
                $this->urlEscapeValue($value);
                break;
            case static::FILE_ESCAPE:
                $this->fileEscapeValue($value);
                break;
            case static::NO_ESCAPE:
                break;
            default:
                throw new \Exception('Not supported escape/sanitize rule ' . $selectedRule);
        }
    }

    private function sanitizeValue(string &$value) : void
    {
        $value = preg_replace('/[^a-zA-Z0-9]/', '', $value);
    }

    private function htmlEscapeValue(string &$value) : void
    {
        $value = htmlspecialchars($value, ENT_QUOTES);

        $patterns = $replacements = [];
        foreach (static::ALLOWED_HTML_TAGS as $tag) {
            $patterns[] = '/&lt;' . $tag . '&gt;/';
            $patterns[] = '/&lt;\/' . $tag . '&gt;/';
            $replacements[] = '<' . $tag . '>';
            $replacements[] = '</' . $tag . '>';
        }
        $value = preg_replace($patterns, $replacements, $value);
    }

    private function urlEscapeValue(string &$value) : void
    {
        $value = rawurlencode($value);
    }

    private function fileEscapeValue(string &$value) : void
    {
        preg_match('/(\/{0,1}[a-zA-Z0-9]{1,}){1,}(\.{1})([a-z]{3,4}){1}/', $value, $matches);
        if (isset($matches[0])) {
            $value = $matches[0];
        } else {
            $value = '';
        }
    }
}
