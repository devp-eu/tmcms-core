<?php
declare(strict_types=1);

namespace TMCms\HTML\Cms\Element;

use TMCms\HTML\Element\InputPassword;

\defined('INC') or exit;

/**
 * Class CmsInputPassword
 * @package TMCms\HTML\Cms\Element
 */
class CmsInputPassword extends InputPassword {
    private $reveal = false;

    /**
     * @param string $name
     * @param string $value
     * @param string $id
     */
    public function  __construct(string $name, string $value = '', string $id = '') {
        parent::__construct($name, $value, $id);

        $this->addCssClass('form-control');
    }

    /**
     * @param string $name
     * @param string $value
     * @param string $id
     *
     * @return $this
     */
    public static function getInstance(string $name, string $value = '', string $id = '') {
        return new self($name, $value, $id);
    }

    /**
     * Show password onfocus
     *
     * @param bool $flag
     *
     * @return $this
     */
    public function setReveal(bool $flag) {
        $this->reveal = $flag;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString() {
        return '<input'. $this->getCommonElementValidationAttributes() . ($this->isFieldRequired() ? ' required' : '') . $this->getCommonElementValidationAttributes() . ($this->reveal ? ' onfocus="this.type=\'text\'" onblur="this.type=\'password\'"': null) .' data-date-format="yyyy-mm-dd" type="password" '. $this->getAttributesString() .'>';
    }
}
