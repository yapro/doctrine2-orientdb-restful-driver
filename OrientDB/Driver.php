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

use Doctrine\DBAL\Driver as DriverInterface;

class Driver implements DriverInterface
{
    /**
     * 2) осуществляет подключение к базе данных
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        return new Connection($params, $username, $password, $driverOptions);
    }

    /**
     * {@inheritdoc}
     * Имя драйвера
     */
    public function getName()
    {
        return 'OrientDb';
    }

    /**
     * {@inheritdoc}
     * возращает класс работы с полями таблицы (членами класса, возможно с Анотациями к полям)
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new SchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     * 1) возвращает экземляр класса работы с таблицей и ее полями
     */
    public function getDatabasePlatform()
    {
        return new Platform();
    }

    /**
     * {@inheritdoc}
     * Get the name of the database connected to for this driver.
     * Возращает имя базы данных, к которой осущствлено подключение с помощью этого драйвера
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }
}
