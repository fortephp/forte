<?php

declare(strict_types=1);

namespace Forte\Facades;

use Forte\Ast\Document\Document;
use Forte\Components\ComponentManager;
use Forte\Enclaves\Enclave;
use Forte\Enclaves\EnclavesManager;
use Forte\Parser\Directives\Directives;
use Forte\Parser\ParserOptions;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * @method static Enclave defaultEnclave()
 */
class Forte extends Facade
{
    protected static function getFacadeAccessor()
    {
        return EnclavesManager::class;
    }

    /**
     * Returns the default application Enclave.
     */
    public static function app(): Enclave
    {
        return static::defaultEnclave();
    }

    /**
     * Parse a template string and return a Document.
     *
     * @param  string  $template  The template source to parse
     * @param  ParserOptions|null  $options  Parser configuration
     */
    public static function parse(string $template, ?ParserOptions $options = null): Document
    {
        return Document::parse($template, $options);
    }

    /**
     * Parse a template from a file path and return a Document.
     *
     * @param  string  $path  File path to load
     * @param  ParserOptions|null  $options  Parser configuration
     *
     * @throws RuntimeException
     */
    public static function parseFile(string $path, ?ParserOptions $options = null): Document
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Template file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Could not read template file: {$path}");
        }

        $doc = Document::parse($content, $options);
        $doc->setFilePath($path);

        return $doc;
    }

    /**
     * Get a directives registry with defaults loaded.
     */
    public static function directives(): Directives
    {
        return Directives::withDefaults();
    }

    /**
     * Get a new component manager instance.
     */
    public static function components(): ComponentManager
    {
        return new ComponentManager;
    }
}
