<?php

namespace TMCms\Admin\Entity;

use TMCms\Orm\Entity;

/**
 * @method $this setSid(string $sid)
 * @method $this setUserId(int $user_id)
 * @method $this setTs(int $ts)
 */
class UsersSessionEntity extends Entity {
    protected function beforeCreate() {
        $this->setTs(NOW);
    }
}