<?php

namespace DKM\FluxMigrate\Migration;

class FluxContentSectionsMigration extends FluxContentElementMigrationAbstract
{

    /**
     * @param bool $doNotResetFiles
     * @return bool
     */
    public function resetFiles(bool $doNotResetFiles = false): bool
    {
        return true;
    }


    protected function generateConfiguration()
    {
        // TODO: Implement generateConfiguration() method.
    }

    protected function writeConfiguration($configuration)
    {
        // TODO: Implement writeConfiguration() method.
    }

    protected function getConfigurationPath()
    {
        // TODO: Implement getConfigurationPath() method.
    }

    protected function generateFluidTemplateContent($configuration)
    {
        // TODO: Implement generateFluidTemplateContent() method.
    }

    protected function getFluidTemplatePath()
    {
        // TODO: Implement getFluidTemplatePath() method.
    }

    protected function writeFluidTemplate($templateContent)
    {
        // TODO: Implement writeFluidTemplate() method.
    }

    public function migrateContentElements($configuration)
    {
        // TODO: Implement migrateContentElements() method.
    }
}