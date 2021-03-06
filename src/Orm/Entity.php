<?php
declare(strict_types=1);

namespace TMCms\Orm;

use TMCms\Cache\Cacher;
use TMCms\Config\Configuration;
use TMCms\Config\Constants;
use TMCms\Config\Settings;
use TMCms\DB\SQL;
use TMCms\Routing\Structure;
use TMCms\Strings\Converter;
use TMCms\Strings\SimpleCrypto;
use TMCms\Strings\Translations;

/**
 * Class Entity
 * @package TMCms\Orm
 *
 * @method int getPid()
 */
class Entity extends AbstractEntity
{
    private static $_cache_key_prefix = 'orm_entity_'; // Should be overwritten in extended class
    private static $encryption_key; // Should be overwritten in extended class
    protected $encrypted_fields = [];
    protected $changed_fields_for_update = [];
    protected $update_on_duplicate = false;
    private $data = [];
    private $translation_data = [];
    private $insert_low_priority = false;
    private $insert_delayed = false;
    private $encode_special_chars_for_html = false; // Auto use of htmlspecialchars for output
    private $field_callbacks = []; // Key used to encrypt and decrypt db data
    private $loaded_from_db = false;

    /**
     * @var SQL
     */
    protected $dao;

    /**
     * Entity constructor.
     *
     * @param int $id
     * @param bool $load_from_db
     */
    public function __construct($id = 0, $load_from_db = true)
    {
        $this->dao = SQL::getInstance();
        $this->data['id'] = NULL;

        if ($id) {
            $this->setId($id, $load_from_db);
        }
    }

    /**
     * @param int $id
     * @param bool $load_from_db
     * @return $this
     */
    public function setId($id, $load_from_db = true)
    {
        $this->data['id'] = $id;

        if ($load_from_db) {
            $this->loadDataFromDB();
        }

        return $this;
    }

    public function loadDataFromDB($id = 0)
    {
        if ($id) {
            $this->setId($id, false); // Prevent recursion
        }

        $data = NULL;

        // Try loading object from cache
        if (Settings::isCacheEnabled()) {
            $data = $this->getObjectDataFromCache();

            // We have to have more than only ID field
            if (!empty($data) && count($data) === 1) {
                $data = NULL;
            }
        }

        // Do we need to update translations
        $all_multi_lng_fields_in_cache = true;
        foreach ($this->translation_fields as $v) {
            if (!isset($data['translation_data'], $data['translation_data'][$v]) || !is_array($data['translation_data'][$v])) {
                $all_multi_lng_fields_in_cache = false;
                break;
            }
        }

        $need_to_cache = false;
        // Get data from DB
        if ($data === NULL || !$all_multi_lng_fields_in_cache) {
            // Load data from database
            $data = q_assoc_row($this->getSelectSql());
            $this->loaded_from_db = true;

            // Later we have to cache data
            $need_to_cache = true;
        }

        // Load entity data
        if ($data) {
            $this->loadDataFromArray($data, true);
        }

        // Save to cache
        if ($need_to_cache) {
            $this->saveObjectDataToCache();
        }

        return $this;
    }

    private function getObjectDataFromCache()
    {
        if (!Settings::isCacheEnabled()) {
            return NULL;
        }

        return Cacher::getInstance()->getDefaultCacher()->get($this->getCacheKey());
    }

    /**
     * @return string
     */
    private function getCacheKey(): string
    {
        return self::$_cache_key_prefix . str_replace('\\', '_', get_class($this)) . '_' . $this->getId();
    }

    /**
     * @return int|NULL
     */
    public function getId(): ?int
    {
        return $this->data['id'] ? (int)$this->data['id'] : NULL;
    }

    /**
     * @return string
     */
    public function getSelectSql(): string
    {
        return 'SELECT * FROM `' . $this->getDbTableName() . '` WHERE `id` = "' . $this->getId() . '"';
    }

    /**
     * @param array $data
     * @param bool $skip_changed_fields
     * @return $this
     */
    public function loadDataFromArray(array $data, $skip_changed_fields = false)
    {
        // Set usual properties
        foreach ($data as $k => $v) {
            $this->setField($k, $v, $skip_changed_fields);
        }

        // Load Multi lng data
        foreach ($this->translation_fields as $v) {
            if (isset($data['translation_data'], $data['translation_data'][$v]) && is_array($data['translation_data'][$v])) {
                $this->translation_data[$v] = $data['translation_data'][$v];
            } elseif (isset($data[$v]) && is_array($data[$v])) {
                $this->translation_data[$v] = $data[$v];
            } elseif (isset($this->data[$v])) {
                $this->translation_data[$v] = Translations::get($this->data[$v]);
            }
        }

        if ($this->encrypted_fields) {
            $this->decryptValues();
        }

        $this->afterLoad();

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @param bool $skip_changed_fields
     * @return $this
     */
    public function setField($key, $value, $skip_changed_fields = false)
    {
        // Optimize updates - only required fields for updating
        if (!$skip_changed_fields) {
            $this->changed_fields_for_update[$key] = true;
        }

        // check null for newly created fields
        if (in_array($key, $this->translation_fields, true) && (is_array($value) || (!ctype_digit((string)$value) && NULL !== $value))) {
            // Saving Translation ID
            $this->translation_data[$key] = $value;
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    protected function decryptValues()
    {
        $key = self::getEncryptionCheckSumKey();

        foreach ($this->encrypted_fields as $field_name) {
            if (isset($this->data[$field_name])) {
                if (is_string($this->data[$field_name]) && self::isFieldEncrypted($this->data[$field_name])) {
                    $this->data[$field_name] = SimpleCrypto::decrypt($this->data[$field_name], $key);
                }
            }

            if (isset($this->translation_data[$field_name], $this->translation_data[$field_name][LNG])) {
                if (is_string($this->translation_data[$field_name][LNG]) && self::isFieldEncrypted($this->translation_data[$field_name][LNG])) {
                    $this->translation_data[$field_name][LNG] = SimpleCrypto::decrypt($this->translation_data[$field_name][LNG], $key);
                }
            }
        }
    }

    public static function getEncryptionCheckSumKey(): int
    {
        if (self::$encryption_key) {
            $config = Configuration::getInstance();
            self::$encryption_key = crc32(
            // All sensitive data
                $config->get('cms')['unique_key']
                . Constants::ADMIN_CMS_NAME
                . Constants::ADMIN_CMS_OWNER_COMPANY
                . CMS_SUPPORT_EMAIL
                . CMS_SITE
            );
        }

        return self::$encryption_key;
    }

    public static function isFieldEncrypted($text): bool
    {
        $key = SimpleCrypto::PREFIX;

        return strpos($text, SimpleCrypto::PREFIX) === strlen($key);
    }

    /**
     * Auto-call before after object is loaded with data
     */
    protected function afterLoad()
    {

    }

    /**
     * Set data of current object in cache
     */
    private function saveObjectDataToCache()
    {
        Cacher::getInstance()->getDefaultCacher()->set($this->getCacheKey(), $this->getAsArray(), 3600);

        return $this;
    }

    /**
     * @param bool $load_translations
     * @return array
     */
    public function getAsArray($load_translations = false): array
    {
        $res = $this->data;

        // Multi lng data in separate field
        foreach ($this->translation_fields as $v) {
            $tmp = [];
            if (isset($this->translation_data[$v])) {
                $tmp = $this->translation_data[$v];
            }
            $res['translation_data'][$v] = $tmp;
        }

        if ($load_translations && isset($res['translation_data']) && $res['translation_data'] && is_array($res['translation_data'])) {
            $res['translation_data'] = (array)$res['translation_data'];
            foreach ($res['translation_data'] as $translation_field => $translation_field_data) {
                $res[$translation_field] = $translation_field_data[LNG] ?? NULL;
            }
            unset($res['translation_data']);
        }

        return $res;
    }

    public static function getInstance($id = 0, $load_from_db = true)
    {
        return new static($id, $load_from_db);
    }

    /**
     * Change bool value to opposite
     * @param $field
     * @return $this
     */
    public function flipBoolValue($field)
    {
        $this->setField($field, (int)!$this->getField($field));

        return $this;
    }

    /**
     * @param string $field
     * @param string $lng if field is multilingual
     *
     * @return mixed
     */
    public function getField($field, $lng = false)
    {
        $res = NULL;

        if($lng === false && defined ('LNG'))
            $lng = LNG;

        if (isset($this->data[$field]) || isset($this->translation_data[$field])) {
            if (in_array($field, $this->translation_fields, true)) {
                if (isset($this->translation_data[$field])) {
                    if (is_array($this->translation_data[$field]) && array_key_exists($lng, $this->translation_data[$field])) {
                        return $this->translation_data[$field][$lng];
                    }

                    return $this->translation_data[$field];

                }

                if (isset($this->data[$field])) {
                    return Translations::get($this->data[$field], $lng);
                }
            }

            if (isset($this->data[$field])) {
                $res = $this->data[$field];
            }

            foreach ($this->field_callbacks as $callback) {
                $res = $callback($field, $res);
            }

            if ($this->encode_special_chars_for_html && is_scalar($res)) {
                $res = htmlspecialchars($res);
            }

            return $res;
        }

        return $res;
    }

    /**
     * Removed field from object
     * @param string $field_from
     * @param string $field_to
     * @param bool $remove_renamed_field set false if need to keep both field
     * @return $this
     */
    public function renameField($field_from, $field_to, $remove_renamed_field = true)
    {
        $this->setField($field_to, $this->getField($field_from));
        if ($remove_renamed_field) {
            $this->unsetField($field_from);
        }

        return $this;
    }

    /**
     * Removed field from object
     * @param string|array $fields
     * @return $this
     */
    public function unsetField($fields)
    {
        $fields = (array)$fields;
        foreach ($fields as $field) {
            unset($this->data[$field]);
        }

        return $this;
    }

    /**
     * This method sometimes is required for language selector
     * @param $lng
     * @return string
     */
    public function getSlugUrl($lng = LNG): string
    {
        throw new \RuntimeException('getSlugUrl is undefined for '.get_class($this));
    }

    public function deleteObject()
    {
        $this->beforeDelete();

        $this->dao->delete($this->getDbTableName(), $this->getId());

        $this->deleteObjectDataFromCache();

        $this->afterDelete();

        return $this;
    }

    /**
     * Auto-call before object is Deleted
     */
    protected function beforeDelete()
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function deleteObjectDataFromCache()
    {
        Cacher::getInstance()->getDefaultCacher()->delete($this->getCacheKey());

        return $this;
    }

    /**
     * Auto-call after object is Deleted
     */
    protected function afterDelete()
    {
        return $this;
    }

    public function save()
    {
        $this->deleteObjectDataFromCache();

        $this->beforeSave();

        $this->validateEntityDataFields();

        if ($this->encrypted_fields) {
            $this->encryptValues();
        }

        if ($this->getId()) {
            $this->update();
        } else {
            $this->create();
        }

        $this->afterSave();

        return $this;
    }

    /**
     * Auto-call before any Create or Update
     */
    protected function beforeSave()
    {
        return $this;
    }

    protected function encryptValues()
    {
        $key = self::getEncryptionCheckSumKey();

        foreach ($this->encrypted_fields as $field_name) {
            if (isset($this->data[$field_name])) {
                if (is_string($this->data[$field_name]) && !self::isFieldEncrypted($this->data[$field_name])) {
                    $this->data[$field_name] = SimpleCrypto::encrypt($this->data[$field_name], $key);
                }
            }

            if (isset($this->translation_data[$field_name], $this->translation_data[$field_name][LNG])) {
                if (is_string($this->translation_data[$field_name][LNG]) && !self::isFieldEncrypted($this->translation_data[$field_name][LNG])) {
                    $this->translation_data[$field_name][LNG] = SimpleCrypto::encrypt($this->translation_data[$field_name][LNG], $key);
                }
            }
        }
    }

    private function update()
    {
        // Start transaction - we have inner-translation updates
        $this->dao->startTransaction();

        $this->beforeUpdate();

        $data = [];

        // Get updated fields
        $fields = $this->dao->getFields($this->getDbTableName());

        // Generate data to be updated
        foreach ($fields as $v) {
            // Update only changed fields
            if (isset($this->changed_fields_for_update[$v])) {

                // Translation field
                if (in_array($v, $this->translation_fields, true) && isset($this->translation_data[$v], $this->data[$v]) && is_array($this->translation_data[$v])) {
                    $data[$v] = Translations::update($this->translation_data[$v], $this->data[$v]);
                } else {
                    // Usual field
                    if ($this->getField($v) !== NULL) {
                        $data[$v] = $this->getField($v);
                    }
                }
            }
        }

        // Update entry in DB
        $this->dao->update($this->getDbTableName(), $data, $this->getId(), 'id', $this->insert_low_priority);

        // Load all fresh data from DB
        $this->loadDataFromDB();

        $this->afterUpdate();

        // Close all update queries
        $this->dao->confirmTransaction();

        return $this;
    }

    /**
     * Auto-call before any Update
     */
    protected function beforeUpdate()
    {
        return $this;
    }

    /**
     * Auto-call after any Update
     */
    protected function afterUpdate()
    {
        return $this;
    }

    /**
     * @return $this
     */
    private function create()
    {
        // Start transaction - we have inner-translation updates
        $this->dao->startTransaction();

        $this->beforeCreate();

        $data = [];

        // Get all fields in DB table
        $fields = $this->dao->getFields($this->getDbTableName());

        // Clear ID if created from another data
        unset($this->data['id']);

        // Set data values for every available field
        foreach ($fields as $v) {
            // Translation
            if (in_array($v, $this->translation_fields, true) && isset($this->translation_data[$v])) {
                // If provided text only - need to save new and set array
                if (!is_array($this->translation_data[$v])) {
                    $this->translation_data[$v] = [
                        LNG => $this->translation_data[$v],
                    ];
                }

                // Save new Translation
                if (isset($this->translation_data[$v]['id'])) {
                    unset($this->translation_data[$v]['id']);
                }

                $data[$v] = Translations::save($this->translation_data[$v]);

                $this->setField($v, $data[$v]);
            } else {
                // Usual field
                if ($this->getField($v) !== NULL) {
                    $data[$v] = $this->getField($v);
                }
            }
        }


        // Create entry in database
        $this->data['id'] = $this->dao->add($this->getDbTableName(), $data, true, $this->update_on_duplicate, $this->insert_low_priority, $this->insert_delayed);

        $this->afterCreate();

        // Close all update queries
        $this->dao->confirmTransaction();

        return $this;
    }

    /**
     * Auto-call before any Create
     */
    protected function beforeCreate()
    {
        return $this;
    }

    /**
     * Auto-call after any Create
     */
    protected function afterCreate()
    {
        return $this;
    }

    /**
     * Auto-call after any Create or Update
     */
    protected function afterSave()
    {
        return $this;
    }

    /**
     * Auto-call before any save, validates all fields are in correct format and have correct data
     * Inherit the function with any validation you need
     * And do not forget to call parent::validateEntityDataFields() at the end
     */
    protected function validateEntityDataFields()
    {
        // Check only change fields and make sure they are in correct format for database fields
        $changed_fields = $this->getChangedDataFields();
        // Get repository
        $repository_class = $this->getRepositoryClassName();
        /** @var EntityRepository $repo_object */
        $repo_object = new $repository_class;

        foreach ($changed_fields as $changed_field) {
            // Get field value
            $field_value = $this->getField($changed_field);
            // Validate the field is in correct format
            $is_valid_value = $repo_object->validateFieldDataIsInCorrectFormat($changed_field, $field_value);

            if (!$is_valid_value) {
                // TODO uncomment when all setter will typecast values to corresponding database field type
//                throw new \UnexpectedValueException('Value for field "' . $changed_field . '" with type "' . \gettype($field_value) . '" is not correct, check validation functions in Repository "' . $repository_class . '" with ID "' . $this->getId() . '" and value "' . \json_encode($field_value) . '"');
            }
        }

        return $this;
    }

    public function isFieldChangedForUpdate($field): bool
    {
        return isset($this->changed_fields_for_update[$field]);
    }

    public function getChangedDataFields(): array
    {
        return array_keys($this->changed_fields_for_update);
    }

    /**
     * Method for catching setField + getField
     *
     * @param $name
     * @param $args
     *
     * @return string
     * @throws \Exception
     */
    public function __call($name, $args)
    {
        $prefix = substr($name, 0, 3);

        if ($prefix === 'get' || $prefix === 'set') {
            $method_to_call = $prefix . 'Field';
            $param = substr($name, 3); // Cut "set" or "get"
            $param = Converter::fromCamelCase($param);
            $param = strtolower($param);

            return $this->{$method_to_call}(strtolower($param), ...$args);
        }

        throw new \RuntimeException('Method "' . $name . '" unknown');
    }

    public function enableUpdateOnDuplicate()
    {
        $this->update_on_duplicate = true;

        return $this;
    }

    public function enableSpecialCharEncodingForHtml()
    {
        $this->encode_special_chars_for_html = true;

        return $this;
    }

    public function addCustomCallbackForFields(callable $callback)
    {
        $this->field_callbacks[] = $callback;

        return $this;
    }

    public function enableSavingWithLowPriority()
    {
        if ($this->insert_delayed) {
            throw new \RuntimeException('Can not use Low Priority when Delayed is enabled');
        }

        $this->insert_low_priority = true;
    }

    public function enableSavingDelayedWithNoReturn()
    {
        if ($this->insert_low_priority) {
            throw new \RuntimeException('Can not use Delayed when Low Priority is enabled');
        }

        $this->insert_delayed = true;
    }

    public function setDbTableName($db_table)
    {
        $this->db_table = $db_table;

        return $this;
    }

    /**
     * If you need to add translation field on the fly, e.g. when merging repositories
     * @param string $field_name
     * @return $this
     */
    public function addTranslationFieldForAutoSelects($field_name)
    {
        $this->translation_fields[] = $field_name;

        return $this;
    }

    public function getTranslationFields(): array
    {
        return $this->translation_fields;
    }

    public function addFieldForDecryption($field_name)
    {
        $this->encrypted_fields[] = $field_name;

        $key = self::getEncryptionCheckSumKey();

        // Decrypt field
        if (isset($this->data[$field_name])) {
            if (is_string($this->data[$field_name]) && self::isFieldEncrypted($this->data[$field_name])) {
                $this->data[$field_name] = SimpleCrypto::decrypt($this->data[$field_name], $key);
            }
        }

        if (isset($this->translation_data[$field_name], $this->translation_data[$field_name][LNG])) {
            if (is_string($this->translation_data[$field_name][LNG]) && self::isFieldEncrypted($this->translation_data[$field_name][LNG])) {
                $this->translation_data[$field_name][LNG] = SimpleCrypto::decrypt($this->translation_data[$field_name][LNG], $key);
            }
        }

        return $this;
    }

    /**
     * If possible to find existing entry by all this fields than update but not save new
     * Make sure to first load data before calling that method
     *
     * @param array $check_fields that must be unique
     * @return $this
     */
    public function findAndLoadPossibleDuplicateEntityByFields(array $check_fields)
    {
        $class_name = $this->getRepositoryClassName();
        /** @var EntityRepository $repo */
        $repo = new $class_name;

        $params = [];
        foreach ($check_fields as $field) {
            $method = 'get' . ucfirst($field);
            $params[$field] = $this->$method();
        }

        $existing_entry = $repo::findOneEntityByCriteria($params);

        if ($existing_entry) {
            $this->setId($existing_entry->getId(), false);
        }

        return $this;
    }

    public function __clone()
    {
        $this->setId(0, false);
    }

    /**
     * Must be implemented in extended classes. Links will be displayed in Widget Pages
     *
     * @param string $lng
     *
     * @return string
     */
    public function getLinkForSitemap($lng = LNG): string
    {
        // This is an example, please overwrite it in own Entity
        return Structure::getPathByLabel('XXX', $lng);
    }

    /**
     * Action to call fron _item_order functions
     * @param string $parent_column_name
     */
    public function processOrderAction($parent_column_name = '') {
        if ($parent_column_name) {
            // If have pid field, means we should move in the same parent
            SQL::orderCat($this->getId(), $this->getDbTableName(), $this->getPid(), $parent_column_name, $_GET['direct']);
        } else {
            // Or usual move
            SQL::order($this->getId(), $this->getDbTableName(), $_GET['direct']);
        }

        // Ajax request only with steps
        if (IS_AJAX_REQUEST) {
            SQL::orderMoveByStep($this->getId(), $this->getDbTableName(), $_GET['direct'], $_GET['step']);
            die(1);
        }

        back();
    }

    protected function getRepositoryClassName() {
        return \get_class($this) . self::CLASS_RELATION_NAME_REPOSITORY;
    }
}
