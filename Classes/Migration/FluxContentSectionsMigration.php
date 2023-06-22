<?php

namespace DKM\FluxMigrate\Migration;

use DKM\FluxMigrate\Utility;
use DKM\FluxMigrate\Utility\Container\ContainerElementUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluxContentSectionsMigration extends FluxMigrationAbstract
{

    public function getResetPaths(): array
    {
        return [
            self::class => [
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['TCAOverrideFilePath']) => "<?php\n",
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['typoScriptSetupFilePath']) => "",
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['sectionsRootPath']) =>  "",
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['flexFormConfigurationPath']) => "",
            ]
        ];
    }


    protected function generateConfiguration()
    {
        // TODO: Implement generateConfiguration() method.
    }

    protected function writeConfiguration($configuration)
    {
        $CType = 'sections_' . $this->getData()['element']['id'];
        if ($flexFormConfigurationFilePath = $this->getOutputProviderSettings()['flexFormConfigurationPath'] ?? false) {
            $flexFormConfigurationFilePath .= '/' . $this->getFlexFormProvider()->getPluginName() . '.xml';
        }
        $this->createFlexFormDefinition($flexFormConfigurationFilePath, $this->getData()['sections']);

        $PHPCode = Utility::getCTypeInitializationCode(
            $CType,
            $this->getData()['element']['label'] ?? $this->getData()['element']['id'],
            $this->getData()['sections'],
            $flexFormConfigurationFilePath
        );

        file_put_contents(
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['TCAOverrideFilePath']),
            $PHPCode,
            FILE_APPEND
        );


        $typoScriptCode = Utility::getCTypeTypoScriptCode(
            $CType,
            $this->getFlexFormProvider()->getPluginName(),
            $this->getOutputProviderSettings()['sectionsRootPath']
        );

        file_put_contents(
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['typoScriptSetupFilePath']),
            $typoScriptCode,
            FILE_APPEND
        );
    }


    /**
     * @param $path
     * @param $sections
     * @return void
     */
    private function createFlexFormDefinition($path, $sections)
    {
        $xmlArray = [];
        $sheetKey = 'options';
        $xmlArray['sheets'][$sheetKey]['ROOT'] = ['TCEforms' => ['sheetTitle' => 'options'], 'type' => 'array',
            'el' => []];
            $sectionIndex = 0;
        foreach ($sections as $sectionName => $section) {
            $xmlArray['sheets'][$sheetKey]['ROOT']['el'][$sectionName] = [
                'title' => $sectionName,
                'type' => 'array',
                'section' => ++$sectionIndex,
                'el' => []
            ];
            foreach ($section as $objectName => $object) {
                $xmlArray['sheets'][$sheetKey]['ROOT']['el'][$sectionName]['el'][$objectName] = [
                    'type' => 'array',
                    'title' => $object['label'],
                    'el' => []
                ];
                foreach ($object['fields'] as $field) {
                    $xmlArray['sheets'][$sheetKey]['ROOT']['el'][$sectionName]['el'][$objectName]['el'] += Utility::getFlexFormFieldData($field);
                }
                $xmlArray['sheets'][$sheetKey]['ROOT']['el'][$sectionName]['el'][$objectName]['el'] = array_filter($xmlArray['sheets'][$sheetKey]['ROOT']['el'][$sectionName]['el'][$objectName]['el']);
            }
        }
        $xml = GeneralUtility::array2xml($xmlArray, '', 0, 'T3DataStructure');
        file_put_contents(GeneralUtility::getFileAbsFileName($path), '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . "\n" . $xml);
    }


    protected function getConfigurationPath()
    {
        // TODO: Implement getConfigurationPath() method.
    }

    protected function generateFluidTemplateContent($configuration)
    {
        // TODO: Implement generateFluidTemplateContent() method.
    }

    protected function getFluidTemplatePath()
    {
        // TODO: Implement getFluidTemplatePath() method.
    }

    protected function writeFluidTemplate($templateContent)
    {
        // TODO: Implement writeFluidTemplate() method.
    }

    public function migrateData($configuration)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['CType' => 'sections_' . $this->getData()['element']['id']],  ['CType' => $this->getFlexFormProvider()->getContentObjectType()]);
    }
}