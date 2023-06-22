<?php

namespace DKM\FluxMigrate\Migration;

use DKM\FluxMigrate\Utility\Container\ContainerElementUtility;
use DKM\FluxMigrate\Utility\Mask\ElementUtility;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluxContentColumnsToContainerMigration extends FluxMigrationAbstract
{

    /**
     * @return array[]
     */
    public function getResetPaths(): array
    {
        return [
            self::class => [
                \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['TCAOverrideFilePath']) => "<?php\n",
                \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['typoScriptSetupFilePath']) => "",
                \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['containerRootPath']) =>  "",
                \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['flexFormConfigurationPath']) => "",
            ]
        ];
    }



    /**
     * @return array
     */
    protected function generateConfiguration(): array
    {
        $configuration = [];
        $maxColumns = 0;

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
//        $configuration['fields'] = [];
//        foreach ($this->getData()['sheets'] ?? [] as $sheetKey =>  $sheetData) {
//            foreach ($sheetData['fields'] ?? [] as $field) {
//                $type = $field['type'] == 'fluidtypo3flux.field' ? $field['attributes']['type'] : str_replace('fluidtypo3flux.field.', '', $field['type']);
//                $configuration['fields'][] = $this->elementUtility->getElementField($this->getFlexFormProvider()->getContentObjectType(), $type, $field['attributes']);
//            }
//        }


        return $configuration;

        // TODO: Implement generateConfiguration() method.
    }

    /**
     * @param $configuration
     * @return void
     */
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
            $this->getOutputProviderSettings()['containerRootPath'],
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

    /**
     * @param $path
     * @param $sheets
     * @return void
     */
    private function createFlexFormDefinition($path, $sheets)
    {
        $xmlArray = [];
        foreach ($sheets as $sheetKey => $sheetData) {
            $xmlArray['sheets'][$sheetKey]['ROOT'] = ['type' => 'array',
                'el' => []];
            $sheetRoot = &$xmlArray['sheets'][$sheetKey]['ROOT']['el'];
            foreach ($sheetData['fields'] as $field) {
                if ($type = array_slice(explode('.', $field['type'], 3), 2)[0] ?? '') {
                    switch ($type) {
                        case 'text':
                            $sheetRoot[$field['attributes']['name']]['TCEforms'] = [
                                'label' => $field['attributes']['label'] ?? $field['attributes']['name'],
                                'config' => ['type' => 'text',
                                    'cols' => 40,
                                    'rows' => 8]
                            ];
                            break;
                        case 'input':
                            $sheetRoot[$field['attributes']['name']]['TCEforms'] = [
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

    /**
     * @param $configuration
     * @return string
     */
    protected function generateFluidTemplateContent($configuration)
    {
        $templateContent = $this->getData()['templateContent'];
        // Template content
        foreach ($configuration['columns'] as $name => $colPos) {

            $templateContent = preg_replace('/(<flux:content.render.*area="' . $name . '".*/>)/',
                '<f:for each="{children_' . $colPos . '}" as="record">
    <f:format.raw>
        {record.renderedContent}
    </f:format.raw>
</f:for>', $templateContent);
        }

        return (string)$templateContent;
    }

    protected function getFluidTemplatePath()
    {
        // TODO: Implement getFluidTemplatePath() method.
    }

    protected function writeFluidTemplate($templateContent)
    {
        file_put_contents(
            GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['containerRootPath'] . '/Templates/' . $this->getFlexFormProvider()->getPluginName()) . '.html',
            $templateContent
        );
    }

    /**
     * @param $configuration
     * @return void
     * @throws DBALException
     * @throws Exception
     */
    public function migrateData($configuration)
    {
        $CType = $this->getFlexFormProvider()->getContentObjectType();
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');

        $keyMap = array_column($configuration['fields'] ?? [], null, 'originalKey');

        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        foreach ($qb->from('tt_content')->select('*')->where($qb->expr()->eq('CType', $qb->createNamedParameter($CType)))->execute()->fetchAllAssociative() ?? [] as $element) {
            //Find elements releated to this grid element
            $inContainerElements = $qb->from('tt_content')->select('*')->where(
                $qb->expr()->gte('colPos', $qb->createNamedParameter("{$element['uid']}00")),
                $qb->expr()->lte('colPos', $qb->createNamedParameter("{$element['uid']}99"))
            )->execute()->fetchAllAssociative();
            foreach ($inContainerElements as $inContainerElement) {
                list($tx_container_parent, $colPos) = str_split($inContainerElement['colPos'], strlen($inContainerElement['colPos']) - 2);
                $colPos = (int)$colPos + 200;
                $connection->update('tt_content', ['tx_container_parent' => $tx_container_parent, 'colPos' => $colPos], ['uid' => $inContainerElement['uid']]);
            }
            $connection->update('tt_content', ['CType' => 'container_' . $configuration['element']['id']], ['uid' => $element['uid']]);
        }
    }
}