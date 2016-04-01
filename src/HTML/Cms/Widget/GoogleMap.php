<?php

namespace TMCms\HTML\Cms\Widget;

use TMCms\HTML\Element;
use TMCms\HTML\Widget;

defined('INC') or exit;

/**
 * Class GoogleMap
 */
class GoogleMap extends Widget {
    /**
     * @param Element $owner
     */
    public function __construct(Element $owner = null) {
        parent::__construct($owner);
    }

    public static function getInstance(Element $owner = null) {
        return new self($owner);
    }

    public function __toString() {
        ob_start();
        ?><input data-dialog-modal-url="?p=components&do=google_map&nomenu&cache=<?= NOW ?>" type="button" value="Google Map" data-result-id="<?= $this->owner->id() ?>" class="btn btn-info"><?php
        return ob_get_clean();
    }
}