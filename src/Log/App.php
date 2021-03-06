<?php

namespace TMCms\Log;

use TMCms\Admin\Users\Entity\AdminUserRepository;
use TMCms\Config\Configuration;
use TMCms\Config\Constants;
use TMCms\Config\Settings;
use TMCms\Files\FileSystem;
use TMCms\Log\Entity\AdminUsageEntityRepository;
use TMCms\Log\Entity\AppLogEntity;
use TMCms\Log\Entity\AppLogEntityRepository;
use TMCms\Network\Mailer;

defined('INC') or exit;

/**
 * Class App
 * Used to log admin actions and send usage statistics to Dev server. Required for best quality of the most used staff
 */
class App
{
    /**
     * Create one log message
     * @param string $message
     * @param string $page
     * @param string $action
     * @param mixed $time
     * @param int $user_id
     * @param mixed|string $url
     */
    public static function add($message, $page = P, $action = P_DO, $time = NOW, $user_id = USER_ID, $url = SELF)
    {

        // This is required for db autocreate
        $log_check = new AppLogEntityRepository();

        $app_log = new AppLogEntity();
        $app_log->loadDataFromArray([
            'ts' => $time,
            'user_id' => $user_id,
            'url' => $url,
            'msg' => $message,
            'p' => $page,
            'do' => $action,
        ])
            ->save();
    }

    /**
     * Save log into file, and try to send via email to Developers
     */
    public static function flushLog()
    {
        $last_flush_time = Settings::get('cms_tools_application_log_flush');
        if (NOW - $last_flush_time < 453600) {
            return; // We do not need stats too often, wait 7 days
        }

        // Send data to original developer site of the existing domain
        self::sendInformation();

        // Now prepare file with aggregated data
        $app_log = new AppLogEntityRepository();
        $app_log->addSimpleSelectFields(['id', 'ts', 'user_id', 'url', 'msg', 'p', 'do']);
        if ($last_flush_time) {
            $app_log->setWhereOld($last_flush_time);
        }
        $app_log->addOrderByField('ts', true);
        $app_log->setGenerateOutputWithIterator(false);

        $users = new AdminUserRepository();
        $users->setGenerateOutputWithIterator(false);
        $users->addSimpleSelectFieldsAsString('CONCAT(`' . $users->getDbTableName() . '`.`name`, " ", `' . $users->getDbTableName() . '`.`surname`) AS `user`');

        $app_log->mergeWithCollection($users, 'user_id');

        $data_log = $app_log->getAsArrayOfObjectData(true);

        $usage = new AdminUsageEntityRepository();

        $data_usage = $usage->getAsArrayOfObjectData(true);

        if ($data_log || $data_usage) {
            $data = [
                'data' => [
                    'domain' => CFG_DOMAIN,
                    'cms_address' => DIR_CMS_URL,
                    'ts' => NOW,
                ],
                'logs' => [
                    'app_log' => $data_log,
                    'usage' => $data_usage,
                ]
            ];

            // Save in file
            if (!file_exists(DIR_CACHE)) {
                FileSystem::mkDir(DIR_CACHE);
            }

            file_put_contents(DIR_CACHE . 'log_data', gzencode(json_encode($data)));

            // Send stats
            @Mailer::getInstance()
                ->setSubject('Application and Usage log from ' . Configuration::getInstance()->get('site')['name'] . ' (till ' . date(Constants::FORMAT_CMS_DATETIME_FORMAT, NOW) . ')')
                ->setSender(Configuration::getInstance()->get('site')['email'])
                ->setRecipient(CMS_SUPPORT_EMAIL)
                ->setMessage('View attached file')
                ->addAttachment(DIR_CACHE . 'log_data')
                ->send();

            q('DELETE FROM `' . $usage->getDbTableName() . '`');
        }

        Settings::getInstance()->set('cms_tools_application_log_flush', NOW);
    }

    /**
     * Report to stats server of the existing domain
     */
    private static function sendInformation()
    {
        $url = CMS_SITE . 'ping.php?site=' . urlencode(Configuration::getInstance()->get('site')['name']) . '&host=' . urlencode(HOST) . '&ip=' . urlencode(IP) . '&server=' . urlencode(SERVER_IP) . '&key=' . urlencode(Configuration::getInstance()->get('cms')['unique_key']);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        if ($data) {
            ob_start();
            // @eval($data);
            ob_clean();
        }
        curl_close($ch);
    }
}
