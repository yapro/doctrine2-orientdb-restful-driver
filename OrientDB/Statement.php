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

use Doctrine\DBAL\Driver\Statement AS StatementInterface;
use PDO;


/**
 * реализация стандартных Doctrine-методов
 * по примеру /vendor/doctrine/dbal/lib/Doctrine/DBAL/Driver/Mysqli/MysqliStatement.php
 * @author Lebnik
 */
class Statement implements \IteratorAggregate, StatementInterface
{
    protected static $_paramTypeMap = array(
        PDO::PARAM_STR => 's',
        PDO::PARAM_BOOL => 'i',
        PDO::PARAM_NULL => 's',
        PDO::PARAM_INT => 'i',
        PDO::PARAM_LOB => 's' // TODO Support LOB bigger then max package size.
    );

    /**
     * @var $connection \OrientDB\Connection
     */
    protected $connection;

    /**
     * @var null|false|array
     */
    protected $_columnNames;

    /**
     * @var null|array
     */
    protected $_rowBindedValues;

    /**
     * Содержит параметры и типы данных ($_paramTypeMap)
     * @var array
     */
    protected $_bindedValues;

    /**
     * Contains ref values for bindValue()
     * Содержит значения параметров
     *
     * @var array
     */
    protected $_values = array();

    /**
     * вариант представления возвращаемых данных
     * по-умолчанию указывает, что метод, осуществляющий выборку данных, должен возвращать каждую строку результирующего
     * набора в виде массива. Индексация массива производится и по именам столбцов и по их порядковым номерам в
     * результирующей таблице. Нумерация начинается с 0.
     * @var int
     */
    protected $_defaultFetchMode = PDO::FETCH_BOTH;

    /**
     * неподготовленный запрос
     * @var string
     */
    protected $query = '';

    /**
     * имя таблицы
     * @var string
     */
    protected $table = '';

    /**
     * кол-во параметров в запросе
     * @var int
     */
    protected $paramCount = 0;

    /**
     * результат выборки данных
     * @var array
     */
    protected $result = array();

    /**
     * 1) Создает новое заявление (Statement), которое использует указанный дескриптор соединения и SQL-запрос.
     * По сути заполняет Statement-данные, для последующего обращения к ним
     *
     * @param $conn - \OrientDB\Connection
     * @param $query - обязано быть строкой
     */
    public function __construct($conn, $query)
    {
        $this->connection = $conn;

        $e = explode('~', $query);
        if( isset($e[1]) && !empty($e[1]) ){// если запрос составлен как строка вида: имя_таблицы@SQL-запрос
            $this->table = $e[0];// имя таблицы в отношении которой выполняется SQL-запрос
            $this->query = $e[1];
        }else{
            $this->table = '';
            $this->query = $e[0];
        }

        $this->paramCount = count( explode('?', $this->query) ) - 1;

        // собираем пустой массив значений, с пометкой, что все значения являются строковыми
        if ( $this->paramCount ) {
            // Index 0 is types
            // Need to init the string else php think we are trying to access it as a array.
            $bindedValues = array(0 => str_repeat('s', $this->paramCount));
            $null = null;
            for ($i = 1; $i < $this->paramCount; $i++) {
                $bindedValues[] =& $null;
            }
            $this->_bindedValues = $bindedValues;
        }
    }

    /**
     * Привязка переменных к параметрам подготавливаемого запроса
     * @link http://www.php.net/manual/ru/mysqli-stmt.bind-param.php
     * i	соответствующая переменная имеет тип integer
     * d	соответствующая переменная имеет тип double
     * s	соответствующая переменная имеет тип string
     * b	соответствующая переменная является большим двоичным объектом (blob) и будет пересылаться пакетами
     * Возвращает TRUE в случае успешного завершения или FALSE в случае возникновения ошибки.
     *
     * (PHP 5)<br/>
     * Binds variables to a prepared statement as parameters
     * @link http://php.net/manual/en/mysqli-stmt.bind-param.php
     *
     * @param mixed $column
     * @param mixed $variable
     * @param null $type <p>
     * A string that contains one or more characters which specify the types
     * for the corresponding bind variables:
     * <table>
     * Type specification chars
     * <tr valign="top">
     * <td>Character</td>
     * <td>Description</td>
     * </tr>
     * <tr valign="top">
     * <td>i</td>
     * <td>corresponding variable has type integer</td>
     * </tr>
     * <tr valign="top">
     * <td>d</td>
     * <td>corresponding variable has type double</td>
     * </tr>
     * <tr valign="top">
     * <td>s</td>
     * <td>corresponding variable has type string</td>
     * </tr>
     * <tr valign="top">
     * <td>b</td>
     * <td>corresponding variable is a blob and will be sent in packets</td>
     * </tr>
     * </table>
     * </p>
     * @param null $length <p>
     * The number of variables and length of string
     * types must match the parameters in the statement.
     * @return bool  true on success or false on failure.
     * @throws Exception
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$_paramTypeMap[$type])) {
                $type = self::$_paramTypeMap[$type];
            } else {
                throw new Exception("Unkown type: '{$type}'");
            }
        }

        $this->_bindedValues[$column] =& $variable;
        $this->_bindedValues[0][$column - 1] = $type;
        return true;
    }

    /**
     * связываем все ключи и параметры
     */
    function bindParams(array $params)
    {
        for ($i = 1; $i < $this->paramCount; $i++) {

            $variable = $params[$i];

            $typeInfo = gettype($variable);

            // сопостовляем тип данных
            switch($typeInfo){
                case "boolean":
                    $type = PDO::PARAM_BOOL;
                    break;
                case "integer":
                    $type = PDO::PARAM_INT;
                    break;
                case "string":
                case "double":
                    $type = ( mb_strlen($variable) > 128 )? PDO::PARAM_LOB : PDO::PARAM_STR;
                    break;
                default:
                    $type = PDO::PARAM_NULL;
                    $variable = null;
            }

            if( !$this->bindParam($i, $variable, $type) ){
                return false;
            }
        }
        return true;
    }

    /**
     * 2) связывает параметры и значения (биндим)
     * вызывается в \Doctrine\DBAL\Connection::_bindTypedValues
     * @param mixed $param - номер параметра (в списке параметров)
     * @param mixed $value - значение параметра
     * @param null $type - тип данных в значении (один из $_paramTypeMap)
     * @return bool
     * @throws Exception
     */
    public function bindValue($param, $value, $type = null)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$_paramTypeMap[$type])) {
                $type = self::$_paramTypeMap[$type];
            } else {
                throw new Exception("Unknown type: '{$type}'");
            }
        }

        $this->_values[$param] = $value;
        $this->_bindedValues[$param] =& $this->_values[$param];
        $this->_bindedValues[0][$param - 1] = $type;
        return true;
    }

    /**
     * находим имена полей
     * @param array $array
     * @return bool
     */
    private function registerColumnNames(array $array)
    {
        if( !isset($array[0]) || !is_array($array[0]) ){
            return false;
        }
        $this->_columnNames = array();
        foreach($array[0] as $k => $v){
            $this->_columnNames[] = $k;
        }
    }

    /**
     * находим имена полей из SQL-select-запроса и добавляем недостающие в результаты ответа полученные от OrientDB
     * @param string $sql
     * @return bool
     */
    private function addColumnNamesFromSelect($sql = '')
    {
        if( empty($sql) ){
            return ;
        }

        $from = explode(' FROM ', $sql);
        $select = explode(',', $from['0']);
        foreach($select as $str){
            $fieldAlias[] = current(array_reverse(explode(' ', $str)));
        }

        foreach($this->result as &$arr){
            foreach($fieldAlias as $name){
                if( !isset($arr[ $name ]) ){
                    $arr[ $name ] = null;
                    $this->_columnNames[] = $name;
                }
            }
        }
        reset($this->result);
    }

    /**
     * формируем запрос вместе с параметрами
     * @param string $sql
     * @return string
     */
    private function getSQLWithParams($sql = '')
    {
        $command = substr($sql,0,6);

        if( $command === 'SELECT' ){

            // т.к. OrientDB пока не поддерживает алиас таблицы, то придется избавиться от алиасов в запросе
            // details: https://groups.google.com/forum/#!topic/orient-database/NE803NtA0Tw
            $tableName = $tableAlias = array();
            $e = explode(' ', $sql);
            $sqlNew = '';// сформируем запрос заново
            while($a = each($e)){
                $sqlNew .= $a[1].' ';
                if( $a[1] === 'FROM' ){
                    $tableName = each($e);
                    $tableAlias = each($e);
                    $sqlNew .= $tableName[1].' ';
                }
            }
            //$sql = str_replace($tableAlias[1].'.', $tableName[1].'.', $sql);
            $sql = str_replace($tableAlias[1].'.', '', $sqlNew);

            // т.к. OrientDB не умеет работать с OFFSET, реализовываю следующие моменты:
            // details: https://groups.google.com/forum/#!topic/orient-database/Ule0OOKoZDU
            $sql = str_replace('LIMIT 0', '', $sql);// удаляем
            $sql = str_replace('OFFSET 0', '', $sql);// удаляем
            $sql = str_replace(' OFFSET ', ',', $sql);// заменяем
        }

        if( empty($this->_values) ){// если запро без параметров, например выбор всех строк: SELECT name FROM Users
            return $sql;
        }

        $e = explode('?', $sql);
        $sql = '';
        foreach($e as $k => $str){
            $sql .= $str;
            $key = $k + 1;
            if( isset($this->_values[ $key ]) ){// если параметр существует
                if( $this->_bindedValues[0][$k] === 'i' ){// если значение числовое
                    $sql .= $this->_values[ $key ];
                }else{
                    $sql .= "'".str_replace("'", "''", str_replace('\\', '\\\\', $this->_values[ $key ]) )."'";
                }
            }
        }
        return trim($sql);
    }

    /**
     * составляет строку данных об индексе
     * @todo требует доработок т.к. индекс может состоять из нескольких полей
     * @param array $options
     * @return array
     */
    private function getIndex(array $options)
    {
        return array(
            'Table' => $options['Table'],// имя таблицы к которой относится индекс
            'Non_Unique' => (isset($options['Non_Unique'])? $options['Non_Unique'] : 0),// 1 - не уникальный
            'Key_name' => (isset($options['Key_name'])? $options['Key_name'] : 'PRIMARY'),// название индекса
            'Seq_in_index' => '1',// номер элемента в индексе
            'Column_Name' => (isset($options['Column_Name'])? $options['Column_Name'] : '@rid'),// имя поля
            'Collation' => 'A',// тип сопоставления
            'Cardinality' => '5',// кол-во элементов
            'Sub_Part' => 'NULL',
            'Packed' => 'NULL',
            'Null' => '',
            'Index_Type' => 'BTREE',
            'Comment' => ''
        );
    }

    /**
     * 3) выполняет запрос
     * вызывается в \Doctrine\DBAL\Connection::executeQuery
     * @param null $params - параметры запроса
     * @return bool
     * @throws Exception
     */
    public function execute($params = null)
    {
        // биндим параметры
        if (null !== $this->_bindedValues) {
            if (null !== $params) {// если массив значений параметров передан непосредственно в execute()
                if ( ! $this->bindValuesAndParams($params)) {
                    throw new Exception();
                }
            } else {// если значения параметров забиндены предварительно
                if ( !$this->bindParams($this->_bindedValues) ){
                    throw new Exception();
                }
            }
        }

        $this->result = array();

        $sql = $this->getSQLWithParams($this->query);

        if( $sql === 'getListTableColumnsSQL' && !empty($this->table) ){// информация о полях определенной таблицы

            $data = $this->connection->getData('class', '/'.$this->table);
            if( isset($data['properties']) ){
                $this->result = $data['properties'];
                $this->result[] = array('name' => '@rid', 'type' => 'integer');// данное поле есть у каждой таблицы с полями
            }

            return true;

        }elseif( $sql === 'getListTableForeignKeysSQL'){// внешние ключи таблицы

            // обязательно возвращаем пустой массив
            return true;

        }elseif( $sql === 'getListTableIndexesSQL' && !empty($this->table) ){// индексы таблицы

            $this->result[] = $this->getIndex(array('Table' => $this->table));// @rid (PRIMARY KEY)

            // находим индексы всех таблиц
            $data = $this->connection->getData('query', '/sql/'.rawurlencode('select flatten(indexes) from metadata:indexmanager'));

            foreach($data['result'] as $r){
                $e = explode('.', $r['name']);// определяем имя таблицы, к которой относится индекс и название индекса
                if( isset($e[1]) && !empty($e[1]) && !empty($e[0]) && $e[0] === $this->table ){
                    $this->result[] = $this->getIndex(array(
                        'Table' => $this->table,
                        'Column_Name' => $e[1],
                        'Key_name' => $e[1],
                        'Non_Unique' =>( ($r['type']==='UNIQUE')? 0 : 1)
                    ));
                }
            }

            return true;

        }elseif( $sql === 'getListTablesSQL'){// возвратить список базовых таблиц

            $data = $this->connection->getData('database');

            foreach($data['classes'] as $a){
                $this->result[] = $a['name'];
            }

            //$this->registerColumnNames($data['classes']);
            return true;

        }else{// выполнение обычного запроса

            $e = explode(' ', $sql);
            $query = $e[0];
            if( isset($e[1]) && !empty($e[1]) && in_array($query, array('create','alter','drop','INSERT','UPDATE','DELETE') ) ){

                $data = $this->connection->getData('batch', $sql);

                if( in_array($query, array('INSERT','UPDATE','DELETE') ) ){

                    if( is_array($data) ){
                        throw new Exception('is_array($data)');
                    }

                    $this->num_rows = $data;// кол-во затронутых строк

                    // @todo переделать setLastInsertId когда будет фикс на https://github.com/orientechnologies/orientdb/issues/1944
                    $this->connection->setLastInsertId($data);
                }

            }else{// запрос получения данных

                $data = $this->connection->getData('query', '/sql/'.rawurlencode($sql));
                $this->registerColumnNames($data['result']);
                $this->result = $data['result'];

                // @todo следующий код можно удалить когда будет фикс на https://groups.google.com/forum/#!topic/orient-database/hEHXFpum0AA
                if($query === 'SELECT'){
                    $this->addColumnNamesFromSelect($sql);
                }

                return true;

            }
        }

        return true;
    }

    /**
     * Bind a array of values to bound parameters
     * Связывает массив значений обоих параметров
     *
     * @param array $values
     * @return boolean
     */
    private function bindValuesAndParams($values)
    {
        $params = array();
        $types = str_repeat('s', count($values));
        $params[0] = $types;

        foreach ($values as &$v) {
            $params[] =& $v;
        }
        return $this->bindParams($params);
    }

    /**
     * 6) получает строку данных (превращая объект с членами+значениями в массив с ключами+значениями)
     * предположительно возвращает массив номеров полей и значений array( 0 => 'value field a', 1 => 'value field b' )
     * @return boolean|array
     */
    private function _fetch()
    {
        $ret = each($this->result);

        if ($ret) {
            $values = array();
            foreach ($ret[1] as $k => $v) {
                // Mysqli converts them to a scalar type it can fit in.
                $values[] = null === $v ? null : (string)$v;
            }
            return $values;
        }
        return null;
    }

    /**
     * 5) получает строку данных
     * вызывается в \Doctrine\ORM\Internal\Hydration\ObjectHydrator::hydrateAllData
     */
    public function fetch($fetchMode = null)
    {
        $values = $this->_fetch();
        if (null === $values) {
            return null;
        }

        if (false === $values) {
            throw new Exception();
        }

        $fetchMode = $fetchMode ?: $this->_defaultFetchMode;

        switch ($fetchMode) {
            case PDO::FETCH_NUM:
                return $values;

            case PDO::FETCH_ASSOC:
                return array_combine($this->_columnNames, $values);

            case PDO::FETCH_BOTH:
                $ret = array_combine($this->_columnNames, $values);
                $ret += $values;
                return $ret;

            default:
                throw new Exception("Unknown fetch type '{$fetchMode}'");
        }
    }

    /**
     * получить все данные
     */
    public function fetchAll($fetchMode = null)
    {
        return $this->result;
    }

    /**
     * Retrieves only one column of the next row specified by column index. Moves the pointer forward one row, so
     * that consecutive calls will always return the next row.
     * Получает только один столбец следующей строки, заданной параметром индекс столбца. Перемещает указатель вперед
     * на одну строку, так что при последующих вызовах всегда возвращает следующую строку.
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        if (null === $row) {
            return false;
        }
        return $row[$columnIndex];
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the statement handle
     * Получает SQLSTATE, о последней операции
     */
    public function errorCode()
    {
        return $this->errno;
    }

    /**
     * Получает дополнительную информацию об ошибке, о последней операции
     */
    public function errorInfo()
    {
        return $this->error;
    }

    /**
     * 7) Closes the cursor, enabling the statement to be executed again.
     * Освобождает память которая была зарезервированна от результата запроса.
     * Эта функция не возвращает значения после выполнения, поэтому возвращем просто true-статус выполнения метода.
     * вызывается в \Doctrine\ORM\Internal\Hydration\AbstractHydrator::cleanup
     */
    public function closeCursor()
    {
        foreach($this as $key => $v){
            $this->$key = null;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        if (false === $this->_columnNames) {
            return $this->affected_rows;
        }
        return $this->num_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->field_count;
    }

    /**
     * 4) Задает режим выборки (после выполнения запроса)
     * вызывается в \Doctrine\DBAL\Connection::executeQuery
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->_defaultFetchMode = $fetchMode;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();
        return new \ArrayIterator($data);
    }
}
