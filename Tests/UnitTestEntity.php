<?php
/**
 * сущность c тестовым набором полей
 */
namespace Acme\DemoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class UnitTestEntity
{
    /**
     * переменная нужна т.к. в OrientDB у каждой строки должен быть rid - уникальный ИД
     * @ORM\Id
     * @ORM\Column(type="string", name="@rid")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $rid = 0;

    /**
     * @ORM\Column(type="string", length=255, options={"default":""})
     */
    private $email = '';

    /**
     * @ORM\Column(type="integer", options={"default":0})
     */
    private $time_created = 0;

    /**
     * @param mixed $time_created
     */
    public function setTimeCreated($time_created)
    {
        $this->time_created = $time_created;
    }

    /**
     * @return mixed
     */
    public function getTimeCreated()
    {
        return $this->time_created;
    }


    /**
     * @return mixed
     */
    public function getRid()
    {
        return (string)$this->rid;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = (string)$email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return (string)$this->email;
    }
}