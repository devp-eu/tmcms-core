<?php
declare(strict_types=1);

namespace TMCms\Container;

use TMCms\Traits\singletonInstanceTrait;

\defined('INC') or exit;

/**
 * Class Session
 * @package TMCms\Container
 */
class Session extends Base
{
    use singletonInstanceTrait;

    public function __construct()
    {
        parent::__construct($_SESSION);
    }

    /**
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setValue(string $key, $value)
    {
        $_SESSION[$key] = $value;

        return parent::setValue($key, $value);
    }
}
