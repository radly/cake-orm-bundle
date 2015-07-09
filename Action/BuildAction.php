<?php

namespace RadBundle\CakeORM\Action;

use App\Action\AppAction;
use Cake\Database\Schema\Table;
use Cake\Datasource\ConnectionManager;
use DOMDocument;
use DOMElement;
use DOMXPath;
use League\CLImate\CLImate;
use Rad\Config;
use Rad\Core\Bundles;
use Rad\Utility\Inflection;

/**
 * Build Action
 *
 * @package RadBundle\CakeOrm\Action
 */
class BuildAction extends AppAction
{
    /**
     * Cli method
     *
     * @param CLImate $climate
     *
     * @throws \Rad\Core\Exception\MissingBundleException
     */
    public function cliMethod(CLImate $climate)
    {
        $sqlDir = VAR_DIR . DS . 'cake_orm' . DS . 'sql';
        if (!is_dir($sqlDir)) {
            mkdir($sqlDir, 0777, true);
        }

        $sql = [];
        foreach (Bundles::getLoaded() as $bundle) {
            $climate->info(sprintf('Bundle %s ...', $bundle));
            $path = Bundles::getPath($bundle);
            $sqlFilename = Inflection::underscore($bundle) . '.sql';
            $schemaPath = $path . DS . 'Resource' . DS . 'config' . DS . 'schema.xml';

            if (!is_file($schemaPath)) {
                $climate->lightRed(sprintf('File "%s" does not exists.', $schemaPath));
                continue;
            }

            $domDocument = new DOMDocument();
            $domDocument->load($schemaPath);

            $xpath = new DOMXPath($domDocument);
            $tables = $xpath->query('/database/table');

            /** @var DOMElement $table */
            foreach ($tables as $table) {
                $schemaTable = new Table($table->getAttribute('name'));

                $this->buildTableRegistry($table, $bundle);

                $this->prepareColumn($table, $xpath, $schemaTable);
                $this->prepareForeignConstraint($table, $xpath, $schemaTable);
                $this->prepareUniqueConstraint($table, $xpath, $schemaTable);
                $this->preparePrimaryConstraint($table, $xpath, $schemaTable);
                $this->prepareIndex($table, $xpath, $schemaTable);

                $sql[$sqlDir . DS . $sqlFilename][] = $schemaTable->createSql(ConnectionManager::get('default'));
            }

            foreach ($sql as $file => $tablesSql) {
                $tmpSql = '';
                foreach ($tablesSql as $tableSql) {
                    $tmpSql .= implode(";\n", $tableSql);
                    $tmpSql .= "\n\n";
                }

                file_put_contents($file, $tmpSql);
                $climate->backgroundLightBlue(sprintf('Create SQL file "%s".', $file));
            }
        }
    }

    /**
     * Build table registry
     *
     * @param DOMElement $table
     * @param string     $bundle Bundle name
     */
    protected function buildTableRegistry(DOMElement $table, $bundle)
    {
        $mapDirPath = Bundles::getPath($bundle) . DS . 'Domain' . DS . 'Model' . DS . 'map';
        if (!is_dir($mapDirPath)) {
            mkdir($mapDirPath, 0777, true);
        }

        if (($className = $table->getAttribute('className')) !== '') {
            $tableName = !empty($table->getAttribute('name')) ? "'" . $table->getAttribute('name') . "'" : null;
            $alias = !empty($table->getAttribute('alias')) ? "'" . $table->getAttribute('alias') . "'" : null;

            $code = <<<PHP
    TableRegistry::config('$bundle.Categories', [
        'table' => $tableName,
        'alias' => $alias,
        'className' => '$className',
    ]);
PHP;
        }
    }

    /**
     * Prepare column
     *
     * @param DOMElement $table
     * @param DOMXPath   $xpath
     * @param Table      $schemaTable
     */
    protected function prepareColumn(DOMElement $table, DOMXPath $xpath, Table $schemaTable)
    {
        $columns = $xpath->query(sprintf('/database/table[@name="%s"]/column', $table->getAttribute('name')));
        /** @var \DOMElement $column */
        foreach ($columns as $column) {
            $schemaTable->addColumn(
                $column->getAttribute('name'),
                [
                    'type' => !empty($column->getAttribute('type')) ? $column->getAttribute('type') : null,
                    'length' => !empty($column->getAttribute('length')) ? $column->getAttribute('length') : null,
                    'precision' => !empty($column->getAttribute('precision')) ? $column->getAttribute(
                        'precision'
                    ) : null,
                    'default' => !empty($column->getAttribute('default')) ? $column->getAttribute('default') : null,
                    'null' => !empty($column->getAttribute('null')) ? $column->getAttribute('null') : null,
                    'fixed' => !empty($column->getAttribute('fixed')) ? $column->getAttribute('fixed') : null,
                    'unsigned' => !empty($column->getAttribute('unsigned')) ? $column->getAttribute('unsigned') : null,
                    'comment' => !empty($column->getAttribute('comment')) ? $column->getAttribute('comment') : null
                ]
            );
        }
    }

    /**
     * Prepare foreign key constraint
     *
     * @param DOMElement $table
     * @param DOMXPath   $xpath
     * @param Table      $schemaTable
     */
    protected function prepareForeignConstraint(DOMElement $table, DOMXPath $xpath, Table $schemaTable)
    {
        $foreignKeys = $xpath->query(sprintf('/database/table[@name="%s"]/foreign', $table->getAttribute('name')));

        /** @var DOMElement $foreignKey */
        foreach ($foreignKeys as $foreignKey) {
            $foreignTable = $foreignKey->getAttribute('foreignTable');
            $references = $foreignKey->getElementsByTagName('reference');

            /** @var DOMElement $reference */
            foreach ($references as $reference) {
                $localColumn = $reference->getAttribute('local');
                $foreignColumn = $reference->getAttribute('foreign');
                $constraintName = sprintf(
                    '%s_%s_%s_%s_foreign',
                    $table->getAttribute('name'),
                    $foreignTable,
                    $localColumn,
                    $foreignColumn
                );

                $schemaTable->addConstraint(
                    $constraintName,
                    [
                        'type' => 'foreign',
                        'columns' => [$localColumn],
                        'references' => [$foreignTable, $foreignColumn],
                        'update' => $foreignKey->getAttribute('onUpdate'),
                        'delete' => $foreignKey->getAttribute('onDelete')
                    ]
                );
            }
        }
    }

    /**
     * Prepare unique constraint
     *
     * @param DOMElement $table
     * @param DOMXPath   $xpath
     * @param Table      $schemaTable
     */
    protected function prepareUniqueConstraint(DOMElement $table, DOMXPath $xpath, Table $schemaTable)
    {
        $uniqueTags = $xpath->query(sprintf('/database/table[@name="%s"]/unique', $table->getAttribute('name')));

        /** @var DOMElement $uniqueTag */
        foreach ($uniqueTags as $uniqueTag) {
            $uniqueColumns = $uniqueTag->getElementsByTagName('unique-column');
            $constraintName = $uniqueTag->getAttribute('name');

            $tmpUniqueColumn = [];
            /** @var DOMElement $uniqueColumn */
            foreach ($uniqueColumns as $uniqueColumn) {
                $tmpUniqueColumn[] = $uniqueColumn->getAttribute('name');
            }

            if (empty(trim($constraintName))) {
                $constraintName = sprintf(
                    '%s_%s_%s',
                    $table->getAttribute('name'),
                    implode('_', $tmpUniqueColumn),
                    'unique'
                );
            }

            $schemaTable->addConstraint(
                $constraintName,
                [
                    'type' => 'unique',
                    'columns' => $tmpUniqueColumn
                ]
            );
        }
    }

    /**
     * Prepare primary constraint
     *
     * @param DOMElement $table
     * @param DOMXPath   $xpath
     * @param Table      $schemaTable
     */
    protected function preparePrimaryConstraint(DOMElement $table, DOMXPath $xpath, Table $schemaTable)
    {
        $primaryTags = $xpath->query(sprintf('/database/table[@name="%s"]/primary', $table->getAttribute('name')));

        /** @var DOMElement $primaryTag */
        foreach ($primaryTags as $primaryTag) {
            $primaryColumns = $primaryTag->getElementsByTagName('primary-column');
            $constraintName = $primaryTag->getAttribute('name');

            $tmpPrimaryColumn = [];
            /** @var DOMElement $primaryColumn */
            foreach ($primaryColumns as $primaryColumn) {
                $tmpPrimaryColumn[] = $primaryColumn->getAttribute('name');
            }

            if (empty(trim($constraintName))) {
                $constraintName = sprintf(
                    '%s_%s_%s',
                    $table->getAttribute('name'),
                    implode('_', $tmpPrimaryColumn),
                    'primary'
                );
            }

            $schemaTable->addConstraint(
                $constraintName,
                [
                    'type' => 'primary',
                    'columns' => $tmpPrimaryColumn
                ]
            );
        }
    }

    /**
     * Prepare index
     *
     * @param DOMElement $table
     * @param DOMXPath   $xpath
     * @param Table      $schemaTable
     */
    protected function prepareIndex(DOMElement $table, DOMXPath $xpath, Table $schemaTable)
    {
        $indexes = $xpath->query(sprintf('/database/table[@name="%s"]/index', $table->getAttribute('name')));

        /** @var DOMElement $index */
        foreach ($indexes as $index) {
            $indexColumns = $index->getElementsByTagName('index-column');
            $indexName = $index->getAttribute('name');
            $indexType = $index->getAttribute('type');

            $tmpIndexColumns = [];
            /** @var DOMElement $indexColumn */
            foreach ($indexColumns as $indexColumn) {
                $tmpIndexColumns[] = $indexColumn->getAttribute('name');
            }

            if (empty(trim($indexType))) {
                $indexType = 'index';
            }

            if (empty(trim($indexName))) {
                $indexName = sprintf(
                    '%s_%s_%s',
                    $table->getAttribute('name'),
                    implode('_', $tmpIndexColumns),
                    $indexType
                );
            }

            $schemaTable->addIndex(
                $indexName,
                [
                    'type' => $indexType,
                    'columns' => $tmpIndexColumns
                ]
            );
        }
    }
}
