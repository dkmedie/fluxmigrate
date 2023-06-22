<?php

namespace DKM\FluxMigrate\Utility\Mask;

use DKM\FluxMigrate\Utility;
use MASK\Mask\ConfigurationLoader\ConfigurationLoader;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class ElementUtility implements SingletonInterface
{
    protected ConfigurationLoader $configurationLoader;

    protected array $createdKeys = [];
    protected array $usedKeys = [];

    const fieldPrefix = 'tx_mask_';

    /**
     * @param ConfigurationLoader $configurationLoader
     */
    public function __construct(ConfigurationLoader $configurationLoader)
    {
        $this->configurationLoader = $configurationLoader;
    }

    /**
     * @param $CTypeName
     * @param $type
     * @param $data
     * @return array
     * @throws Exception
     */
    public function getElementField($CTypeName, $type, $data): array
    {
        $methodName = 'getElementField' . Utility::camelCase(str_replace('fluidtypo3flux', '', $type));
        if (method_exists(self::class, $methodName)) {
            $configuration = self::$methodName($data);
            return array_merge([
                'key' => $this->getKey($CTypeName, $data['name'], $configuration['name']),
                'originalKey' => $data['name'],
                'label' => $data['label'] ?? (ucfirst($data['name']))
            ], $configuration);
        } else {
            throw new Exception("The method $methodName does not exist on object " . self::class);
        }
    }

    /**
     * @param string $CTypeName
     * @param string $key
     * @param string $type
     * @param bool $increaseIndex
     * @return string
     */
    private function getKey(string $CTypeName, string $key, string $type, bool $increaseIndex = false): string
    {
        $getSQLField = function($key) {
            return self::fieldPrefix . Utility::camelCase($key, ' .-', true);
        };

        if($increaseIndex) {
            $key = is_numeric(substr($key, -1, 1)) ? ++$key : $key . '1';
        }

        // Field with $key already created
        if(isset($this->createdKeys[$key])) {
            // Field already created with the type of $type
            if(isset($this->createdKeys[$key][$type])) {
                // Field with key is already used on this CType
                // Then increase index on key
                if(isset($this->usedKeys[$CTypeName][$key])) {
                    return $this->getKey($CTypeName, $key, $type, true);
                    // Field with key is not used on this CType
                } else {
                    $this->usedKeys[$CTypeName][$key] = 1;
                    return $getSQLField($key);
                }
                // Field with this key already created, but not of same type
            } else {
                return $this->getKey($CTypeName, $key, $type, true);
            }
            // Field not yet created
        } else {
            $this->createdKeys[$key][$type] = 1;
            $this->usedKeys[$CTypeName][$key] = 1;
            return $getSQLField($key);
        }
    }

    /**
     * @param $data
     * @return array
     */
    public function getElementSheet($data): array
    {
        return [
            "key" => StringUtility::getUniqueId("tx_mask_{$data['sheetKey']}"),
            "label" => $data['label'],
            "description" => "",
            "name" => "tab",
            "tca" => [],
            "fields" => []
        ];
    }

    /**
     * @param $data
     * @return array
     */
    private function getElementFieldInput($data)
    {
        $configuration = [
            "description" => "",
            "fields" => [],
            "tca" => ['l10n_mode' => '',
                'config.eval.null' => 0],
            'sql' => "varchar(255) DEFAULT '' NOT NULL"
        ];
        if ($data['config'] ?? false) {
            $config = Yaml::parse($data['config']);
        }
        switch ($config['renderType'] ?? 'default') {
            case 'inputLink':
                $configuration['name'] = 'link';
                if ($config ?? false) {
                    $configuration['tca'] += ArrayUtility::flattenPlain(['config' => array_intersect_key($config, ['fieldControl' => 1])]);
                }
                break;
            default:
                $configuration['name'] = 'string';
                $configuration['tca'] += ["config.valuePicker.items" => []];

                break;
        }
//        $configuration['tca'] = (object)$configuration['tca'];
        return $configuration;


    }

    /**
     * @param $data
     * @return array
     */
    private function getElementFieldText($data)
    {
        if ($data['enablerichtext'] ?? false) {
            return [
                'description' => '',
                'name' => 'richtext',
                'tca' =>
                    [
                        'l10n_mode' => '',
                        'config.richtextConfiguration' => '',
                    ],
                'fields' =>
                    [],
                'sql' => 'mediumtext'
            ];
        } else {
            return [
                'description' => '',
                'name' => 'text',
                'tca' =>
                    [
                        'l10n_mode' => '',
                        'config.wrap' => 'virtual',
                        'config.format' => '',
                        'config.valuePicker.mode' => '',
                        'config.valuePicker.items' =>
                            [],
                        'config.eval.null' => 0,
                    ],
                'fields' =>
                    [],
                'sql' => 'mediumtext'
            ];
        }
    }

    private function getElementFieldInlineFal($data)
    {
        return [
            'description' => '',
            'name' => 'media',
            'tca' =>
                [
                    'l10n_mode' => '',
                    'onlineMedia' =>
                        [],
                    'config.appearance.elementBrowserEnabled' => 1,
                    'config.appearance.fileUploadAllowed' => 1,
                    'config.appearance.fileByUrlAllowed' => 1,
                    'config.appearance.collapseAll' => '',
                    'config.appearance.useSortable' => 1,
                    'config.appearance.enabledControls.info' => 1,
                    'config.appearance.enabledControls.dragdrop' => 1,
                    'config.appearance.enabledControls.sort' => 0,
                    'config.appearance.enabledControls.hide' => 1,
                    'config.appearance.enabledControls.delete' => 1,
                    'config.appearance.enabledControls.localize' => 1,
                    'allowedFileExtensions' => 'jpg,jpeg,png',
                ],
            'fields' =>
                [],
        ];
    }

    private function getElementFieldRelation($data)
    {
        $configuration = [
            'description' => '',
            'name' => 'group',
            'tca' =>
                 [
                    'l10n_mode' => '',
                    'config.internal_type' => 'db',
                    'config.allowed' => 'tt_content',
                    'config.fieldControl.editPopup.disabled' => 1,
                    'config.fieldControl.addRecord.disabled' => 1,
                    'config.fieldControl.listModule.disabled' => 1,
                    'config.minitems' => $data['minItems'] ?? 1,
                    'config.maxitems' => $data['maxItems'] ?? 10,
                    'config.multiple' => $data['multiple'] ?? 0,
                    'config.size' => $data['config.size'] ?? 1
                ],
            'fields' =>
                array (
                ),
        ];
        if($data['condition'] ?? false) {
            $configuration['tca']['config.suggestOptions.default.addWhere'] = strip_tags($data['condition']);
        }
        return $configuration;
    }
}