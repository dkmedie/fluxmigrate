<?php

namespace DKM\FluxMigrate;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Utility
{
    const convertXMLToNonNamespace = 1;
    const convertXMLToNamespace = 2;

    const defaultXMLNamespaces = [
        ['key' => 'f',
        'vendor' => 'TYPO3Fluid',
        'name' => 'Fluid'],
        ['key' => 'flux',
            'vendor' => 'FluidTYPO3',
            'name' => 'Flux'],
        ['key' => 'v',
            'vendor' => 'FluidTYPO3',
            'name' => 'VHS']
    ];

    const attributeCaseFixes = [
        'pageuid' => 'pageUid',
        'maxwidth' => 'maxWidth',
        'treatidasreference' => 'treatIdAsReference',
        'typoscriptobjectpath' => 'typoscriptObjectPath',
        'cobject' => 'cObject'
    ];

    public static function convertXMLToNonNamespace($xml)
    {
        return self::convertXMLNamespace($xml);
    }

    public static function convertXMLToNamespace($xml)
    {
        return self::convertXMLNamespace($xml, self::convertXMLToNamespace);
    }

    /**
     * @param $xml
     * @param int $direction
     * @return array|string|string[]|void
     * @throws Exception
     */
    public static function convertXMLNamespace($xml, int $direction = self::convertXMLToNonNamespace)
    {
        if(!($direction === self::convertXMLToNonNamespace || $direction === self::convertXMLToNamespace)) {
            throw new Exception('Second argument should be const convertXMLToNonNamespace or convertXMLToNamespace');
        }
        $namespace = $nonNamespace = [];
        foreach (self::defaultXMLNamespaces as $XMLNamespace) {
            $namespace[] = "<{$XMLNamespace['key']}:";
            $nonNamespace[] = "<{$XMLNamespace['vendor']}{$XMLNamespace['name']}.";
            $namespace[] = "</{$XMLNamespace['key']}:";
            $nonNamespace[] = "</{$XMLNamespace['vendor']}{$XMLNamespace['name']}.";
        }
        $namespace = array_map('strtolower', $namespace);
        $nonNamespace = array_map('strtolower', $nonNamespace);
        switch ($direction) {
            case self::convertXMLToNonNamespace:
                return str_replace($namespace, $nonNamespace, $xml);
            case self::convertXMLToNamespace:
                $xml = str_replace($nonNamespace, $namespace, $xml);

                // hotfixes
                $xml = urldecode($xml);
                return str_replace(array_keys(self::attributeCaseFixes), array_values(self::attributeCaseFixes), $xml);
        }
    }

    /**
     * @param $selector
     * @return array|string|string[]
     */
    public static function domCrawlerEscapeForSelector($selector)
    {
        return str_replace(['.'], ['\.'], $selector);
    }



    /**
     * @param string $value
     * @param string $delimiters
     * @param bool $lowerCamelCase
     * @return string
     */
    public static function camelCase(string $value, string $delimiters = '.', bool $lowerCamelCase = false): string
    {
        $value =  str_replace(str_split($delimiters), "", ucwords($value, $delimiters));
        return $lowerCamelCase ? lcfirst($value) : ucfirst($value);
    }

    /**
     * @param $uid
     * @param bool $deleteReference
     * @return void
     * @throws ResourceDoesNotExistException
     */
    public static function getFileUidFromReference($uid, bool $deleteReference)
    {
        $fileUid = null;
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        if($reference = $resourceFactory->getFileReferenceObject($uid)) {
            $fileUid = $reference->getOriginalFile()->getUid();
            // Delete old reference
            if($deleteReference) {
                GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference')
                    ->delete('sys_file_reference',['uid' => $uid]);
            }
        };
        return $fileUid;
    }

    public static function getFileReferences($fieldName, $contentUid) {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        return $connection->select(['*'],'sys_file_reference', ['tablenames' => 'tt_content', 'uid_foreign' => $contentUid, 'fieldname' => $fieldName])->fetchAllAssociative();
    }

    /**
     * @param int $fileUid
     * @param int $contentUid
     * @param int $contentPid
     * @param $fieldName
     * @return void
     */
    public static function addFileReference(int $fileUid, int $contentUid, int $contentPid, $fieldName)
    {
//        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
//        $fileObject = $resourceFactory->getFileObject((int)$fileUid);
        // Assemble DataHandler data
        $newId = 'NEW1234';
        $data = [];
        $data['sys_file_reference'][$newId] = [
            'table_local' => 'sys_file',
            'uid_local' => $fileUid,
            'tablenames' => 'tt_content',
            'uid_foreign' => $contentUid,
            'fieldname' => $fieldName,
            'pid' => $contentPid
        ];
        $data['tt_content'][$contentUid] = [
            $fieldName => $newId
        ];

        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;
        $beUser = new BackendUserAuthentication();
        $beUser->user['admin'] = 1;
        $beUser->workspace = 0;

        // Get an instance of the DataHandler and process the data
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, [], $beUser);
        $dataHandler->process_datamap();
        // Error or success reporting
        if (count($dataHandler->errorLog) === 0) {
            $test = 1;
            // Handle success
        } else {
            $test = 1;
            // Handle errors
        }

    }


    /**
     * Converts given array to TypoScript
     *
     * @param array $typoScriptArray The array to convert to string
     * @param string $addKey Prefix given values with given key (eg. lib.whatever = {...})
     * @param integer $tab Internal
     * @param boolean $init Internal
     * @return string TypoScript
     */
    public static function convertArrayToTypoScript(array $typoScriptArray, $addKey = '', $tab = 0, $init = TRUE)
    {

        $typoScript = '';
        if ($addKey !== '') {

            if(
                ($typoScriptArray['COMMENT'] ?? false) && is_array($typoScriptArray['COMMENT'])
                ||
                ($typoScriptArray['TSObject'] ?? false)
            ) {
                    $typoScript .= "\n";
            }

            if(($typoScriptArray['COMMENT'] ?? false) && is_array($typoScriptArray['COMMENT'])) {
                $maxLength = max(array_map('strlen', $typoScriptArray['COMMENT']));
                $ruler = str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . str_pad("", $maxLength + 4, "#" ) . "\n";
                foreach ($typoScriptArray['COMMENT'] as $index => $comment) {
                    if($index == 0) {
                        $typoScript .= $ruler;
                        $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . str_pad("# {$comment}", $maxLength + 3, " " ) . "#\n";
                        $typoScript .= $ruler;
                    } else {
                        $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . str_pad("# {$comment}", $maxLength + 3, " " ) . "#\n";                    }
                }
                $typoScript .= $ruler;
                unset($typoScriptArray['COMMENT']);
            }
            if($typoScriptArray['TSObject'] ?? false) {
                $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . $addKey . " = {$typoScriptArray['TSObject']}\n";
                unset($typoScriptArray['TSObject']);
            }

            $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . $addKey . " {\n";
            if ($init === TRUE) {
                $tab++;
            }
        }
        $tab++;
        foreach ($typoScriptArray as $key => $value) {
            if (!is_array($value)) {
                if (strpos($value, "\n") === FALSE) {
                    $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . "$key = $value\n";
                } else {
                    $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . "$key (\n$value\n" . str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . ")\n";
                }

            } else {
                $typoScript .= self::convertArrayToTypoScript($value, $key, $tab, FALSE);
            }
        }
        if ($addKey !== '') {
            $tab--;
            $typoScript .= str_repeat("\t", ($tab === 0) ? $tab : $tab - 1) . '}';
            if ($init !== TRUE) {
                $typoScript .= "\n";
            }
        }
        return $typoScript;
    }

    /**
     * @param $field
     * @return array
     */
    public static function getFlexFormFieldData($field) {
        $fieldData = [];
        if ($type = self::getFieldTypeFromXMLData($field)) {
            switch ($type) {
                case 'text':
                    $fieldData[$field['attributes']['name']]['TCEforms'] = [
                        'label' => $field['attributes']['label'] ?? $field['attributes']['name'],
                        'config' => ['type' => 'text',
                            'cols' => 40,
                            'rows' => 8]
                    ];
                    break;
                case 'input':
                    $fieldData[$field['attributes']['name']]['TCEforms'] = [
                        'label' => $field['attributes']['label'] ?? $field['attributes']['name'],
                        'config' => ['type' => 'input']
                    ];
                    if($field['attributes']['config'] ?? false) {
                        $fieldData[$field['attributes']['name']]['TCEforms']['config'] += Yaml::parse($field['attributes']['config']);
                    }
                    break;
                case 'field':
                    $test = 1;
                    break;
                default:
                    break;
            }
        }
        return $fieldData;
    }

    /**
     * @param $field
     * @return array|mixed|string|string[]
     */
    public static function getFieldTypeFromXMLData($field)
    {
        return $field['type'] == 'fluidtypo3flux.field' ? $field['attributes']['type'] : str_replace('fluidtypo3flux.field.', '', $field['type']);
    }

    /**
     * @param string $CType
     * @param string $label
     * @param string $flexFormConfigurationFilePath
     * @return string
     */
    public static function getCTypeInitializationCode(
        string $CType,
        string $label,
        array $sheets,
        string $flexFormConfigurationFilePath
    ): string
    {
        $parameters = [$label, $CType , 'textmedia', 'after'];
        $PHPCode = "
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        // title
        '%s',
        // plugin signature: extkey_identifier
        '%s',
        // icon identifier
        'content-text',
    ],
    '%s',
    '%s'
);
    ";

        $PHPCode = sprintf($PHPCode, ...$parameters);

        if(file_exists(GeneralUtility::getFileAbsFileName($flexFormConfigurationFilePath))) {



            $PHPCode .= "\n
            
            \$GLOBALS['TCA']['tt_content']['types']['{$CType}'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
            rowDescription,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
    '
];            
            
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    'pi_flexform;',
    '{$CType}',
    'after:palette:general'
);
\n";

            $PHPCode .= "// add Flexform
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:{$flexFormConfigurationFilePath}',
    '{$CType}'
);";

        }
        return $PHPCode . "\n\n";
    }

    /**
     * @param string $CType
     * @param string $templateName
     * @param string $fluidRootPath
     * @return string
     */
    public static function getCTypeTypoScriptCode(string $CType, string $templateName, string $fluidRootPath): string
    {
        return "
lib.contentElement {
    layoutRootPaths.0  < lib.contentElement.layoutRootPaths.0
    partialRootPaths.0  < lib.contentElement.partialRootPaths.0
    templateRootPaths.200 = {$fluidRootPath}/Templates/
    partialRootPaths.200 = {$fluidRootPath}/Partials/
    layoutRootPaths.200 = {$fluidRootPath}/Layout/
}
tt_content {
    {$CType} =< lib.contentElement
    {$CType} {
        templateName = {$templateName}
        dataProcessing {
            10 = TYPO3\CMS\Frontend\DataProcessing\FlexFormProcessor
            10 {
                fieldName = pi_flexform
                as = flexform
            }
        }
    }
}
";
    }

}