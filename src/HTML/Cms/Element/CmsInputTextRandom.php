<?php
declare(strict_types=1);

namespace TMCms\HTML\Cms\Element;

use TMCms\HTML\Element\InputText;

\defined('INC') or exit;

/**
 * Class CmsInputTextRandom
 * @package TMCms\HTML\Cms\Element
 */
class CmsInputTextRandom extends InputText {
    /**
     * @param string $name
     * @param string $value
     * @param string $id
     */
    public function  __construct(string $name, string $value = '', string $id = '') {
        parent::__construct($name, $value, $id);

        $this->addCssClass('form-control');
        $this->setRandomGenerator();
    }

    /**
     * @param string $name
     * @param string $value
     * @param string $id
     *
     * @return $this
     */
    public static function getInstance(string $name, string $value = '', string $id = '')
    {
        return new self($name, $value, $id);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $helper = $this->getHelperbox();

        return $this->js_function_for_random . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td width="100%"><input ' . $this->getCommonElementValidationAttributes() . $this->getAttributesString() . '></td><td valign="top"><input type="button" class="btn btn-info btn-outline" value="' . __('Random') . '" onclick="document.getElementById(\'' . $this->getId() . '\').value=random_for_input()"></td></td></tr></table>' . $helper;
    }
}
