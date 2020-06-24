<?php
namespace App\Entity;

class Access
{
    /** @var integer */
    private $id;

    /** @var string */
    private $cookieID;

    /** @var \DateTime */
    private $timestamp;

    /** @var string */
    private $route;

    /** @var string */
    private $controller;

    /** @var string */
    private $requestString;

    /** @var string */
    private $requestMethod;

    /** @var string */
    private $ip;

    /** @var string */
    private $userAgent;

    /** @var string */
    private $filename;

    /** @var string */
    private $referer;

    /** @var User */
    private $user;

    public function __construct()
    {
        $this->setTimestamp(new \DateTime());
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $cookieID
     * @return Access
     */
    public function setCookieID($cookieID)
    {
        $this->cookieID = $cookieID;

        return $this;
    }

    /**
     * @return string
     */
    public function getCookieID()
    {
        return $this->cookieID;
    }

    /**
     * @param \DateTime $timestamp
     * @return Access
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param string $route
     * @return Access
     */
    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param string $controller
     * @return Access
     */
    public function setController($controller)
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param string $requestString
     * @return Access
     */
    public function setRequestString($requestString)
    {
        $this->requestString = $requestString;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestString()
    {
        return $this->requestString;
    }

    /**
     * @param string $requestMethod
     * @return Access
     */
    public function setRequestMethod($requestMethod)
    {
        $this->requestMethod = $requestMethod;

        return $this;
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * @param string $ip
     * @return Access
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param string $userAgent
     * @return Access
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @param string $filename
     * @return Access
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $referer
     * @return Access
     */
    public function setReferer($referer)
    {
        $this->referer = mb_substr($referer, 0, 190);
        return $this;
    }

    /**
     * @return string
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * @param User $user
     * @return Access
     */
    public function setUser(User $user = null)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}

