<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace OrientDB;

use Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\Table;

class Platform extends \Doctrine\DBAL\Platforms\AbstractPlatform
{
    /**
     * {@inheritDoc}
     */
    public function getIdentifierQuoteCharacter()
    {
        return '`';
    }

    /**
     * {@inheritDoc}
     */
    public function getRegexpExpression()
    {
        return 'RLIKE';
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidExpression()
    {
        return 'UUID()';
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'LOCATE(' . $substr . ', ' . $str . ')';
        }

        return 'LOCATE(' . $substr . ', ' . $str . ', '.$startPos.')';
    }

    /**
     * {@inheritDoc}
     */
    public function getConcatExpression()
    {
        $args = func_get_args();
        return 'CONCAT(' . join(', ', (array) $args) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'DATEDIFF(' . $date1 . ', ' . $date2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return 'DATE_ADD(' . $date . ', INTERVAL ' . $days . ' DAY)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return 'DATE_SUB(' . $date . ', INTERVAL ' . $days . ' DAY)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return 'DATE_ADD(' . $date . ', INTERVAL ' . $months . ' MONTH)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return 'DATE_SUB(' . $date . ', INTERVAL ' . $months . ' MONTH)';
    }

    public function getListDatabasesSQL()
    {
        return 'SHOW DATABASES';
    }

    public function getListTableConstraintsSQL($table)
    {
        return 'SHOW INDEX FROM ' . $table;
    }

    /**
     * {@inheritDoc}
     *
     * Two approaches to listing the table indexes. The information_schema is
     * preferred, because it doesn't cause problems with SQL keywords such as "order" or "table".
     *
     * Находит индексы указанной таблицы
     *
     * Два подхода к листингу таблицы индексов. Information_schema является предпочтительным, поскольку не вызывает
     * проблем с SQL ключевыми словами, такими как "order" или "table".
     *
     * @param string $table
     * @param string $currentDatabase
     * @return string example:
     * Table | Non_Unique | Key_name | Seq_in_index | Column_Name | Collation | Cardinality | Sub_Part | Packed | Null | Index_Type	Comment
     * Orgunit | 0 | PRIMARY | 1 | id | A | 5 | NULL | NULL | BTREE
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        return $table.'~getListTableIndexesSQL';

        if ($currentDatabase) {
            return "SELECT TABLE_NAME AS `Table`, NON_UNIQUE AS Non_Unique, INDEX_NAME AS Key_name, ".
                   "SEQ_IN_INDEX AS Seq_in_index, COLUMN_NAME AS Column_Name, COLLATION AS Collation, ".
                   "CARDINALITY AS Cardinality, SUB_PART AS Sub_Part, PACKED AS Packed, " .
                   "NULLABLE AS `Null`, INDEX_TYPE AS Index_Type, COMMENT AS Comment " .
                   "FROM information_schema.STATISTICS WHERE TABLE_NAME = '" . $table . "' AND TABLE_SCHEMA = '" . $currentDatabase . "'";
        }

        return 'SHOW INDEX FROM ' . $table;
    }

    /**
     * просмотр схемы базы данных
     * TABLE_CATALOG | TABLE_SCHEMA | TABLE_NAME | VIEW_DEFINITION | CHECK_OPTION | IS_UPDATABLE | DEFINER | SECURITY_TYPE | CHARACTER_SET_CLIENT | COLLATION_CONNECTION
     * @param string $database
     * @return string
     */
    public function getListViewsSQL($database)
    {
        return 'info';
        //return "SELECT * FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '".$database."'";
    }

    /**
     * запрос, который должен возвращать список первичных ключей
     * @link https://github.com/orientechnologies/orientdb/wiki/Concepts#relationships
     * @param $table - не важно какая таблица, в OrientDB первычным ключем может быть только @RID
     * @param null $database
     * @return string|void
     */
    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return '~getListTableForeignKeysSQL';
    }

    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL($name)
    {
        return 'DROP VIEW '. $name;
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
                : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        if ( ! empty($field['length']) && is_numeric($field['length'])) {
            $length = $field['length'];
            if ($length <= 255) {
                return 'TINYTEXT';
            }

            if ($length <= 65532) {
                return 'TEXT';
            }

            if ($length <= 16777215) {
                return 'MEDIUMTEXT';
            }
        }

        return 'LONGTEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        if (isset($fieldDeclaration['version']) && $fieldDeclaration['version'] == true) {
            return 'TIMESTAMP';
        }

        return 'DATETIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'TINYINT(1)';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the COLLATION
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $collation   name of the collation
     *
     * @return string  DBMS specific SQL code portion needed to set the COLLATION
     *                 of a field declaration.
     */
    public function getCollationFieldDeclaration($collation)
    {
        return 'COLLATE ' . $collation;
    }

    /**
     * {@inheritDoc}
     *
     * MySql prefers "autoincrement" identity columns since sequences can only
     * be emulated with a table.
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * MySql supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsInlineColumnComments()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getShowDatabasesSQL()
    {
        return 'SHOW DATABASES';
    }

    /**
     * запрос получения списка базовых таблиц
     * @return string|void
     */
    public function getListTablesSQL()
    {
        return '~getListTablesSQL';
        // short versiton: return 'classes';// NAME | SUPERCLASS | CLUSTERS | RECORDS
        // sql-query: return 'select expand( classes ) from metadata:schema';
        // mysql: return "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'";
    }

    /**
     * возвращает информацию о полях таблицы
     * @param $table
     * @param null $database
     * @return string|void результат данного SQL-запроса должен возвращать массив с массивами строк, к примеру:
     * NAME | TYPE | LINKED TYPE/CLASS | MANDATORY | READONLY | NOT NULL | MIN | MAX | COLLATE
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        // short variant: 'info class '.$table;
        return $table.'~getListTableColumnsSQL';

        /*
         * следующий запрос возвращает поле "тип" в числовом формате, а нужно в строковом
         * см. https://groups.google.com/forum/#!topic/orient-database/AN6tJANZ0ps
         * return "select expand( properties) from ( select expand( classes ) from metadata:schema ) where name='".$table."'";
         */
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($name)
    {
        return 'CREATE DATABASE ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
    }

    /**
     * {@inheritDoc}
     * запрос на создание таблицы
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $sql = array();
        $sql[] = 'create class '.$tableName;

        if ( !empty($columns) ) {
            foreach ($columns as $name => $info) {
                if($name === '@rid'){
                    continue;
                }
                $sql[] = 'create property '.$tableName.'.'.$name.' '.$info['type']->getName();
            }
        }
        // @todo реализовать индексы, информацию о которых можно найти в $options
        return $sql;
    }

    /**
     * Получаем sql-инструкции для изменения существующей таблицы.
     * Метод возвращает массив sql-инструкций, поскольку в некоторых платформах нужно несколько заявлений.
     * @param TableDiff $diff - объект с данными, которые нужно озменить
     * @return array
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $tableName = $diff->name;
        $columnSql = array();
        $queryParts = array();

        /* переименование таблицы
        if ($diff->newName !== false) {
            $queryParts[] = 'RENAME TO ' . $diff->newName;
        }
        */

        // добавляем поле
        foreach ($diff->addedColumns as $column) {
            // проверяем не удаляется ли поле
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }
            $fieldName = $column->getQuotedName($this);
            if($fieldName === '@rid'){
                continue;
            }
            $sql[] = 'create property '.$tableName.'.'.$fieldName.' '.$column->getType();
            /*
            $columnArray = $column->toArray();
            $columnArray['comment'] = $this->getColumnComment($column);
            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
            */
        }

        // удаляем поле
        foreach ($diff->removedColumns as $column) {
            // проверяем не удаляется ли поле
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }
            $sql[] =  'drop property '.$tableName.'.'.$column->getQuotedName($this);
        }

        /*
        следующие действия решено пока не реализовывать (чтобы иметь возможность выполнять их в консоле)
        // изменяем поле (изменяем атрибуты поля)
        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }
            if( isset($columnDiff->changedProperties) ){
                foreach($columnDiff->changedProperties as $attr){
                    if($attr === 'autoincrement'){// избавиться от changedProperties: autoincrement,unsigned,comment
                        continue;// избавляемся от атрибутов полей, которых нет в OrientDB
                    }
                    $sql[] =  'alter property '.$tableName.'.'.$column->getQuotedName($this).' '.$attr.' true';
                }
            }
            continue;

            // @var $columnDiff \Doctrine\DBAL\Schema\ColumnDiff
            $column = $columnDiff->column;
            $columnArray = $column->toArray();
            $columnArray['comment'] = $this->getColumnComment($column);
            $queryParts[] =  'CHANGE ' . ($columnDiff->oldColumnName) . ' '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }
        */

        // переименовываем поле
        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }
            // требует доработки:
            $sql[] =  'alter property '.$tableName.'.'.$oldColumnName.' NAME '.$column->getName();
            /*
            $columnArray = $column->toArray();
            $columnArray['comment'] = $this->getColumnComment($column);
            $queryParts[] =  'CHANGE ' . $oldColumnName . ' '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
            */
        }
        $tableSql = array();
        /*
        // составляем запрос изменения поля
        if ( ! $this->onSchemaAlterTable($diff, $tableSql)) {
            if (count($queryParts) > 0) {
                $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . implode(", ", $queryParts);
            }
            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }
        */
        // создаем индексы
        foreach ($diff->addedIndexes as $column) {
            /** @var \Doctrine\DBAL\Schema\Index $column */
            if( $column->isUnique() ){
                $sql[] =  'create index '.$tableName.'.'.$column->getName().' UNIQUE';
            }
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        $sql = array();
        $table = $diff->name;

        foreach ($diff->removedIndexes as $remKey => $remIndex) {

            foreach ($diff->addedIndexes as $addKey => $addIndex) {
                if ($remIndex->getColumns() == $addIndex->getColumns()) {

                    $columns = $addIndex->getColumns();
                    $type = '';
                    if ($addIndex->isUnique()) {
                        $type = 'UNIQUE ';
                    }

                    $query = 'ALTER TABLE ' . $table . ' DROP INDEX ' . $remIndex->getName() . ', ';
                    $query .= 'ADD ' . $type . 'INDEX ' . $addIndex->getName();
                    $query .= ' (' . $this->getIndexFieldDeclarationListSQL($columns) . ')';

                    $sql[] = $query;

                    unset($diff->removedIndexes[$remKey]);
                    unset($diff->addedIndexes[$addKey]);

                    break;
                }
            }
        }

        $sql = array_merge($sql, parent::getPreAlterTableIndexForeignKeySQL($diff));

        return $sql;
    }

    /**
     * Добавляет дополнительные флаги для генерации индекса
     */
    protected function getCreateIndexSQLFlags(Index $index)
    {
        $type = '';
        if ($index->isUnique()) {
            $type .= 'UNIQUE ';
        } else if ($index->hasFlag('fulltext')) {
            $type .= 'FULLTEXT ';
        }

        return $type;
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        $autoinc = '';
        if ( ! empty($columnDef['autoincrement'])) {
            $autoinc = ' AUTO_INCREMENT';
        }
        $unsigned = (isset($columnDef['unsigned']) && $columnDef['unsigned']) ? ' UNSIGNED' : '';

        return $unsigned . $autoinc;
    }

    /**
     * {@inheritDoc}
     */
    public function getAdvancedForeignKeyOptionsSQL(\Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey)
    {
        $query = '';
        if ($foreignKey->hasOption('match')) {
            $query .= ' MATCH ' . $foreignKey->getOption('match');
        }
        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);
        return $query;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropIndexSQL($index, $table=null)
    {
        if ($index instanceof Index) {
            $indexName = $index->getQuotedName($this);
        } else if(is_string($index)) {
            $indexName = $index;
        } else {
            throw new \InvalidArgumentException('MysqlPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        } else if(!is_string($table)) {
            throw new \InvalidArgumentException('MysqlPlatform::getDropIndexSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        if ($index instanceof Index && $index->isPrimary()) {
            // mysql primary keys are always named "PRIMARY",
            // so we cannot use them in statements because of them being keyword.
            return $this->getDropPrimaryKeySQL($table);
        }

        return 'DROP INDEX ' . $indexName . ' ON ' . $table;
    }

    /**
     * @param string $table
     *
     * @return string
     */
    protected function getDropPrimaryKeySQL($table)
    {
        return 'ALTER TABLE ' . $table . ' DROP PRIMARY KEY';
    }

    /**
     * {@inheritDoc}
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'mysql';
    }

    /**
     * {@inheritDoc}
     */
    public function getReadLockSQL()
    {
        return 'LOCK IN SHARE MODE';
    }

    /**
     * {@inheritDoc}
     * инициализация маппера типов полей, которые могут быть у таблицы
     * другими словами - сопоставление типов полей базы данных Х с типами полей Doctrine
     * @link https://github.com/orientechnologies/orientdb/wiki/Types
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = $this->getOrientDbDoctrineMapping();
        return true;
    }

    /**
     * содержит массив маппинга возможных полей базы данных
     * @return array|string
     */
    public function getOrientDbDoctrineMapping()
    {
        return array(
            'boolean'       => 'boolean',
            'integer'       => 'integer',// 32-bit signed Integers (-2,147,483,648 - +2,147,483,647)
            'short'       => 'smallint',// Small 16-bit signed integers (-32,768 - +32,767)
            'long'       => 'bigint',// Big 64-bit signed integers (-2 in 63 - +(2 in 63)-1)
            'float'       => 'decimal',// Decimal numbers (2 in -149 - (2-2 in -23)*2 in 127)
            'double'       => 'decimal',// Decimal numbers with high precision
            'date'       => 'datetime',
            'datetime'       => 'datetime',
            'string'       => 'string',// Any string as alphanumeric sequence of chars ( 0-infinity )
            'binary'       => 'blob',
            'embedded'       => 'text',
            'embeddedlist'       => 'string',
            'embeddedset'       => 'string',
            'embeddedmap'       => 'string',
            'link'       => 'string',
            'linklist'       => 'string',
            'linkset'       => 'string',
            'linkmap'       => 'string',
            'byte'       => 'smallint'// Single byte. Useful to store small 8-bit signed integers (-128 - +127)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getVarcharMaxLength()
    {
        return 65535;
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\MySQLKeywords';
    }

    /**
     * {@inheritDoc}
     *
     * MySQL commits a transaction implicitly when DROP TABLE is executed, however not
     * if DROP TEMPORARY TABLE is executed.
     */
    public function getDropTemporaryTableSQL($table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        } else if(!is_string($table)) {
            throw new \InvalidArgumentException('getDropTableSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        return 'DROP TEMPORARY TABLE ' . $table;
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'LONGBLOB';
    }
}
