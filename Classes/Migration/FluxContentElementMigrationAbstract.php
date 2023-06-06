<?php

namespace DKM\FluxMigrate\Migration;

use FluidTYPO3\Flux\Provider\Provider;

abstract class FluxContentElementMigrationAbstract
{

    protected array $data = [];

    protected array $elementConfiguration = [];

    /**
     * @var Provider
     */
    protected $flexFormProvider;


    /**
     * @param $data
     * @return void
     */
    public function setData($data) {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getElementConfiguration(): array
    {
        return $this->elementConfiguration;
    }

    /**
     * @param array $elementConfiguration
     */
    public function setElementConfiguration(array $elementConfiguration): void
    {
        $this->elementConfiguration = $elementConfiguration;
    }

    /**
     * @return Provider
     */
    public function getFlexFormProvider(): Provider
    {
        return $this->flexFormProvider;
    }

    /**
     * @param Provider $flexFormProvider
     */
    public function setFlexFormProvider(Provider $flexFormProvider): void
    {
        $this->flexFormProvider = $flexFormProvider;
    }

    /**
     * @return mixed
     */
    public function migrateElement()
    {
        $configuration = $this->generateConfiguration();
        $this->writeConfiguration($configuration);
        $template = $this->generateFluidTemplateContent($configuration);
        $this->writeFluidTemplate($template);
        $this->migrateContentElements($configuration);
    }
    abstract protected function generateConfiguration();
    abstract protected function writeConfiguration($configuration);
    abstract protected function getConfigurationPath();

    /**
     * @return void
     */
    public function createFluidTemplate()
    {
        $template = $this->generateFluidTemplateContent();
        // write template
//        $this->getFluidTemplatePath();
    }
    abstract protected function generateFluidTemplateContent($configuration);
    abstract protected function getFluidTemplatePath();
    abstract protected function writeFluidTemplate($template);

    abstract public function migrateContentElements($configuration);





}