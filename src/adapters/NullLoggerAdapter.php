<?php
/**
 * Created by PhpStorm.
 * User: Henrik
 * Date: 2/19/2018
 * Time: 9:59 AM
 */

namespace henrik\log\adapters;

use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;

/**
 * Class NullLoggerAdapter
 * @package henrik\log\adapters
 */
class NullLoggerAdapter extends NullLogger
{
    use LoggerTrait;
}