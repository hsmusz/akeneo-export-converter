<?php

namespace MoveCloser\ExportConverterBundle;

use MoveCloser\ExportConverterBundle\DependencyInjection\Compiler\AddConvertExportStepToJobPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ExportConverterBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddConvertExportStepToJobPass());
    }
}
