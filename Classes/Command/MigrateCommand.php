<?php

namespace DKM\FluxMigrate\Command;

use DKM\FluxMigrate\Migration\FluxContentElementMigrationAbstract;
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
                if(!class_exists($this->configuration['output']['name'])) {
                    throw new Exception("The class {$this->configuration['output']['name']} does not exist!");
                }
                /** @var FluxContentElementMigrationAbstract $outPutProvider */
                $outPutProvider = GeneralUtility::makeInstance($this->configuration['output']['name']);
                $outPutProvider->setData($data);
                $outPutProvider->setFlexFormProvider($flexFormProvider);
                $outPutProvider->migrateElement();
            }
        }
    }


    /**
     * @param $path
     * @return array
     */
    protected function getDataFromElement($path)
    {
        $sheets = [];
        $sections = [];
        $crawler = new Crawler(file_get_contents($path));
        $configuration = $crawler->filter('section[name=Configuration] form');
        $configuration->children()->each(function (Crawler $domElement, $i) use (&$sheets, &$optionGroupName) {
            switch ($domElement->nodeName()) {
                case 'form.option.group':
                    $optionGroupName = $domElement->html();
                    break;
                case 'form.sheet':
                    $sheetName = $domElement->attr('name');
                    $sheets[$sheetName]['label'] = $domElement->attr('label');
                    $domElement->children()->each(function (Crawler $node, $i) use ($sheetName, &$sheets) {
                        $sheets[$sheetName]['fields'][] = $this->getFieldData($node);
                    });
                    break;
                case 'form.section':
                    $sectionName = $domElement->attr('name');
                    $sheets[$sectionName]['label'] = $domElement->attr('label');
                    $domElement->children()->each(function (Crawler $node, $i) use ($sectionName, &$sections) {
                        $sheets[$sectionName]['fields'][] = $this->getFieldData($node);
                    });
                    break;
            }
        });
        return ['element' => ['id' => $configuration->attr('id'),
            'label' => $configuration->attr('label'),
            'optionGroup' => $optionGroupName],
            'sheets' => $sheets,
            'sections' => $sections];
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
        $elements = $crawler->filter('section form form\.sheet');
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