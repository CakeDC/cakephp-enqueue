<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Enqueue;

class JSON
{
    /**
     * @param string $string String to decode.
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function decode($string)
    {
        if (!is_string($string)) {
            throw new \InvalidArgumentException(sprintf(
                'Accept only string argument but got: "%s"',
                is_object($string) ? get_class($string) : gettype($string)
            ));
        }

        // PHP7 fix - empty string and null cause syntax error
        if (empty($string)) {
            return null;
        }

        $decoded = json_decode($string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(sprintf(
                'The malformed json given. Error %s and message %s',
                json_last_error(),
                json_last_error_msg()
            ));
        }

        return $decoded;
    }

    /**
     * @param mixed $value Value to encode.
     * @return string
     */
    public static function encode($value)
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(sprintf(
                'Could not encode value into json. Error %s and message %s',
                json_last_error(),
                json_last_error_msg()
            ));
        }

        return $encoded;
    }
}
