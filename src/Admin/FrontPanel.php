<?php

namespace TMCms\Admin;

defined('INC') or exit;

/**
 * Class FrontPanel
 * @package TMCms\Admin
 */
class FrontPanel
{
    /**
     * @return string
     */
    public static function getView()
    {
        ob_start();
        ?>
        <script>
            $.ajax({
                url: '/-/<?= CFG_API_ROUTE ?>/admin_front_panel/',
                data: {
                    page_id: '<?= PAGE_ID ?>'
                },
                success: function (data) {
                    $('body').append(data);
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
}