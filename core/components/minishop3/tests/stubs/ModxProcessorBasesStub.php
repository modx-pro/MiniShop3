<?php

/**
 * Minimal MODX processor bases for permission reflection smoke (#672).
 * Empty $permission mirrors ModelProcessor::checkPermissions() passthrough.
 */

declare(strict_types=1);

namespace MODX\Revolution\Processors {
    if (!class_exists(Processor::class, false)) {
        class Processor
        {
            public $permission = '';
        }
    }

    if (!class_exists(ModelProcessor::class, false)) {
        class ModelProcessor extends Processor
        {
        }
    }

    if (!class_exists(GetProcessor::class, false)) {
        class GetProcessor extends ModelProcessor
        {
        }
    }

    if (!class_exists(GetListProcessor::class, false)) {
        class GetListProcessor extends ModelProcessor
        {
        }
    }

    if (!class_exists(CreateProcessor::class, false)) {
        class CreateProcessor extends ModelProcessor
        {
        }
    }

    if (!class_exists(UpdateProcessor::class, false)) {
        class UpdateProcessor extends ModelProcessor
        {
        }
    }

    if (!class_exists(DeleteProcessor::class, false)) {
        class DeleteProcessor extends ModelProcessor
        {
        }
    }

    if (!class_exists(ProcessorResponse::class, false)) {
        class ProcessorResponse
        {
        }
    }
}

namespace MODX\Revolution\Processors\Model {
    if (!class_exists(GetProcessor::class, false)) {
        class GetProcessor extends \MODX\Revolution\Processors\GetProcessor
        {
        }
    }

    if (!class_exists(GetListProcessor::class, false)) {
        class GetListProcessor extends \MODX\Revolution\Processors\GetListProcessor
        {
        }
    }

    if (!class_exists(CreateProcessor::class, false)) {
        class CreateProcessor extends \MODX\Revolution\Processors\CreateProcessor
        {
        }
    }

    if (!class_exists(UpdateProcessor::class, false)) {
        class UpdateProcessor extends \MODX\Revolution\Processors\UpdateProcessor
        {
        }
    }

    if (!class_exists(DeleteProcessor::class, false)) {
        class DeleteProcessor extends \MODX\Revolution\Processors\DeleteProcessor
        {
        }
    }

    if (!class_exists(RemoveProcessor::class, false)) {
        class RemoveProcessor extends \MODX\Revolution\Processors\ModelProcessor
        {
        }
    }
}

namespace MODX\Revolution\Processors\Resource {
    if (!class_exists(Create::class, false)) {
        class Create extends \MODX\Revolution\Processors\CreateProcessor
        {
        }
    }

    if (!class_exists(Update::class, false)) {
        class Update extends \MODX\Revolution\Processors\UpdateProcessor
        {
        }
    }

    if (!class_exists(Delete::class, false)) {
        class Delete extends \MODX\Revolution\Processors\DeleteProcessor
        {
        }
    }

    if (!class_exists(Publish::class, false)) {
        class Publish extends \MODX\Revolution\Processors\ModelProcessor
        {
        }
    }

    if (!class_exists(Unpublish::class, false)) {
        class Unpublish extends \MODX\Revolution\Processors\ModelProcessor
        {
        }
    }

    if (!class_exists(Undelete::class, false)) {
        class Undelete extends \MODX\Revolution\Processors\ModelProcessor
        {
        }
    }

    if (!class_exists(GetNodes::class, false)) {
        class GetNodes extends \MODX\Revolution\Processors\ModelProcessor
        {
        }
    }
}
