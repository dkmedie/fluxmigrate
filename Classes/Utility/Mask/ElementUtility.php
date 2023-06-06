<?php

namespace DKM\FluxMigrate\Utility\Mask;

use DKM\FluxMigrate\Utility;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class ElementUtility
{
    public static function getElement($type, $data)
    {
        $methodName = 'getElement' . Utility::camelCase($type);
        if (method_exists(self::class, $methodName)) {
            return self::$methodName($data);
        } else {
            throw new Exception("The method $methodName does not exist on object " . self::class);
        }
    }

    /**
     * @param $data
     * @return array
     */
    public static function getElementSheet($data): array
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
    public static function getElementInput($data)
    {
        $configuration = [
            "key" => self::getKey($data),
            "label" => $data['label'],
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
    public static function getElementText($data)
    {
        if ($data['enablerichtext'] ?? false) {
            return [
                'key' => self::getKey($data),
                'label' => '',
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
                'key' => self::getKey($data),
                'label' => '',
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

    public static function getElementInlineFal($data)
    {
        return [
            'key' => self::getKey($data),
            'label' => '',
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

    /**
     * @param $data
     * @param string $prepend
     * @return string
     */
    public static function getKey($data, string $prepend = 'tx_mask_'): string
    {
        return $prepend . Utility::camelCase($data['name'], ' .-', true);
    }

}