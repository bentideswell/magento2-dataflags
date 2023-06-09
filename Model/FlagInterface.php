<?php
/**
 * @
 */
declare(strict_types=1);

namespace FishPig\DataFlags\Model;

interface FlagInterface
{
    /**
     * @const int
     */
    const FLAG_OK = 1;
    const FLAG_ERROR = 0;

    /**
     * @
     */
    public function get(object $object, string $flag, ?string $return = 'value');

    /**
     * @
     */
    public function set(object $object, string $flag, int $value = self::FLAG_OK, $msg = null): void;
    
    /**
     *
     */
    public function delete(object $object, string $flag): void;
}