<?php

namespace Fenrizbes\IpGeoBaseBundle\Entity;

/**
 * GeoIpRange
 */
class GeoIpRange
{
    /**
     * @var string
     */
    private $countryCode;

    /**
     * @var string
     */
    private $description;

    /**
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * @var integer
     */
    private $begin;

    /**
     * @var integer
     */
    private $end;

    /**
     * @var \Fenrizbes\IpGeoBaseBundle\Entity\GeoCity
     */
    private $geoCity;


    /**
     * Set countryCode
     *
     * @param string $countryCode
     *
     * @return GeoIpRange
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * Get countryCode
     *
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return GeoIpRange
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return GeoIpRange
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set begin
     *
     * @param integer $begin
     *
     * @return GeoIpRange
     */
    public function setBegin($begin)
    {
        $this->begin = $begin;

        return $this;
    }

    /**
     * Get begin
     *
     * @return integer
     */
    public function getBegin()
    {
        return $this->begin;
    }

    /**
     * Set end
     *
     * @param integer $end
     *
     * @return GeoIpRange
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end
     *
     * @return integer
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Set geoCity
     *
     * @param \Fenrizbes\IpGeoBaseBundle\Entity\GeoCity $geoCity
     *
     * @return GeoIpRange
     */
    public function setGeoCity(\Fenrizbes\IpGeoBaseBundle\Entity\GeoCity $geoCity = null)
    {
        $this->geoCity = $geoCity;

        return $this;
    }

    /**
     * Get geoCity
     *
     * @return \Fenrizbes\IpGeoBaseBundle\Entity\GeoCity
     */
    public function getGeoCity()
    {
        return $this->geoCity;
    }
}
