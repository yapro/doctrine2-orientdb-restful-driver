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

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;

/**
 * класс подключения к базе данных
 *
 * DBAL (Data Base Abstraction Layer) предоставляет объектно-ориентированный доступ к базе данных, абстрагируясь от 
 * деталей конкретной реализации базы данных.
 *
 * Statement - созданный, но еще не выполненный объект запроса (с указанными данными запроса).
 * 
 * @author Lebnik
 */
class Connection implements ConnectionInterface
{
    /**
     * @var \curl_init
     */
    private $_conn;

    private $address = '';

    private $dataBase = '';

    private $lastInsertId = 0;

    /**
     * 1) проверяет переменные подключения и подключается к OrientDB
     * @param array $params
     * @param $username
     * @param $password
     * @param array $driverOptions
     */
    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $host = ( isset($params['host']) && !empty($params['host']) ) ? $params['host'] : 'localhost';
        $port = ( isset($params['port']) && !empty($params['port']) ) ? $params['port'] : '2480';

        $this->address = 'http://'.$host.':'.$port;
        $this->dataBase = $params['dbname'];

        if ( !isset($this->dataBase) || empty($this->dataBase) ) {
            throw new Exception();
        }

        $this->_conn = curl_init();

        curl_setopt($this->_conn, CURLOPT_URL, $this->getUrl('connect'));

        //вразумительный браузер
        curl_setopt($this->_conn, CURLOPT_USERAGENT, 'Mozilla/4.0');

        // говорим что это basic, хотя и без этого работает с моей страницей
        curl_setopt($this->_conn, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($this->_conn, CURLOPT_USERPWD, $username.':'.$password);

        curl_setopt($this->_conn, CURLOPT_NOBODY, true);

        // посылаем запрос и получаем ответ
        if( !curl_exec($this->_conn) ){
            throw new Exception();
        }

        $info = curl_getinfo($this->_conn);

        // проверяем успешность ответа
        if( $info['http_code'] !== 204 ){
            throw new Exception( print_r($info,1) );
        }

        curl_setopt($this->_conn, CURLOPT_NOBODY, false);

    }

    public function getUrl($action = '', $additional = '')
    {
        if( empty($action) ){
            throw new Exception();
        }
        return $this->address.'/'.$action.'/'.$this->dataBase.$additional;
    }

    /**
     * Retrieve mysqli native resource handle.
     * Получить mysqli родной дескриптор ресурса.
     *
     * Could be used if part of your application is not using DBAL
     * Может использоваться, если часть вашего приложения, не используя DBAL
     *
     * @return \curl_init
     */
    public function getWrappedResourceHandle()
    {
        return $this->_conn;
    }

    /**
     * 2) Создает еще не выполненный Doctrine-Statement.
     * вызывается в \Doctrine\DBAL\Connection::executeQuery
     */
    public function prepare($prepareString)
    {
        return new Statement($this, $prepareString);
    }

    /**
     * подготавливает и выполняет запрос
     * к примеру query('SELECT a FROM b')
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    /**
     * квотирует входящие данные
     */
    public function quote($value, $type=\PDO::PARAM_STR)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = str_replace("'", "''", $value);
        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    /**
     * выполняет Statement
     *
     * срабатывает например когда в консоле выполняешь: app/console doctrine:query:sql "drop class UnitTest"
     */
    public function exec($statement)
    {
        return $this->getData('batch', $statement);
    }

    /**
     * с помощь curl выполняет REST-запрос в базу данных
     * @param string $action - основная команда
     * @param string $additional - дополнительная команда ИЛИ SQL-запрос, который не возвращает данные из таблицы (create,alter,drop)
     * @return mixed
     * @throws Exception
     */
    public function getData($action = '', $additional = '')
    {
        if( empty($action) ){
            throw new Exception();
        }

        if( $action === 'batch' && $additional ){
            $arr = array(
                'type' => 'cmd',
                'language' => 'sql',
                'command' => $additional
            );
            $content = '{"transaction":false,"operations":['.json_encode($arr).']}';
            curl_setopt($this->_conn, CURLOPT_POSTFIELDS, $content);
            $additional = '';
        }

        curl_setopt($this->_conn, CURLOPT_POST, ( ( $action === 'batch' )? 1 : 0));

        curl_setopt($this->_conn, CURLOPT_URL, $this->getUrl($action,$additional));
        // нужно чтобы вместо результата успешности запроса, получить содержимое запроса
        curl_setopt($this->_conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_conn, CURLOPT_NOBODY, false);
        // посылаем запрос и получаем ответ
        $json = curl_exec($this->_conn);

        if( !$json ){
            throw new Exception();
        }

        $info = curl_getinfo($this->_conn);

        // проверяем успешность ответа
        if( $info['http_code'] !== 200 ){
            throw new Exception( $json."\n".print_r($info,1) );
        }

        if( $action === 'batch' && $additional ){
            return $json;
        }

        $json_decode = json_decode($json, true);

        if ( !$json_decode ) {
            throw new Exception($json);
        }

        return $json_decode;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        //return $this->_conn->insert_id;
        return $this->lastInsertId;
    }

    /**
     * @param int $lastInsertId
     */
    public function setLastInsertId($lastInsertId)
    {
        $this->lastInsertId = $lastInsertId;
    }

    /**
     * начинает транзакцию
     */
    public function beginTransaction()
    {
        // пока что сделаю без поддержки транзакций $this->_conn->query('START TRANSACTION');
        return true;
    }

    /**
     * коммитит транзакцию
     */
    public function commit()
    {
        // пока что сделаю без поддержки return $this->_conn->commit();
        return true;
    }

    /**
     * откатывает транзакцию
     */
    public function rollBack()
    {
        // пока что сделаю без откатывания транзакций return $this->_conn->rollback();
        return true;
    }

    /**
     * возвращает код ошибки
     */
    public function errorCode()
    {
        // пока что сделаю без правильной реализации метода
        //return $this->_conn->errno;
        return 7777777;
    }

    /**
     * возвращает подробное описание ошибки
     */
    public function errorInfo()
    {
        // пока что сделаю без правильной реализации метода
        //return $this->_conn->error;
        return 'errorWhenSendRequestToOrientDB';
    }
}
