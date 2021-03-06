<?php

namespace App\URLParser;


use App\Service\ImportService;

class URLParser
{
    /** @var array|URLParserBase[]  */
    protected static $parser = [];

    /** @var null  */
    protected static $baseParser = null;

    public static function readParser(ImportService $importService)
    {
        if (empty(self::$parser)) {
            foreach (scandir(__DIR__) as $file)
            {
                $filename = realpath(__DIR__.'/'.$file);
                if (is_file($filename)) {
                    require_once($filename);

                    $className = basename($file, '.php');

                    // get the file name of the current file without the extension
                    // which is essentially the class name
                    $class = __NAMESPACE__.'\\'.$className;
                    if (class_exists($class))
                    {
                        if (method_exists($class, 'canHandleUrl')) {
                            if ($className == 'URLParserBase') {
                                continue;
                            }
                            if ($className == 'URLParserAdvanced') {
                                self::$baseParser = new $class($importService);
                            } else {
                                self::$parser[] = new $class($importService);
                            }
                        }
                    }
                }
            }
        }
    }

    public static function getParser($url, ImportService $importService) : ?URLParserBase
    {
        self::readParser($importService);
        foreach (self::$parser as $parser) {
            if ($parser->canHandleUrl($url)) {
                return $parser;
            }
        }

        return self::$baseParser;
    }
}
