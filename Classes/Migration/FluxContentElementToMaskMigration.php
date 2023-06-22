<?php

namespace DKM\FluxMigrate\Migration;

use DKM\FluxMigrate\Utility;
use DKM\FluxMigrate\Utility\Mask\ElementUtility;
use MASK\Mask\CodeGenerator\SqlCodeGenerator;
use MASK\Mask\Domain\Repository\StorageRepository;
use MASK\Mask\Loader\LoaderInterface;
use MASK\Mask\Utility\TemplatePathUtility;
use Symfony\Component\DomCrawler\Crawler;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluxContentElementToMaskMigration extends FluxMigrationAbstract
{
    private LoaderInterface $loader;

    private ?StorageRepository $storageRepository = null;

    private ?ElementUtility $elementUtility = null;

    private ?SqlCodeGenerator $sqlCodeGenerator = null;

    /**
     * @var array<string, string>
     */
    protected array $maskExtensionConfiguration;

    /**
     * @param LoaderInterface $loader
     * @param StorageRepository|null $storageRepository
     * @param ElementUtility|null $elementUtility
     * @param SqlCodeGenerator|null $sqlCodeGenerator
     * @param string[] $maskExtensionConfiguration
     */
    public function __construct(LoaderInterface $loader, ?StorageRepository $storageRepository, ?ElementUtility $elementUtility, ?SqlCodeGenerator $sqlCodeGenerator, array $maskExtensionConfiguration)
    {
        $this->loader = $loader;
        $this->storageRepository = $storageRepository;
        $this->elementUtility = $elementUtility;
        $this->sqlCodeGenerator = $sqlCodeGenerator;
        $this->maskExtensionConfiguration = $maskExtensionConfiguration;
    }

    /**
     * @return array
     */
    public function getResetPaths(): array
    {
        return [];
    }


    /**
     * @return array
     * @throws Exception
     */
    protected function generateConfiguration(): array
    {
        $fieldsConfigurationData = [];
        // Container
        if( ($this->getData()['grid'] ?? false)) {
            $this->setOutputTarget('container');
            return [];

        // Sections
        } else if(($this->getData()['sections'] ?? false)) {
            $this->setOutputTarget('sections');
            return [];

        // If no sections or grid columns, migrate to Mask Element
        } else {
            $this->setOutputTarget('mask');
            $this->addFields($this->getData()['fields'] ?? [], $fieldsConfigurationData);
            // Generate tabs from sheets
            foreach ($this->getData()['sheets'] ?? [] as $sheetKey =>  $sheetData) {
                $fieldsConfigurationData[] = $this->elementUtility->getElementSheet(['sheetKey' => $sheetKey, 'label' => $sheetData['label'] ?? 'LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general']);
                $this->addFields($sheetData['fields'], $fieldsConfigurationData);
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
            return ['element' => $element, 'fields' => $fieldsConfigurationData, 'type' => 'tt_content', 'isNew' => true];
        }
    }

    /**
     * @throws Exception
     */
    private function addFields($fields, &$fieldsConfigurationData)
    {
        foreach ($fields ?? [] as $field) {
            $type = Utility::getFieldTypeFromXMLData($field);
            $fieldsConfigurationData[] = $this->elementUtility->getElementField($this->getFlexFormProvider()->getContentObjectType(), $type, $field['attributes']);
        }
    }

    /**
     * @param $configuration
     * @return void
     */
    protected function writeConfiguration($configuration)
    {
        if($this->getOutputTarget() == 'mask') {
            $tableDefinitionCollection = $this->storageRepository->update($configuration['element'], $configuration['fields'], $configuration['type'], $configuration['isNew']);
            $this->loader->write($tableDefinitionCollection);
            //
            $this->sqlCodeGenerator->updateDatabase();
        }
    }

    /**
     * @param $configuration
     * @return string
     */
    protected function generateFluidTemplateContent($configuration): string
    {
        if($this->getOutputTarget() === 'mask') {
            $fieldMap = [];
            foreach ($configuration['fields'] ?? [] as $field) {
                $fieldMap["{" . str_replace('tx_mask_', '', $field['originalKey']) . "}"] = "{data." . $field['key'] . "}";
            }
            return str_replace(array_keys($fieldMap), $fieldMap, $this->getData()['templateContent'] ?? '');
        } else {
            return '';
        }
    }

    /**
     * @param $templateContent
     * @return bool|void
     */
    protected function writeFluidTemplate($templateContent)
    {
        if($this->getOutputTarget() === 'mask') {
            if(!($key = $this->getData()['element']['id'] ?? false)) {
                throw new Exception('Element had no id');
            }
            // fallback to prevent breaking change
            $path = TemplatePathUtility::getTemplatePath($this->maskExtensionConfiguration, $key);
            // Do not override existing files.
//            if (file_exists($path)) {
//                return false;
//            }
            return GeneralUtility::writeFile($path, $templateContent);
        }
    }

    /**
     * @param $configuration
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function migrateData($configuration)
    {

        if($this->getOutputTarget()  === 'mask') {
            $CType = $this->getFlexFormProvider()->getContentObjectType();
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
            $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
            $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $keyMap = array_column($configuration['fields'] ?? [], null, 'originalKey');

            $sheets = $this->getData()['sheets'] ?? [];
            if($this->getData()['fields']) {
                $sheets['options'] = ['fields' => $this->getData()['fields']];
            }

            foreach ($qb->from('tt_content')->select('*')->where($qb->expr()->eq('CType', $qb->createNamedParameter($CType)))->execute()->fetchAllAssociative() ?? [] as $element) {
                $crawler = new Crawler($element['pi_flexform']);
                $update = ['CType' => 'mask_' . $configuration['element']['key']];
                foreach ($sheets ?? [] as $sheetKey => $sheetData) {
                    $sheetNode = $crawler->filter("sheet[index=" . Utility::domCrawlerEscapeForSelector($sheetKey) . "]");
                    if($sheetNode->count()) {
                        foreach ($sheetData['fields'] ?? [] as $field) {
                            $fieldValueNode = $sheetNode->filter("field[index=" . Utility::domCrawlerEscapeForSelector($field['attributes']['name']) . "] value[index=vDEF]");
                            if($fieldValueNode->count()) {
                                $fieldName = $keyMap[$field['attributes']['name']]['key'];
                                if($keyMap[$field['attributes']['name']]['name'] == 'media') {
    //                                $fileReferenceUids = GeneralUtility::trimExplode(',', $fieldValueNode->html(), true);
                                    $fileReferences = Utility::getFileReferences($keyMap[$field['attributes']['name']]['originalKey'], $element['uid']);
                                    foreach ($fileReferences as $fileReference) {
                                        try {
                                            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
    //                                        $fileUid = Utility::getFileUidFromReference($fileReferenceUid, true);
                                            if($fileObject = $resourceFactory->getFileObject((int)$fileReference['uid_local'])) {
                                                Utility::addFileReference($fileObject->getUid(), $element['uid'], $element['pid'], $fieldName);
                                            }
                                        } catch (ResourceDoesNotExistException $e) {
                                        }
                                    }
                                } else {
                                    $update[$fieldName] = html_entity_decode($fieldValueNode->html());
                                }
                            }
                        }
                    }
                }
                $connection->update('tt_content', $update, ['uid' => $element['uid']]);
            }
        }
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