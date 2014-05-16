<?php
/**
 * create, alter, drop, INSERT, UPDATE, SELECT, DELETE
 */
namespace Yapro\OrientDB\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;// необходим для метода setUp()

class Crud extends WebTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $_em;

    /**
     * адрес корня проекта
     * @var string
     */
    private $root = '';

    /**
     * копия файла тестовой сущности
     * @var string
     */
    private $copyUnitTestEntity = '';

    /**
     * емэйл которым будем проверять добавление/обновление/удаление (как некое уникальное значение сущности)
     * @var string
     */
    private $email = 'test@site.ru';

    /**
     * настройки теста
     */
    protected function setUp()
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $this->_em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->_em->beginTransaction();

        $this->root = dirname( $kernel->getRootDir() );

        $this->copyUnitTestEntity = $this->root.'/src/Acme/DemoBundle/Entity/UnitTestEntity.php';
    }

    /**
     * Rollback changes.
     */
    public function tearDown()
    {
        //$this->_em->rollback();
    }

    public function testExistDemoBundle()
    {
        $this->assertTrue(is_dir($this->root.'/src/Acme/DemoBundle'), 'DemoBundle');
    }

    /**
     * @depends testExistDemoBundle
     */
    public function testExistDemoBundleEntityDir()
    {
        $dir = $this->root.'/src/Acme/DemoBundle/Entity';

        if( !is_dir($dir) ){
            mkdir($dir);
        }

        $this->assertTrue(is_dir($dir), 'wrong access to create dir '.$dir);
    }

    /**
     * @depends testExistDemoBundleEntityDir
     */
    public function testCopyEntity()
    {
        $action = copy(__DIR__.'/UnitTestEntity.php', $this->copyUnitTestEntity);

        $this->assertTrue($action, 'wrong access to copy file');
    }

    /**
     * @depends testCopyEntity
     */
    function testTableCreate()
    {
        // php app/console generate:doctrine:entity --no-interaction --entity=AcmeBlogBundle:UnitTestEntity --fields="title:string(100) body:text" --format=xml
        // http://symfony.com/doc/current/bundles/SensioGeneratorBundle/commands/generate_doctrine_entity.html
        // создаем таблицу
        $last_line = exec('php '.$this->root.'/app/console doctrine:schema:update --force');

        $this->assertEquals('Database schema updated successfully! "4" queries were executed', $last_line, 'problems in database or driver');
    }

    /**
     * @depends testTableCreate
     */
    function testExistRepository()
    {
        $this->assertTrue( class_exists('\Acme\DemoBundle\Entity\UnitTestEntity'), 'Repository don`t exist');

        // получаем репозиторий
        $repository = $this->_em->getRepository('AcmeDemoBundle:UnitTestEntity');

        $action = (is_object($repository) && $repository instanceof \Doctrine\Common\Persistence\ObjectRepository);

        $this->assertTrue($action, 'Repository don`t exist');

        return array(
            'entityManager' => $this->_em,// очень важно передать здесь $this->_em чтобы не использовать копию
            'repository' => $repository
        );
    }

    /**
     * @depends testExistRepository
     * @param array $a
     * @return array
     */
    function testInsert(array $a)
    {
        /** @var $entityManager \Doctrine\ORM\EntityManager */
        $entityManager = $a['entityManager'];

        /** @var $repository \Doctrine\Common\Persistence\ObjectRepository */
        $repository = $a['repository'];

        // добавляем строку в таблицу
        $entity = new \Acme\DemoBundle\Entity\UnitTestEntity();
        $entity->setEmail($this->email);
        $entity->setTimeCreated(1);
        $entity->setArray(array(1=>2));

        $entityManager->persist($entity);
        $entityManager->flush();

        // проверяем добавление
        $id = 0;
        if( $entity = $repository->findOneBy(array('email'=>$this->email)) ){
            $id = $entity->getRid();
        }
        $this->assertTrue( !empty($id), 'problems with add row in database' );

        return $a + array('entity' => $entity);
    }

    /**
     * @depends testInsert
     * @param array $a
     */
    function testSelect(array $a)
    {
        /** @var $entityManager \Doctrine\ORM\EntityManager */
        $entityManager = $a['entityManager'];

        // проверяем существование данных используя поиск без параметров
        // специально не будет пользоваться $entityManager, чтобы сделать реальный запрос в базу данных
        $arr = $this->_em->getRepository('AcmeDemoBundle:UnitTestEntity')->findAll();

        $this->assertTrue( is_array($arr), 'problems with select rows from database' );

        $this->assertTrue( !empty($arr[0]), 'problems with select result' );

        /** @var $entity \Acme\DemoBundle\Entity\UnitTestEntity */
        $entity = $arr[0];

        $this->assertTrue( is_object($entity), 'problems with select result data' );

        $email = $entity->getEmail();

        $this->assertTrue( !empty($email), 'select result is empty Email' );

        $array = $entity->getArray();

        $this->assertTrue( !empty($array), 'select result is empty Array' );
    }

    /**
     * @depends testInsert
     * @param array $a
     */
    function testUpdate(array $a)
    {
        /** @var $entityManager \Doctrine\ORM\EntityManager */
        $entityManager = $a['entityManager'];

        /** @var $entity \Acme\DemoBundle\Entity\UnitTestEntity */
        $entity = $a['entity'];

        // обновляем строку в таблице
        $entity->setTimeCreated(2);
        $entity->setArray(array(2=>3));
        $entityManager->persist($entity);
        $entityManager->flush();

        // проверяем обновление (специально через QueryBuilder и используя $this->_em вместо $entityManager )
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t')
            ->from('AcmeDemoBundle:UnitTestEntity', 't')
            ->where('t.email = :email')
            ->setParameter('email', $this->email);
        $arr = $qb->getQuery()->getResult();

        $array = array(); $time_created = 1;

        if( $arr && isset($arr[0]) && is_object($arr[0]) ){
            /** @var $entity \Acme\DemoBundle\Entity\UnitTestEntity */
            $entity = $arr[0];
            $array = $entity->getArray();
            $time_created = $entity->getTimeCreated();
        }

        $this->assertEquals(2, $time_created, 'problems with update row in database' );

        $this->assertTrue( !empty($array), 'problems with update row in database with Array (empty)' );

        if(isset($array['2'])){
            $this->assertEquals(3, $array['2'], 'problems with update row in database with Array (wrong changed)' );
        }else{
            $this->assertTrue( false, 'problems with update row in database with Array (not updated)' );
        }
    }

    /**
     * удаляем строку в таблице
     * @depends testInsert
     * @param array $a
     */
    function testDelete(array $a)
    {
        /** @var $entityManager \Doctrine\ORM\EntityManager */
        $entityManager = $a['entityManager'];

        /** @var $repository \Doctrine\Common\Persistence\ObjectRepository */
        $repository = $a['repository'];

        // специально через QueryBuilder
        $qb = $entityManager->createQueryBuilder();
        $qb->delete('AcmeDemoBundle:UnitTestEntity', 't')
            ->where('t.email = :email')
            ->setParameter('email', $this->email);
        $arr = $qb->getQuery()->getResult();

        // проверяем удаление
        $id = 0;
        if( $entity = $repository->findOneBy(array('email'=>$this->email)) ){
            $id = $entity->getRid();
        }
        $this->assertEquals(0, $id, 'problems with delete row in database');
    }

    /**
     * @depends testTableCreate
     */
    public function testTableDrop()
    {
        // удаляем ранее скопированный файл
        $this->assertTrue( unlink($this->copyUnitTestEntity) );

        // удаляем таблицу
        $last_line = exec('php '.$this->root.'/app/console doctrine:query:sql "drop class UnitTestEntity"');

        $this->assertEquals('3bca5f39270be06c7bcb142b3a2b1b3b', md5($last_line), 'something wrong in database or driver');
    }
}
