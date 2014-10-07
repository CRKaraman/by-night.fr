<?php

namespace BeSimple\I18nRoutingBundle\Routing\Translator\DoctrineDBAL;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class SchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs)
    {
        $schema = $eventArgs->getSchema();
        $this->addRoutingTranslationsTable($schema);
    }

    public function addRoutingTranslationsTable(Schema $schema)
    {
        $table = $schema->createTable('routing_translations');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('route', 'string');
        $table->addColumn('locale', 'string');
        $table->addColumn('attribute', 'string');
        $table->addColumn('localized_value', 'string');
        $table->addColumn('original_value', 'string');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['route', 'locale', 'attribute']);
        $table->addIndex(['localized_value']); // this is much more selective than the unique index
    }
}
