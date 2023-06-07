<?php

namespace DKM\FluxMigrate\Migration;

use DKM\FluxMigrate\Utility\Mask\ElementUtility;
use MASK\Mask\CodeGenerator\SqlCodeGenerator;
use MASK\Mask\CodeGenerator\TcaCodeGenerator;
use MASK\Mask\Definition\TableDefinitionCollection;
use MASK\Mask\Domain\Repository\StorageRepository;
use MASK\Mask\Loader\LoaderInterface;
use MASK\Mask\Utility\AffixUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluxContentElementToMaskMigration extends FluxContentElementMigrationAbstract
{
    private LoaderInterface $loader;

    private ?StorageRepository $storageRepository = null;

    private ?SqlCodeGenerator $sqlCodeGenerator = null;

    /**
     * @param LoaderInterface $loader
     * @param StorageRepository|null $storageRepository
     * @param SqlCodeGenerator|null $sqlCodeGenerator
     */
    public function __construct(LoaderInterface $loader, ?StorageRepository $storageRepository, ?SqlCodeGenerator $sqlCodeGenerator)
    {
        $this->loader = $loader;
        $this->storageRepository = $storageRepository;
        $this->sqlCodeGenerator = $sqlCodeGenerator;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function generateConfiguration(): array
    {
        $fields = [];
        // Generate tabs from sheets
        foreach ($this->getData()['sheets'] ?? [] as $sheetKey =>  $sheetData) {
            $fields[] = ElementUtility::getElement('sheet', ['sheetKey' => $sheetKey, 'label' => $sheetData['label']]);
            foreach ($sheetData['fields'] ?? [] as $field) {
                $type = $field['type'] == 'field' ? $field['attributes']['type'] : str_replace('field.', '', $field['type']);
                $fields[] = ElementUtility::getElement($type, $field['attributes']);
            }
        }
        $element = [
            'key' => $this->getData()['element']['id'],
            'icon' => "",
            'label' => $this->getData()['element']['name'] ?? $this->getData()['element']['id'],
            'shortLabel' => "",
            'description' => "",
            'color' => "#000000",
            'colorOverlay' => "#000000",
            'saveAndClose' => "0",
            'iconOverlay' => ""
        ];
        return ['element' => $element, 'fields' => $fields, 'type' => 'tt_content', 'isNew' => true];
    }

    /**
     * @param $configuration
     * @return void
     */
    protected function writeConfiguration($configuration)
    {
        $tableDefinitionCollection = $this->storageRepository->update($configuration['element'], $configuration['fields'], $configuration['type'], $configuration['isNew']);
        $this->loader->write($tableDefinitionCollection);
    }

    /**
     * @param $configuration
     * @return void
     */
    protected function generateFluidTemplateContent($configuration)
    {
        $test = 1;
        // TODO: Implement generateFluidTemplateContent() method.
    }

    /**
     * @param $template
     * @return void
     */
    protected function writeFluidTemplate($template)
    {
        // TODO: Implement writeFluidTemplate() method.
    }

    /**
     * @param $configuration
     * @return void
     */
    public function migrateContentElements($configuration)
    {
        // TODO: Implement migrateContentElements() method.
    }

    public function getConfigurationPath()
    {
        return self::getMaskExtCfg('content_elements_folder');
    }

    public function getFluidTemplatePath()
    {
        return self::getMaskExtCfg('content');
    }

    public static function getMaskExtCfg($key)
    {
        if(!$returnValue = (GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('mask')[$key] ?? false)) {
            return $returnValue;
        } else {
            throw new Exception("Please configure $key for EXT:mask");
        }
    }

    private function getFSCHeaderSomething()
    {
        //EXT:fluid_styled_content/Resources/Private/Partials/Header/All.html
    }



    private function getDBTable(): string
    {
        return 'tt_content';
    }

    private function getDBFieldDefinition(): string
    {
        return "varchar(255) DEFAULT '' NOT NULL";
    }





}