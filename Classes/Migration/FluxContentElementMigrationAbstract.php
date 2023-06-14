<?php

namespace DKM\FluxMigrate\Migration;

use FluidTYPO3\Flux\Provider\Provider;
use FluidTYPO3\Flux\Provider\ProviderInterface;

abstract class FluxContentElementMigrationAbstract
{

    protected array $data = [];

    protected array $elementConfiguration = [];

    protected string $outputTarget = '';

    protected array $outputProviderSettings = [];

    /**
     * @var Provider
     */
    protected Provider $flexFormProvider;

    abstract public function resetFiles();

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
     * @return string
     */
    public function getOutputTarget(): string
    {
        return $this->outputTarget;
    }

    /**
     * @param string $outputTarget
     */
    public function setOutputTarget(string $outputTarget): void
    {
        $this->outputTarget = $outputTarget;
    }

    /**
     * @return array
     */
    public function getOutputProviderSettings(): array
    {
        return $this->outputProviderSettings;
    }

    /**
     * @param array $outputProviderSettings
     */
    public function setOutputProviderSettings(array $outputProviderSettings): void
    {
        $this->outputProviderSettings = $outputProviderSettings;
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
     * @param ProviderInterface $flexFormProvider
     */
    public function setFlexFormProvider(ProviderInterface $flexFormProvider): void
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
    abstract protected function writeFluidTemplate($templateContent);

    abstract public function migrateContentElements($configuration);





}