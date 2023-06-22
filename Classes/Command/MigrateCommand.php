<?php

namespace DKM\FluxMigrate\Command;

use DKM\FluxMigrate\Migration\FluxMigrationAbstract;
use DKM\FluxMigrate\Migration\FluxPageToCoreAndMaskMigration;
use DKM\FluxMigrate\Utility;
use FluidTYPO3\Flux\Core;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Parser;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MigrateCommand extends \Symfony\Component\Console\Command\Command
{
    public array $configuration = [];

    /**
     * @var SymfonyStyle
     */
    public $io;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Tool to help migrate from the TYPO3 extension Flux to a solution closer to core.')
            ->addArgument('configurationFileName', InputArgument::OPTIONAL, 'Name of file in config folder which contains the migration configuration', 'fluxmigrate.yaml')
            ->setHelp('.... help help help....' . LF . 'If you want to get more detailed information, use the --verbose option.');
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $cfgFile = Environment::getConfigPath() . '/' . $input->getArgument('configurationFileName');
        if(!file_exists($cfgFile)) {
            throw new Exception("The configuration file was not found at: @$cfgFile");
        }

        $parser = new Parser();
        $this->configuration = $parser->parseFile($cfgFile);

        $this->io->title($this->getDescription());
        $this->migrateContent();
        $this->migratePageTemplates();



        return 0;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function migrateContent() {
        $this->io->section('Working on templates');
        foreach (Core::getRegisteredFlexFormProviders() as $flexFormProvider) {
            if(is_object($flexFormProvider)) {
                $data = $this->getDataFromElement($flexFormProvider->getTemplatePathAndFilename([]));
//                if($data['type'] == 'sections') {
                    if($outputProvider = $this->getOutputProvider($data['type'], $data)) {
                        $outputProvider->setFlexFormProvider($flexFormProvider);
                        $outputProvider->execute();
                        $this->io->info($flexFormProvider->getContentObjectType() . " of type {$data['type']} migrated!");
                    }
//                }
            }
        }
    }

    /**
     * @param $type
     * @param $data
     * @return FluxMigrationAbstract
     * @throws Exception
     */
    protected function getOutputProvider($type, $data): ?FluxMigrationAbstract
    {
        $outputProvider = null;
        if($type ?? false) {
            if($className = $this->configuration['output'][$type]['name'] ?? '') {
                if(!class_exists($className)) {
                    throw new Exception("The class {$className} does not exist!");
                }
                /** @var FluxMigrationAbstract $outputProvider */
                $outputProvider = GeneralUtility::makeInstance($className);
                $outputProvider->setData($data);
                $outputProvider->setOutputProviderSettings($this->configuration['output'][$type]);
                $outputProvider->initOutputProvider();
//                $typeResetFiles[$type] = $outputProvider->getResetFiles($typeResetFiles[$type] ?? false);
            } else {
                throw new Exception("No class name was configured for the type {$type}!");
            }
        }
        return $outputProvider;
    }


    /**
     * @param $path
     * @return array
     */
    protected function getDataFromElement($path): array
    {
        $elementCfg = [];
        $xml = Utility::convertXMLToNonNamespace(file_get_contents($path));
        $crawler = new Crawler($xml);
//        $domDoc = new \DOMDocument("1.0","utf-8");
//        $domDoc->loadXML($html);
//        echo $domDoc->saveXML();



//        libxml_use_internal_errors(true);
//        $sxe = simplexml_load_string(Utility::sanitizeXMLFromFile($path));
//        if ($sxe === false) {
//            echo "Failed loading XML\n";
//            foreach(libxml_get_errors() as $error) {
//                echo "\t", $error->message;
//            }
//        }
//
//
//        $dom = new \DOMDocument("1.0");
//        $dom->loadXML(file_get_contents($path));
//        foreach ($dom->childNodes as $childNode) {
//            $test = 1;
//        }
//        $testOutput = $dom->saveXML();
        $crawler->filter('div > typo3fluidfluid\.section, typo3fluidfluid\.section:root')->each(function(Crawler $sectionNode, $i) use (&$elementCfg) {
            $types = [];

            if($sectionNode->attr('name') == 'Configuration') {
                $formNode = $sectionNode->filter('fluidtypo3flux\.form');
                $elementCfg['element']['id'] = $formNode->attr('id');
                $elementCfg['element']['label'] = $formNode->attr('label');
                $formNode->children()->each(function (Crawler $domElement, $i) use (&$elementCfg, &$types) {
                    switch ($domElement->nodeName()) {
                        case 'fluidtypo3flux.form.option.group':
                            $elementCfg['element']['optionGroup'] = $domElement->html();
                            break;
                        case 'fluidtypo3flux.form.sheet':
                            $sheetName = $domElement->attr('name');
                            $elementCfg['sheets'][$sheetName]['label'] = $domElement->attr('label');
                            $domElement->children()->each(function (Crawler $node, $i) use ($sheetName, &$elementCfg) {
                                $elementCfg['sheets'][$sheetName]['fields'][] = $this->getFieldData($node);
                            });
                            $types[] = 'sheets';
                            break;
                        case 'fluidtypo3flux.form.section':
                            $sectionName = $domElement->attr('name');
//                            $elementCfg['sections'][$sectionName]['label'] = $domElement->attr('label');
                            $domElement->children()->each(function (Crawler $objectNode, $objectIndex) use ($sectionName, &$elementCfg) {
                                $objectName = $objectNode->attr('name');
                                $objectLabel = $objectNode->attr('label') ?? $objectNode->attr('name');
                                $elementCfg['sections'][$sectionName][$objectName] = ['name' => $objectName, 'label' => $objectLabel];
                                $objectNode->children()->each(function (Crawler $node, $i) use ($objectIndex,  $sectionName, $objectName, &$elementCfg) {
                                    $elementCfg['sections'][$sectionName][$objectName]['fields'][] = $this->getFieldData($node);
                                });
                            });
                            $types[] = 'sections';
                            break;
                        case 'fluidtypo3flux.grid':
                            $domElement->children('fluidtypo3flux\.grid\.row')->each(function (Crawler $nodeRow, $iRow) use (&$elementCfg) {
                                $nodeRow->children('fluidtypo3flux\.grid\.column')->each(function (Crawler $nodeColumn, $iColumn) use (&$elementCfg, $iRow) {
                                    $elementCfg['grid']['rows'][$iRow]['columns'][$iColumn] = [
                                        'name' => $nodeColumn->attr('name'),
                                        'colPos' => $nodeColumn->attr('colpos') ?? 0,
                                        'colspan' => $nodeColumn->attr('colspan'),
                                        'label' => $nodeColumn->attr('label'),
                                        'style' => $nodeColumn->attr('style')
                                    ];
                                    // remove empty, false and null values
                                    $elementCfg['grid']['rows'][$iRow]['columns'][$iColumn] = array_filter($elementCfg['grid']['rows'][$iRow]['columns'][$iColumn], fn($b) => strlen((string)$b));
                                });
                            });
                            $types[] = 'columns';
                            break;
                        default:
                            if (str_starts_with($domElement->nodeName(), 'fluidtypo3flux.field')) {
                                $elementCfg['sheets']['options']['fields'][] = $this->getFieldData($domElement);
                                $types[] = 'sheets';
                            }
                    }
                });
            } else if ($sectionNode->attr('name') == 'Preview') {
                $elementCfg['templatePreview'] = $sectionNode;
            } else {
                $elementCfg['templateSections'][$sectionNode->attr('name')] = $sectionNode;
            }

            if( in_array('columns', $types)) {
                $elementCfg['type'] = 'columns';
            } elseif( in_array('sections', $types)) {
                $elementCfg['type'] = 'sections';
            } elseif( in_array('sheets', $types)) {
                $elementCfg['type'] = 'sheets';
            }
        });

        if(isset($elementCfg['sheets']['options'])) {
            $elementCfg['sheets']['options']['label'] = 'LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general';
        }


        $crawler->filter('typo3fluidfluid\.section[name=Configuration], typo3fluidfluid\.section[name=Preview]')->each(function(Crawler $node) {
           $node = $node->getNode(0);
            $node->parentNode->removeChild($node);
        });
        $elementCfg['templateContent'] = Utility::convertXMLToNamespace($crawler->html());
        return $elementCfg;
    }


    /**
     * @throws Exception
     */
    protected function migratePageTemplates() {
        $this->io->section('Working on page templates');
        foreach (GeneralUtility::getAllFilesAndFoldersInPath([], GeneralUtility::getFileAbsFileName($this->configuration['output']['pages']['fluxTemplateRootPath'])) as $file) {
            $data = $this->getDataFromElement($file);
            /** @var FluxPageToCoreAndMaskMigration $outputProvider */
            $outputProvider = $this->getOutputProvider('pages', $data);
            $outputProvider->execute();
            $this->io->info(pathinfo($file)['basename'] . ' migrated!');
        }
        if(isset($outputProvider)) {
            $outputProvider->writeTypoScriptPageSetup();
        }

        foreach (GeneralUtility::getAllFilesAndFoldersInPath([], GeneralUtility::getFileAbsFileName($this->configuration['output']['pages']['fluxTemplateRootPath'])) as $file) {
            $data = $this->getDataFromElement($file);
            /** @var FluxPageToCoreAndMaskMigration $outputProvider */
            $outputProvider = $this->getOutputProvider('pages', $data);
            $outputProvider->generateFluidTemplateContentAndWrite();
        }

    }






    /**
     * @param Crawler $crawler
     * @return void
     */
    protected function migratePageTemplateFormSheet(Crawler $crawler) {
        $sheets = [];
        $elements = $crawler->filter('fluidtypo3flux\.section fluidtypo3flux\.form fluidtypo3flux\.form\.sheet');
        $elements->each(function (Crawler $sheetNode, $i) use (&$sheets) {
            $sheetName = $sheetNode->attr('name');
            $sheetLabel = $sheetNode->attr('label');
            $sheets[$sheetName]['label'] = $sheetLabel;
            $sheetNode->children()->each(function (Crawler $node, $i) use ($sheetName, &$sheets) {
                $sheets[$sheetName]['fields'][] = $this->getFieldData($node);
            });
        });

    }

    /**
     * @param Crawler $node
     * @return array
     */
    protected function getFieldData(Crawler $node): array
    {
        $attributes = [];
        foreach ($node->getNode(0)->attributes as $attribute) {
            $attributes[$attribute->nodeName] = $attribute->nodeValue;
        }
        return ['type' => $node->nodeName(), 'attributes' => $attributes];
    }
}