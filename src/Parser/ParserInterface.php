<?php

namespace App\Parser;

/**
 * Description of ParserInterface.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
interface ParserInterface
{
    public function parse(bool $incremental): void;

    public function getParsedEvents(): int;

    public function publish(array $item): void;

    public static function getParserName(): string;
}
