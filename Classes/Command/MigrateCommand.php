<?php

namespace DKM\FluxMigrate\Command;

use DKM\FluxMigrate\Migration\FluxContentElementMigrationAbstract;
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MigrateCommand extends \Symfony\Component\Console\Command\Command
{
    public $configuration = [];

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
//        $this->migratePageTemplates();



        return 0;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function migrateContent() {
        foreach (Core::getRegisteredFlexFormProviders() as $flexFormProvider) {
            if(is_object($flexFormProvider)) {
                $data = $this->getDataFromElement($flexFormProvider->getTemplatePathAndFilename([]));
                if($data['type'] ?? false) {
                    if($className = $this->configuration['output'][$data['type']]['name'] ?? '') {
                        if(!class_exists($className)) {
                            throw new Exception("The class {$className} does not exist!");
                        }
                        /** @var FluxContentElementMigrationAbstract $outputProvider */
                        $outputProvider = GeneralUtility::makeInstance($className);
                        $outputProvider->setData($data);
                        $outputProvider->setFlexFormProvider($flexFormProvider);
                        $outputProvider->setOutputProviderSettings($this->configuration['output'][$data['type']]);
                        $typeResetFiles[$data['type']] = $outputProvider->resetFiles($typeResetFiles[$data['type']] ?? false);
                        $outputProvider->migrateElement();
                    } else {
                        throw new Exception("No class name was configured for the type {$data['type']}!");
                    }
                }
            }
        }
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
                            $elementCfg['sections'][$sectionName]['label'] = $domElement->attr('label');
                            $domElement->children()->each(function (Crawler $node, $i) use ($sectionName, &$elementCfg) {
                                $elementCfg['sections'][$sectionName]['fields'][] = $this->getFieldData($node);
                            });
                            $types[] = 'sections';
                            break;
                        case 'fluidtypo3flux.grid':
                            $domElement->children('fluidtypo3flux\.grid\.row')->each(function (Crawler $nodeRow, $iRow) use (&$elementCfg) {
                                $nodeRow->children('fluidtypo3flux\.grid\.column')->each(function (Crawler $nodeColumn, $iColumn) use (&$elementCfg, $iRow) {
                                    $elementCfg['grid']['rows'][$iRow]['columns'][$iColumn] = [
                                        'name' => $nodeColumn->attr('name'),
                                        'colPos' => $nodeColumn->attr('colpos') ?? 0,
                                        'label' => $nodeColumn->attr('label'),
                                        'style' => $nodeColumn->attr('style')
                                    ];
                                    $elementCfg['grid']['rows'][$iRow]['columns'][$iColumn] = array_filter($elementCfg['grid']['rows'][$iRow]['columns'][$iColumn]);
                                });
                            });
                            $types[] = 'columns';
                            break;
                        default:
                            if (str_starts_with($domElement->nodeName(), 'fluidtypo3flux.field')) {
                                $elementCfg['sheets']['options']['fields'][] = $this->getFieldData($domElement);
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


        $crawler->filter('typo3fluidfluid\.section[name=Configuration], typo3fluidfluid\.section[name=Preview]')->each(function(Crawler $node) {
           $node = $node->getNode(0);
            $node->parentNode->removeChild($node);
        });
        $elementCfg['templateContent'] = Utility::convertXMLToNamespace($crawler->html());
        return $elementCfg;
    }






    protected function migratePageTemplates() {
        foreach ($this->configuration['paths']['pageTemplates'] ?? [] as $template) {
            if($template['migrated'] ?? false) {
                $this->io->info('Done: ' . GeneralUtility::getFileAbsFileName($template['path']));
                continue;
            }
            $this->io->section('Working on: ' . GeneralUtility::getFileAbsFileName($template['path']));
            $crawler = new Crawler(file_get_contents(GeneralUtility::getFileAbsFileName($template['path'])));
            $this->migratePageTemplateFormSheet($crawler);
            return;
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




//    protected function migrateFieldTypeInput(Crawler $node)
//    {
//        $attributes = [];
//        foreach ($node->getNode(0)->attributes as $attribute) {
//            $attributes[$attribute->nodeName] = $attribute->nodeValue;
//        }
//        return ['type' => $node->nodeName(), 'attributes' => $attributes];
//    }
//    protected function migrateFieldTypeText(Crawler $node)
//    {
//        $attributes = [];
//        foreach ($node->getNode(0)->attributes as $attribute) {
//            $attributes[$attribute->nodeName] = $attribute->nodeValue;
//        }
//        return ['type' => $node->nodeName(), 'attributes' => $attributes];
//    }



}