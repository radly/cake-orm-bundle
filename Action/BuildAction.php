<?php

namespace CakeOrm\Action;

use App\Action\AppAction;
use Cake\Database\Schema\Table;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use DOMDocument;
use DOMElement;
use DOMXPath;
use League\CLImate\CLImate;
use Rad\Core\Bundles;
use Rad\Utility\Inflection;

/**
 * Build Action
 *
 * @package RadBundle\CakeOrm\Action
 */
class BuildAction extends AppAction
{
    protected $tableRegistryCode = [];
    protected $tablesModel = [];

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

                $this->prepareTableRegistry($table, $bundle);
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
                $climate->lightGray(sprintf('Create SQL file "%s".', $file));
            }

            $climate->info('Dump table registry config ...');
            $this->dumpTableRegistry($climate, $bundle);

            $climate->info('Dump model classes ...');
            $this->dumpModelClasses($climate, $bundle);
            $climate->br(2);
        }
    }

    /**
     * Prepare table registry
     *
     * @param DOMElement $table
     * @param string     $bundle Bundle name
     */
    protected function prepareTableRegistry(DOMElement $table, $bundle)
    {
        if (($tableName = $table->getAttribute('name')) !== '') {
            $tmpTableClass = null;
            if (!empty($tableClass = $table->getAttribute('tableClass'))) {
                $tmpTableClass = "'" . $tableClass . "'";
            }

            $tmpEntityClass = null;
            if (!empty($entityClass = $table->getAttribute('entityClass'))) {
                $tmpEntityClass = "'" . $entityClass . "'";
            }

            $tmpAlias = null;
            if (!empty($alias = $table->getAttribute('alias'))) {
                $tmpAlias = "'" . $alias . "'";
            }

            $code = <<<PHP
Cake\ORM\TableRegistry::config(
    '$bundle.Categories',
    [
        'table' => '$tableName',
        'alias' => $tmpAlias,
        'className' => $tmpTableClass,
        'entityClass' => $tmpEntityClass,
    ]
);
PHP;

            $this->tablesModel[$bundle][] = [
                'tableClass' => $tableClass,
                'entityClass' => $entityClass,
                'alias' => $alias
            ];
            $this->tableRegistryCode[$bundle][] = $code;
        }
    }

    /**
     * Dump table registry
     *
     * @param CLImate $climate
     * @param string  $bundle
     *
     * @throws \Rad\Core\Exception\MissingBundleException
     */
    protected function dumpTableRegistry(CLImate $climate, $bundle)
    {
        $mapDirPath = Bundles::getPath($bundle) . DS . 'Domain' . DS . 'map';
        $tableRegistryConfigFile = $mapDirPath . DS . 'table_registry_config.php';

        if (!is_dir($mapDirPath)) {
            mkdir($mapDirPath, 0777, true);
        }

        if (is_array($this->tableRegistryCode[$bundle])) {
            file_put_contents(
                $tableRegistryConfigFile,
                '<?php' . "\n\n" . implode("\n\n", $this->tableRegistryCode[$bundle])
            );
        }
    }

    /**
     * Dump model classes
     *
     * @param CLImate $climate
     * @param string  $bundle
     */
    protected function dumpModelClasses(CLImate $climate, $bundle)
    {
        if (is_array($this->tablesModel[$bundle])) {
            foreach ($this->tablesModel[$bundle] as $tableSpec) {
                $alias = $tableSpec['alias'];
                if ($tableSpec['tableClass']) {
                    $tableClassPath = SRC_DIR . DS . str_replace('\\', '/', $tableSpec['tableClass']) . '.php';
                    $tableClassDir = dirname($tableClassPath);
                    $tableClassName = trim(
                        substr($tableSpec['tableClass'], strrpos($tableSpec['tableClass'], '\\')),
                        '\\'
                    );
                    $tableClassNamespace = trim(
                        substr($tableSpec['tableClass'], 0, strrpos($tableSpec['tableClass'], '\\')),
                        '\\'
                    );

                    if (!is_file($tableClassPath)) {
                        if (!is_dir($tableClassDir)) {
                            mkdir($tableClassDir, 0777, true);
                        }

                        ob_start();
                        echo '<?php';
                        include Bundles::getPath('CakeOrm') . '/Resource/config/table_template.php';
                        $content = ob_get_contents();
                        ob_end_clean();

                        file_put_contents($tableClassPath, $content);
                        $climate->lightGray(sprintf('Create table class "%s".', $tableSpec['tableClass']));
                    } else {
                        $climate->lightMagenta(sprintf('Table class "%s" exists.', $tableSpec['tableClass']));
                    }
                }

                //if ($tableSpec['entityClass']) {
                //    $entityClassPath = SRC_DIR . DS . str_replace('\\', '/', $tableSpec['entityClass']);
                //    $entityClassName = trim(
                //        substr($tableSpec['entityClass'], strrpos($tableSpec['entityClass'], '\\')),
                //        '\\'
                //    );
                //}
            }
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
