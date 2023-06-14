<?php

namespace DKM\FluxMigrate\Migration;

use DKM\FluxMigrate\Utility;
use DKM\FluxMigrate\Utility\Container\ContainerElementUtility;
use Symfony\Component\DomCrawler\Crawler;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class FluxContentColumnsToContainerMigration extends FluxContentElementMigrationAbstract
{

    /**
     * @param bool $doNotResetFiles
     * @return bool
     */
    public function resetFiles(bool $doNotResetFiles = false): bool
    {
        if (!$doNotResetFiles) {
            file_put_contents(
                \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['TCAOverrideFilePath']),
                "<?php\n"
            );
            file_put_contents(
                \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['typoScriptSetupFilePath']),
                ""
            );
        }
        return true;
    }

    protected function generateConfiguration()
    {
        $configuration = [];
        $maxColumns = 0;


        $this->getFlexFormProvider()->getContentObjectType();

        // CType
        $configuration['element']['id'] = $this->getData()['element']['id'];
        // Label
        $configuration['element']['title'] = $this->getData()['element']['title'];
        // ?
        $configuration['element']['optionGroup'] = $this->getData()['element']['optionGroup'];

        //TODO OBS OBS OBS DISSE MANUELT???
        //TODO Titel på element: EXT:skeleton/Resources/Private/Language/da.locallang.xlf
        //TODO Titel på kolonne: EXT:skeleton/Resources/Private/Language/da.locallang.xlf


        foreach ($this->getData()['grid']['rows'] ?? [] as $keyRow => $rowData) {
            if (count($rowData['columns']) > $maxColumns) {
                $maxColumns = count($rowData['columns']);
            }
            foreach ($rowData['columns'] ?? [] as $keyColumn => $columnData) {
                $configuration['columns'][$columnData['name']] = $configuration['grid'][$keyRow][$keyColumn]['colPos'] = ($columnData['colPos'] ?? 0) + 200;
                $configuration['grid'][$keyRow][$keyColumn]['name'] = $columnData['name'];
                $configuration['grid'][$keyRow][$keyColumn]['label'] = $columnData['label'];
            }
        }
        // add colspan
        foreach ($configuration['grid'] as &$row) {
            $currentColumnCount = count($row);
            if ($currentColumnCount != $maxColumns) {
                foreach ($row as $i => &$column) {
                    if ($maxColumns == ($i + 1)) {
                        $column['colspan'] = $maxColumns - ($i);
                    } else {
                        $column['colspan'] = 1;
                    }
                }
            }
        }

// TODO Get field data
        foreach ($this->getData()['sheets']['options'] ?? [] as $fieldKey => $field) {

        }


        return $configuration;

        // TODO: Implement generateConfiguration() method.
    }

    protected function writeConfiguration($configuration)
    {
        $CType = 'container_' . $configuration['element']['id'];
        if ($flexFormConfigurationFilePath = $this->getOutputProviderSettings()['flexFormConfigurationPath'] ?? false) {
            $flexFormConfigurationFilePath .= '/' . $this->getFlexFormProvider()->getPluginName() . '.xml';
        }
        $this->createFlexFormDefinition($flexFormConfigurationFilePath, $this->getData()['sheets']);

        $PHPCode = ContainerElementUtility::getContainerElementInitializationCode(
            $CType,
            $configuration['element']['title'] ?? $configuration['element']['id'],
            $configuration['element']['description'] ?? '',
            $configuration['grid'],
            $flexFormConfigurationFilePath,
            $this->getData()['sheets'] ?? [],
            $configuration['element']['iconPath'] ??
            ($this->getOutputProviderSettings()['defaultContainerElementIcon'] ?? null)

        );

        file_put_contents(
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['TCAOverrideFilePath']),
            $PHPCode,
            FILE_APPEND
        );


        $typoScriptCode = ContainerElementUtility::getContainerElementTypoScriptCode(
            $CType,
            $this->getFlexFormProvider()->getPluginName(),
            $this->getOutputProviderSettings()['fluidRootPath'],
            $configuration['columns']
        );

        file_put_contents(
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['typoScriptSetupFilePath']),
            $typoScriptCode,
            FILE_APPEND
        );

        $test = 1;
        // TODO: Implement writeConfiguration() method.
    }

    private function createFlexFormDefinition($path, $sheets)
    {
        $xmlArray = [];
        foreach ($sheets as $sheetKey => $sheetData) {
            $xmlArray['sheets'][$sheetKey]['ROOT'] = ['type' => 'array',
                'el' => []];
            $sheetRoot = &$xmlArray['sheets'][$sheetKey]['ROOT'];
            foreach ($sheetData['fields'] as $field) {
                if ($type = array_slice(explode('.', $field['type'], 3), 2)[0] ?? '') {
                    switch ($type) {
                        case 'text':
                            $sheetRoot['el'][$field['attributes']['name']]['TCEforms'] = [
                                'label' => $field['attributes']['label'] ?? $field['attributes']['name'],
                                'config' => ['type' => 'text',
                                    'cols' => 40,
                                    'rows' => 8]
                            ];
                            break;
                        case 'input':
                            $sheetRoot['el'][$field['attributes']['name']]['TCEforms'] = [
                                'label' => $field['attributes']['label'] ?? $field['attributes']['name'],
                                'config' => ['type' => 'input']
                            ];
                            break;
                        default:
                            break;
                    }
                }

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
        $templateContent = $this->getData()['templateContent'];
        // Template content
        foreach ($configuration['columns'] as $name => $colPos) {

            $templateContent = preg_replace('/(.*flux:content.render.*area="' . $name . '".*)/',
                '<f:for each="{children_' . $colPos . '}" as="record">
    <f:format.raw>
        {record.renderedContent}
    </f:format.raw>
</f:for>', $templateContent);
        }

        return $templateContent;
    }

    protected function getFluidTemplatePath()
    {
        // TODO: Implement getFluidTemplatePath() method.
    }

    protected function writeFluidTemplate($templateContent)
    {
        file_put_contents(
            GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['templatePath'] . "/" . $this->getFlexFormProvider()->getPluginName()) . '.html',
            $templateContent
        );
    }

    /**
     * @param $configuration
     * @return void
     */
    public function migrateContentElements($configuration)
    {
        $CType = $this->getFlexFormProvider()->getContentObjectType();
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');

        $keyMap = array_column($configuration['fields'] ?? [], null, 'originalKey');

        foreach ($connection->select(['*'], 'tt_content', ['CType' => $CType])->fetchAllAssociative() ?? [] as $element) {
            //Find elements releated to this grid element
            $inContainerElements = $qb->from('tt_content')->select('*')->where(
                $qb->expr()->gte('colPos', $qb->createNamedParameter("{$element['uid']}00")),
                $qb->expr()->lte('colPos', $qb->createNamedParameter("{$element['uid']}99"))
            )->execute()->fetchAllAssociative();
            foreach ($inContainerElements as $inContainerElement) {
                list($tx_container_parent, $colPos) = str_split($inContainerElement['colPos'], 4);
                $colPos = (int)$colPos + 200;
                $connection->update('tt_content', ['tx_container_parent' => $tx_container_parent, 'colPos' => $colPos], ['uid' => $inContainerElement['uid']]);
            }
            $update = ['CType' => 'container_' . $configuration['element']['id']];
            $connection->update('tt_content', $update, ['uid' => $element['uid']]);
        }
    }
}