<?php

namespace MoveCloser\ExportConverterBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddConvertExportStepToJobPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $jobServiceId = 'pim_connector.job.xlsx_product_export';

        if (!$container->hasDefinition($jobServiceId)) {
            // Job service does not exist - nothing to do
            return;
        }

        $definition = $container->getDefinition($jobServiceId);

        $steps = $definition->getArgument(3);

        if (!is_array($steps)) {
            $steps = [];
        }

        // Add your custom step service reference to the steps array
        $steps[0] = new Reference('movecloser.export_converter_bundle.step.convert_export_step');

        // Set the modified steps back
        $definition->replaceArgument(3, $steps);
    }
}
