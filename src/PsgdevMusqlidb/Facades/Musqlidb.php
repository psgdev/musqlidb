<?php
/**
 * Created by PhpStorm.
 * User: Tibor
 * Date: 12/6/2016
 * Time: 4:36 PM
 */

namespace PsgdevMusqlidb\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Config\Repository
 */
class Musqlidb extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'musqlidb';
    }
}