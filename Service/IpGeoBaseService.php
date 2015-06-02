<?php

namespace Fenrizbes\IpGeoBaseBundle\Service;

use Doctrine\ORM\EntityManager;
use Fenrizbes\IpGeoBaseBundle\Entity\GeoCity;
use Fenrizbes\IpGeoBaseBundle\Entity\GeoIpRange;
use Symfony\Component\HttpFoundation\RequestStack;

class IpGeoBaseService
{
    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $request_stack;
    
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $cache = array();

    /**
     * @var GeoCity
     */
    protected $default_city;

    public function __construct(RequestStack $request_stack, EntityManager $em, array $config)
    {
        $this->em = $em;
        $this->request_stack = $request_stack;
        $this->config        = $config;
    }

    /**
     * Returns info about IP
     *
     * @param null $ip
     * @return mixed
     */
    public function getIpInfo($ip = null)
    {
        if (is_null($ip)) {
            $ip = $this->request_stack->getMasterRequest()->getClientIp();
        }

        if (!isset($this->cache[$ip])) {
            $this->cache[$ip] = $this->findRange($ip);
        }

        return $this->cache[$ip];
    }

    /**
     * Returns city by IP
     *
     * @param null $ip
     * @return mixed
     */
    public function getIpCity($ip = null)
    {
        $range = $this->getIpInfo($ip);

        if (!$range instanceof GeoIpRange || !$range->getGeoCity() instanceof GeoCity) {
            return $this->getDefaultCity();
        }

        return $range->getGeoCity();
    }

    /**
     * Returns IpRange
     *
     * @param $ip
     * @return null|GeoIpRange
     */
    protected function findRange($ip)
    {
        if (!$this->config['enabled']) {
            return null;
        }

        $long = sprintf("%u", ip2long($ip));
        
        $qb = $this->em->getRepository('FenrizbesIpGeoBaseBundle:GeoIpRange')->createQueryBuilder('r');
        $qb->where($qb->expr()->lte("r.begin", $long));
        $qb->andWhere($qb->expr()->gte("r.end", $long));

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Returns default city
     *
     * @return GeoCity|null
     * @throws \RuntimeException
     */
    public function getDefaultCity()
    {
        if (empty($this->config['default_city'])) {
            return null;
        }

        if (is_null($this->default_city)) {
            $this->default_city = $this->em->find('FenrizbesIpGeoBaseBundle:GeoIpRange', $this->config['default_city']);

            if (!$this->default_city instanceof GeoCity) {
                throw new \RuntimeException('The default city is not found');
            }
        }

        return $this->default_city;
    }
}
