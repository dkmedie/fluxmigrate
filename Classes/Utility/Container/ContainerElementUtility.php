<?php

namespace DKM\FluxMigrate\Utility\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContainerElementUtility
{

    /**
     * @param string $CType
     * @param string $label
     * @param string $description
     * @param array $gridArray
     * @param string $flexFormConfigurationFilePath
     * @param array $sheets
     * @param string|null $iconPath
     * @return string
     */
    public static function getContainerElementInitializationCode(
        string $CType,
        string $label,
        string $description,
        array $gridArray,
        string $flexFormConfigurationFilePath,
        array $sheets,
        string $iconPath = null
    ): string
    {
        $gridArrayAsString = var_export($gridArray, 1);
        $parameters = [$CType, $label, $description, $gridArrayAsString];
        if($iconPath) $parameters[] = $iconPath;

        $PHPCode = "
    new \B13\Container\Tca\ContainerConfiguration(
        '%s', // CType
        '%s', // label
        '%s', // description
        %s // grid configuration
    )
    )
    ";
        if($iconPath) {
            $PHPCode .= "
        // set an optional icon configuration
        ->setIcon('%s')
";
        }

        $PHPCode = "
\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\B13\Container\Tca\Registry::class)->configureContainer(
    (" . sprintf($PHPCode, ...$parameters) . ");";

        if(file_exists(GeneralUtility::getFileAbsFileName($flexFormConfigurationFilePath))) {

            $itemCfg = 'pi_flexform;';
            foreach ($sheets as $sheet) {
                foreach ($sheet['fields'] as $field) {
                    if($field['attributes']['name'] ?? false) {
                        $itemCfg .= "{$field['attributes']['name']},";
                    }
                }
            }
            $PHPCode .= "\n
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '{$itemCfg}',
    '{$CType}',
    'after:header'
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
     * @param array $columns
     * @return string
     */
    public static function getContainerElementTypoScriptCode(string $CType, string $templateName, string $fluidRootPath, array $columns): string
    {
        $dataProcessingCode = [];

        $dataProcessingCodeTemplate =
"        %s = B13\Container\DataProcessing\ContainerProcessor
        %s {
            colPos = %s
            as = children_%s
        }";
        foreach ($columns as $colPos) {
            $dataProcessingCode[] = str_replace('%s', $colPos, $dataProcessingCodeTemplate);
        }


        $parameters = [$CType, $CType, $templateName, $fluidRootPath, $fluidRootPath, implode("\n", $dataProcessingCode)];
        $typoScriptCode = "
tt_content.%s < lib.contentElement
tt_content.%s {
    templateName = %s
    layoutRootPaths {
        10 = %s/Layouts
    }
    templateRootPaths {
        10 = %s/Templates
    }
    dataProcessing {
%s
    }
}";
        return sprintf($typoScriptCode, ...$parameters);
    }
}