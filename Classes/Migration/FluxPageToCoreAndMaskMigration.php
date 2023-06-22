<?php

namespace DKM\FluxMigrate\Migration;

use DKM\FluxMigrate\Utility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluxPageToCoreAndMaskMigration extends FluxMigrationAbstract
{
    protected array $columnSetup = [];

    public function getResetPaths(): array
    {

        $pageSetupTemplate = '
<INCLUDE_TYPOSCRIPT: source="FILE:EXT:skeleton/Configuration/TypoScript/migrated.typoscript">
page = PAGE
page {
    typeNum = 0
#    shortcutIcon = {$page.favicon.file}

    bodyTagCObject = COA
    bodyTagCObject {
        10 = TEXT
        10.data = TSFE:id
        10.noTrimWrap = | id="p|"|
#        20 =< lib.page.class
#        20.stdWrap.noTrimWrap = | class="|"|
        wrap = <body|>
    }

    bodyTagAdd = class="antialiased"

    10 = FLUIDTEMPLATE
    10 {
        layoutRootPaths {
            10 = %s
        }
        partialRootPaths {
            10 = %s
        }
        templateRootPaths {
            10 = %s
        }
        templateName {
            cObject = TEXT
            cObject {
//                data = pagelayout
                data = levelfield:-1,backend_layout_next_level,slide
                override.field = backend_layout
                required = 1
                case = uppercamelcase
                split {
                token = pagets__
                cObjNum = 1
                1.current = 1
                }
            }
            ifEmpty = Default
        }
    }
}
';
        $pageSetup = sprintf($pageSetupTemplate,
                $this->getOutputProviderSettings()['layoutRootPath'],
                $this->getOutputProviderSettings()['partialRootPath'],
                $this->getOutputProviderSettings()['newTemplateRootPath'],
            ) . "\n";



        return [
            self::class => [
                GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['backendLayoutPageTSFilePath']) => '',
                GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['fluidTemplateTSFilePath']) => $pageSetup,
                GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['newTemplateRootPath']) => ''

            ]
        ];
    }

    /**
     * @return array
     */
    protected function generateConfiguration(): array
    {
        // Generate backendLayout configuration content
        $configuration = [];
        $gridRows = $this->getData()['grid']['rows'];
        $rowCount = count($gridRows);
        $colCount = 0;
        foreach ($gridRows as $rowKey => &$rowData) {
            if(count($rowData['columns']) > $colCount) {
                $colCount = count($rowData['columns']);
            }
            foreach ($rowData['columns'] as &$column) {
                if($column['label']) {
                    $column['name'] = $column['label'];
                    unset($column['label']);
                }
            }
        }

        $typoScriptArray['mod']['web_layout']['BackendLayouts'][$this->getData()['element']['id']] = [
            'title' => $this->getData()['element']['id'],
            'icon' => '',
            'config' => ['backend_layout' => [
                'colCount' => $colCount,
                'rowCount' => $rowCount,
                'rows' => $gridRows
            ]]
        ];

        $configuration['backendLayoutPageTS'] = \DKM\FluxMigrate\Utility::convertArrayToTypoScript($typoScriptArray);


        // TODO optional generate configuration for custom fields

        // TypoScript
        $test = 1;
        // page.10.file.stdWrap


        foreach ($this->getData()['grid']['rows'] as $row) {
            foreach ($row['columns'] as $column) {
                if(!isset($this->columnSetup['page'][10]['dataProcessing'][$column['colPos'] + 100])) {
                    $this->columnSetup['page'][10]['dataProcessing'][$column['colPos'] + 100] = [
                        'TSObject' => 'TYPO3\CMS\Frontend\DataProcessing\DatabaseQueryProcessor',
                        'COMMENT' => ["Template column usage:", "{$this->getData()['element']['id']}: {$column['name']}"],
                        'table' => 'tt_content',
                        'orderBy' => 'sorting',
                        'where' => "colPos = {$column['colPos']}",
                        'as' => "column{$column['colPos']}"
                    ];
                } else {
                    $this->columnSetup['page'][10]['dataProcessing'][$column['colPos'] + 100]['COMMENT'][] = "{$this->getData()['element']['id']}: {$column['name']}";
                }
            }
        }
        return $configuration;
    }

    protected function writeConfiguration($configuration)
    {
        if(!($this->getOutputProviderSettings()['backendLayoutPageTSFilePath'] ?? false)) {
            throw new Exception('backendLayoutPageTSFilePath not defined for pages output type');
        }
        if(!file_exists(GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['backendLayoutPageTSFilePath']))) {
            throw new Exception('defined path \'backendLayoutPageTSFilePath\' for pages output type does not exist');
        }
        if(!($this->getOutputProviderSettings()['fluidTemplateTSFilePath'] ?? false)) {
            throw new Exception('fluidTemplateTSFilePath not defined for pages output type');
        }
        if(!file_exists(GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['fluidTemplateTSFilePath']))) {
            throw new Exception('defined path \'fluidTemplateTSFilePath\' for pages output type does not exist');
        }
        file_put_contents(GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['backendLayoutPageTSFilePath']), $configuration['backendLayoutPageTS'] . "\n\n", FILE_APPEND);
    }


    public function writeTypoScriptPageSetup()
    {
        ksort($this->columnSetup['page'][10]['dataProcessing']);
        $pageSetup = Utility::convertArrayToTypoScript($this->columnSetup ?? []);
        file_put_contents(GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['fluidTemplateTSFilePath']), $pageSetup, FILE_APPEND);
    }

    protected function getConfigurationPath()
    {
        // TODO: Implement getConfigurationPath() method.
    }

    protected function generateFluidTemplateContent($configuration): string
    {
        return '';
    }

    protected function getFluidTemplatePath()
    {
        // TODO: Implement getFluidTemplatePath() method.
    }

    protected function writeFluidTemplate($templateContent)
    {
    }


    public function generateFluidTemplateContentAndWrite(): string
    {
        $templateContent = $this->getData()['templateContent'];
        // Template content
        foreach (array_keys($this->columnSetup['page'][10]['dataProcessing']) as $colPos) {

            $templateContent = preg_replace(
                '/(<v:content\.render column="' . ($colPos-100) . '" slide="-1"(.*?)>(.*?)<\/v:content\.render>)/s',
                '<f:format.raw>{column'  . $colPos . '}</f:format.raw>',
                $templateContent);

            $templateContent = preg_replace(
                '/(<v:content\.render column="' . ($colPos-100) . '"(.*?)>(.*?)<\/v:content\.render>)/s',
'<f:for each="{column' . ($colPos-100) . '}" as="contentElement">
    <f:cObject typoscriptObjectPath="tt_content.{contentElement.data.CType}" data="{contentElement.data}" table="tt_content" />
</f:for>',
                $templateContent);
        }
        file_put_contents(
            GeneralUtility::getFileAbsFileName($this->getOutputProviderSettings()['newTemplateRootPath'] . "/" . ucfirst($this->getData()['element']['id'])) . '.html',
            $templateContent
        );
        return (string)$templateContent;
    }




    public function migrateData($configuration)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $pages = $qb->from('pages')
            ->select('*')
            ->orWhere(
                $qb->expr()->eq('backend_layout', $qb->createNamedParameter('flux__grid')),
                $qb->expr()->eq('backend_layout_next_level', $qb->createNamedParameter('flux__grid'))
            )->execute()->fetchAllAssociative();
        foreach ($pages as $page) {
            $data = [];
            if($page['tx_fed_page_controller_action']) {
                list($extension, $action) = explode("->", $page['tx_fed_page_controller_action']);
                $data['backend_layout'] = strtolower("pagets__{$action}");
            }
            if($page['tx_fed_page_controller_action_sub']) {
                list($extension, $action) = explode("->", $page['tx_fed_page_controller_action_sub']);
                $data['backend_layout_next_level'] = strtolower("pagets__{$action}");
            }
            if($data) {
                $connection->update('pages', $data, ['uid' => $page['uid']]);
            }
        }
    }
}