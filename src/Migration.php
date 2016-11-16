<?php
namespace demmonico\migration;

use demmonico\helpers\DateTimeHelper;
use yii\db\Migration as BaseMigration;


/**
 * Class Migration provide more flexibility usage interface then basic Yii2 class, so can be used instead of it.
 * It is backward compatible with basic migration class.
 * @author: dep
 * @date 16.11.2016
 * @package demmonico\migration
 */
class Migration extends BaseMigration
{
    const COLUMN_ID             = 'id';
    const COLUMN_NAME           = 'name';
    const COLUMN_CREATED        = 'created';
    const COLUMN_UPDATED        = 'updated';
    const COLUMN_STATUS         = 'status';
    const COLUMN_STATUS_DATE    = 'status_date';
    const COLUMN_ISACTIVE       = 'is_active';
    const COLUMN_ORDER          = 'order';

    /**
     * Table name
     * @var string
     */
    protected $tableName = '';      // required
    /**
     * @var string|null
     */
    protected $tableOptions = null;
    /**
     * Array of table columns
     * @var array
     */
    protected $columns = [];        // required
    /**
     * Array of table indexes
     * @var array
     * @example   ['column1', 'column2']   or   ['column1', 'column2'=>true] (for create unique index on 'column2')
     */
    protected $indexKeys = [];
    /**
     * Array of table foreign keys
     * @var array
     * @example
     * ['column' => 'tableName']
     * or
     * ['column' => [
     *      'refTable' => 'tableName',
     *      'refColumn'=>'columnName',  (additional, id default)
     *      'delete'   => ...,          (additional, CASCADE default)
     *      'update'   => ...           (additional, CASCADE default)
     * ]]
     */
    protected $foreignKeys = [];


    /**
     * Array of column names (with default data if need it) to be inserted to new table
     * @var array
     * @use   ['column1', 'column2']   or   ['column1', 'column2'=>'default_value']
     */
    protected $insertColumns = [];
    /**
     * Array of row data to be inserted to new table (in order considered with $defaultDataColumns)
     * @var array
     * @example   [ ['row1-1', 'row1-2'], ['row2-1', 'column2'=>'custom_value'] ]
     */
    protected $insertRows = [];

    /**
     * Whether append created and updated columns automatically
     * @var bool
     */
    protected $isAutoAppendDateTime = true;
    /**
     * Default column name, which used when inserts to ordinary tables with single-column insertion data
     * @var string
     */
    protected $insertSingleColumnName = self::COLUMN_NAME;
    /**
     * Default name of created datetime column, which value will be filled automatically
     * @var string
     */
    protected $insertCreatedColumnName = self::COLUMN_CREATED;
    /**
     * Default name of updated datetime column, which value will be filled automatically
     * @var string
     */
    protected $insertUpdatedColumnName = self::COLUMN_UPDATED;
    /**
     * Default name of status updated datetime column, which value will be filled automatically
     * @var string
     */
    protected $insertStatusUpdatedColumnName = self::COLUMN_STATUS_DATE;



    public function getTableOptions()
    {
        if (is_null($this->tableOptions) && $this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $this->tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB AUTO_INCREMENT=1';
        }
        return $this->tableOptions;
    }

    public function transaction($callback)
    {
        $transaction = $this->db->beginTransaction();
        try {
            $callback();
            $transaction->commit();
            echo "    > Transaction with $this->tableName was processed \n";
            return true;
        } catch (\Exception $e) {
            // delete invalid table if crashed after create
            $tables = $this->db->schema->getTableNames();
            if (in_array($this->tableName, $tables))
                $this->dropTableExists($this->tableName);         // TODO-dep Test error when second foreign key failed ???????????????????
            $transaction->rollBack();
            echo "    > error: $e \n";
            return false;
        }
    }


    public function up()
    {
        $this->checkRequiredProperty(['tableName', 'columns']);
        // append created and updated column if need it
        if ($this->isAutoAppendDateTime){
            if ( $this->insertCreatedColumnName && !isset( $this->columns[ $this->insertCreatedColumnName ] ) ){
                $this->columns[ $this->insertCreatedColumnName ] = 'DATETIME NOT NULL';
                $this->indexKeys[] = $this->insertCreatedColumnName;
                if (!empty($this->insertColumns))
                    $this->insertColumns[] = $this->insertCreatedColumnName;
            }
            if ( $this->insertUpdatedColumnName && !isset( $this->columns[ $this->insertUpdatedColumnName ] ) ){
                $this->columns[ $this->insertUpdatedColumnName ] = 'DATETIME DEFAULT NULL';
                $this->indexKeys[] = $this->insertUpdatedColumnName;
                if (!empty($this->insertColumns))
                    $this->insertColumns[] = $this->insertUpdatedColumnName;
            }
        }
        // create transaction
        $r = $this->transaction(function(){
            $this->createTableExists($this->tableName, $this->columns, $this->getTableOptions());
            // add indexes
            if (!empty($this->indexKeys)) foreach($this->indexKeys as $k=>$v){
                if (!is_bool($v)){
                    $this->createIndex(null, $this->tableName, $v);
                } else {
                    $this->createIndex(null, $this->tableName, $k, $v);    // for create unique index
                }
            }
            // add foreign keys
            if (!empty($this->foreignKeys)) foreach($this->foreignKeys as $k=>$v){
                // add index to foreign key field if need it
                if (!in_array($k, $this->indexKeys))
                    $this->createIndex(null, $this->tableName, $k);
                // add foreign key
                if (is_array($v))
                    $this->addForeignKey(null, $this->tableName, $k, $v['refTable'],
                                         isset($v['refColumn'])?$v['refColumn']:'id',
                                         isset($v['delete'])?$v['delete']:'CASCADE',
                                         isset($v['update'])?$v['update']:'CASCADE');
                else
                    $this->addForeignKey(null, $this->tableName, $k, $v, 'id', 'CASCADE', 'CASCADE');
            }
        });
        // insert default data if need it
        if ($r)
            $this->insertDefaultData();
        return $r;
    }

    public function createTableExists( $table, $columns, $options = null, $ifNotExists=true )
    {
        echo "    > create table $table ...";
        $time = microtime(true);

        $command = $this->db->createCommand()->createTable($table, $columns, $options);
        if ($ifNotExists)
            $command->setSql( $this->addTableExists( $command->getSql() ) );
        $command->execute();

        echo ' done (time: ' . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    public function down()
    {
        $this->checkRequiredProperty(['tableName']);
        return $this->transaction(function(){
            // drop foreign keys
            if (!empty($this->foreignKeys)) foreach($this->foreignKeys as $k=>$v){
                $this->dropForeignKeyEx(null, $this->tableName, $k);
            }
            // drop table
            $this->dropTableExists($this->tableName);
        });
    }

    public function dropTableExists($table, $ifExists=true)
    {
        echo "    > drop table $table ...";
        $time = microtime(true);

        $command = $this->db->createCommand()->dropTable($table);
        if ($ifExists)
            $command->setSql( $this->addTableExists( $command->getSql() ) );
        $command->execute();

        echo ' done (time: ' . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }


    public function createIndex($name, $table, $columns, $unique = false)
    {
        if(null === $name){
            $altColumns = $columns;
            if(is_array($altColumns)){
                $altColumns = implode('-',$altColumns);
            }
            $name = 'idx-'.trim($table,'{}%').'-'.$altColumns;
        }
        parent::createIndex($name, $table, $columns, $unique);
    }

    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = 'CASCADE', $update = 'CASCADE')
    {
        $name = $this->buildForeignKey($name,$table,$columns);
        parent::addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }

    public function dropForeignKeyEx($name, $table, $columns)
    {
        $name = $this->buildForeignKey($name, $table, $columns);
        parent::dropForeignKey($name, $table);
    }

    public function insertDefaultData($isDropTableOnException=true)
    {
        if (!empty($this->insertRows) && !empty($this->insertColumns)){

            // get columns
            $columns = $const = [];
            foreach($this->insertColumns as $k=> $v)
                if (is_integer($k) && is_string($v)){
                    $columns[] = $v;
                } elseif (is_string($k) && !is_array($v) && !is_object($v)){
                    $columns[] = $k;
                    $const[$k] = $v;
                }

            // get rows
            $rows = [];
            $now = DateTimeHelper::utc2str();
            foreach($this->insertRows as $i){
                $r = [];
                foreach($columns as $col){

                    // check for direct defined value in single row
                    if (array_key_exists($col, $i)){
                        $r[] = $i[$col];

                    // check for common default value
                    } elseif (array_key_exists($col, $const)){
                        $r[] = $const[$col];

                    // check for created or updated datetime value
                    } elseif ($col === $this->insertCreatedColumnName || $col === $this->insertUpdatedColumnName || $col === $this->insertStatusUpdatedColumnName){
                        $r[] = $now;

                    // fill from array
                    } else {
                        $r[] = array_shift($i);
                    }
                }
                if (!empty($r))
                    $rows[] = $r;
            }

            // insertion
            if (!empty($columns) && !empty($rows)){
                echo "    > insert data ...\n";
                $time = microtime(true);
                try{
                    $this->batchInsert($this->tableName, $columns, $rows);
                    echo "    > insertion done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
                } catch (\Exception $e){
                    echo "    > insertion error: $e \n";
                    if ($isDropTableOnException)
                        $this->dropTableExists($this->tableName);
                    throw $e;
                }

            }
        }
    }



    protected function buildForeignKey($name, $table, $columns)
    {
        if(null === $name){
            $altColumns = $columns;
            if(is_array($altColumns)){
                $altColumns = implode('-',$altColumns);
            }
            $name = 'fk-'.trim($table,'{}%').'-'.$altColumns;
        }
        return $name;
    }

    protected function checkRequiredProperty(array $properties)
    {
        foreach ($properties as $i){
            if (!isset($this->{$i}) || empty($this->{$i})){
                echo "    > Error: Invalid property $i \n";
                die;
            }
        }
    }

    protected function addTableExists($sql)
    {
        $sql = strtr($sql, ['CREATE TABLE ' => 'CREATE TABLE IF NOT EXISTS ', 'DROP TABLE ' => 'DROP TABLE IF EXISTS ']);
        return $sql;
    }

}