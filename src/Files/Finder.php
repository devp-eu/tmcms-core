<?php
declare(strict_types=1);

namespace TMCms\Files;

use RuntimeException;
use TMCms\Config\Constants;
use TMCms\Config\Settings;
use TMCms\Orm\Entity;
use TMCms\Traits\singletonInstanceTrait;

\defined('INC') or exit;

/**
 * Class Finder used to register custom folder for file loading
 * @package TMCms\Files
 */
class Finder {
    use singletonInstanceTrait;

    const TYPE_ASSETS = 'assets';
    const TYPE_API = 'api';
    const TYPE_PLUGINS = 'plugins';
    const TYPE_SERVICES = 'services';
    const TYPE_TRANSLATIONS = 'translations';

    private $assets_search_folders = [\DIR_CMS_SCRIPTS_URL];
    private $api_search_folders = [\DIR_FRONT_API_URL];
    private $plugin_search_folders = [\DIR_FRONT_PLUGINS_URL];
    private $services_search_folders = [\DIR_FRONT_SERVICES_URL];
    private $translations_search_folders = [];

    private $entities_with_sitemap_links = [];

    /**
     * @param string $path
     * @return $this
     */
    public function addAssetsSearchPath(string $path)
    {
        $this->assets_search_folders[] = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function addApiSearchPath(string $path)
    {
        $this->api_search_folders[] = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function addPluginsSearchPath(string $path)
    {
        $this->plugin_search_folders[] = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function addServicesSearchPath(string $path)
    {
        $this->services_search_folders[] = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function addTranslationsSearchPath(string $path)
    {
        $this->translations_search_folders[] = $path;

        return $this;
    }

    /**
     * @param Entity $entity
     * @return $this
     */
    public function addEntityWithSitemapLinks(Entity $entity)
    {
        $this->entities_with_sitemap_links[] = $entity;

        return $this;
    }

    /**
     * @return array
     */
    public function getEntitiesWithSitemapLinks(): array
    {
        return $this->entities_with_sitemap_links;
    }

    /**
     * @param string $real_file_path
     * @param string $type
     * @return string
     * @throws \RuntimeException
     */
    public function searchForRealPath(string $real_file_path, $type = self::TYPE_ASSETS): string
    {
        $search_array = $this->getPathFolders($type);
        $found_path = '';
        $external = false;

        // External path?
        if (($url = @\parse_url($real_file_path)) && isset($url['host']) && $url['host'] != CFG_DOMAIN) {
            $found_path = $real_file_path;
            $external = true;
        }

        // Straight path to local file
        if (!$found_path && \file_exists(DIR_BASE . $real_file_path)) {
            $found_path = $real_file_path;
        }

        if (!$found_path) {
            foreach ($search_array as $folder) {
                // Search folders with relative path
                if (\file_exists(\rtrim(\DIR_BASE, '/') . $folder . $real_file_path)) {
                    $found_path = \rtrim(\DIR_BASE_URL, '/') . $folder . $real_file_path;
                    break;
                }

                // Search folders with basename
                $basename = \basename($real_file_path);
                if (\file_exists(\rtrim(\DIR_BASE, '/') . $folder . $basename)) {
                    $found_path = \rtrim(\DIR_BASE_URL, '/') . $folder . $basename;
                    break;
                }
            }
        }

        // If file from external composer vendor - should copy to public dir
        if (MODE === 'site' && \stripos($found_path, '/vendor/') === 0) {
            $copy_from = \DIR_BASE . \ltrim($found_path, '/');
            $copy_to = \DIR_ASSETS . \ltrim($real_file_path, '/');
            if (\file_exists($copy_from) && (!\file_exists($copy_to) || \filemtime($copy_to) < \NOW - Constants::PERIOD_DAY_IN_SECONDS )) {
                FileSystem::mkDir(\pathinfo($copy_to, \PATHINFO_DIRNAME));
                $symlink = @\symlink($copy_from, $copy_to);
                if (!$symlink) {
                    \copy($copy_from, $copy_to);
                }
            }
            $found_path = \DIR_ASSETS_URL . \ltrim($real_file_path, '/');
        }

        // Add cache stamp for frontend assets
        if (!$external && $type == self::TYPE_ASSETS && $found_path) {
            $found_path .= '?' . Settings::get('last_assets_invalidate_time');
        }

        if (!$found_path) {
            throw new RuntimeException('File "'. $real_file_path .'" with type "'. $type .'" not found, looked in '. \implode(' , ', $search_array));
        }

        return $found_path;
    }

    /**
     * @param string $type - choose from Finder constants
     * @return array
     */
    public function getPathFolders(string $type): array
    {
        $search_array = [];
        switch ($type) {
            case self::TYPE_ASSETS:
                $search_array = $this->assets_search_folders;
                break;
            case self::TYPE_API:
                $search_array = $this->api_search_folders;
                break;
            case self::TYPE_PLUGINS:
                $search_array = $this->plugin_search_folders;
                break;
            case self::TYPE_SERVICES:
                $search_array = $this->services_search_folders;
                break;
            case self::TYPE_TRANSLATIONS:
                $search_array = $this->translations_search_folders;
                break;
        }

        return $search_array;
    }
}
