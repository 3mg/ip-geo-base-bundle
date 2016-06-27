<?php

namespace Fenrizbes\IpGeoBaseBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Console\Output\OutputInterface;
use \ZipArchive;
use Fenrizbes\IpGeoBaseBundle\Entity\GeoCity;
use Fenrizbes\IpGeoBaseBundle\Entity\GeoIpRange;

class UpdateCommand extends ContainerAwareCommand
{
    /**
     * The path to the database zip file
     */
    const DEFAULT_SOURCE = 'http://ipgeobase.ru/files/db/Main/geo_files.zip';

    /**
     * The names of text files
     */
    const FILE_CITIES    = 'cities.txt';
    const FILE_IP_RANGE  = 'cidr_optim.txt';

    /**
     * The amount of columns in text files
     */
    const FILE_CITIES_COLUMNS   = 6;
    const FILE_IP_RANGE_COLUMNS = 5;

    /**
     * Indexes of columns in text files
     */
    const CITY_COLUMN_INDEX_ID           = 0;
    const CITY_COLUMN_INDEX_NAME         = 1;
    const CITY_COLUMN_INDEX_REGION       = 2;
    const CITY_COLUMN_INDEX_DISTRICT     = 3;
    const CITY_COLUMN_INDEX_LATITUDE     = 4;
    const CITY_COLUMN_INDEX_LONGITUDE    = 5;
    const RANGE_COLUMN_INDEX_BEGIN       = 0;
    const RANGE_COLUMN_INDEX_END         = 1;
    const RANGE_COLUMN_INDEX_DESCRIPTION = 2;
    const RANGE_COLUMN_INDEX_COUNTRY     = 3;
    const RANGE_COLUMN_INDEX_CITY        = 4;
    
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ipgeobase:update')
            ->setDescription('Updates IpGeoBase data')
            ->addOption(
                'source', 'S', InputOption::VALUE_REQUIRED, 'The path to the database zip file', static::DEFAULT_SOURCE
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        
        $source = $input->getOption('source');

        if (filter_var($source, FILTER_VALIDATE_URL) === false) {
            $resource = $this->readFile($source);
        } else {
            $resource = $this->downloadFile($source);
        }

        switch ($this->getMimeType($resource)) {
            case 'application/zip':
                $path = rtrim(sys_get_temp_dir(), '/');

                $this->extract($resource, $path);

                $cities = $this->readFile($path .'/'. static::FILE_CITIES);
                $ranges = $this->readFile($path .'/'. static::FILE_IP_RANGE);

                $this->handleResource($cities, $output);
                $this->handleResource($ranges, $output);
                break;

            case 'text/plain':
                $this->handleResource($resource, $output);
                break;

            default:
                throw new FileException('Unsupported file format');
        }
    }

    /**
     * Downloads a file
     *
     * @param string $url
     * @return resource
     */
    protected function downloadFile($url)
    {
        $fp = tmpfile();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);

        return $fp;
    }

    /**
     * Reads a file
     *
     * @param $filename
     * @return null|resource
     */
    protected function readFile($filename)
    {
        if (!file_exists($filename)) {
            return null;
        }

        return fopen($filename, 'r');
    }

    /**
     * Returns the file's mime-type
     *
     * @param $resource
     * @return mixed
     */
    protected function getMimeType($resource)
    {
        $finfo  = finfo_open(FILEINFO_MIME);
        $string = finfo_file($finfo, $this->getResourceName($resource));
        finfo_close($finfo);

        $pieces = explode(';', $string);

        return $pieces[0];
    }

    /**
     * Returns the file's path by an resource
     *
     * @param $resource
     * @return null|string
     */
    protected function getResourceName($resource)
    {
        if (is_resource($resource) && 'stream' === get_resource_type($resource)) {
            $meta = stream_get_meta_data($resource);

            return $meta['uri'];
        }

        return null;
    }

    /**
     * Extracts a zip archive to the specific folder
     *
     * @param $resource
     * @param $path
     * @return bool
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    protected function extract($resource, $path)
    {
        $zip = new ZipArchive;
        $res = $zip->open($this->getResourceName($resource));

        if ($res !== true) {
            throw new FileException('Cannot extract zip archive, error code: '. $res);
        }

        if ($zip->locateName(static::FILE_CITIES) === false || $zip->locateName(static::FILE_IP_RANGE) === false) {
            throw new FileException('Invalid archive data');
        }

        $zip->extractTo($path, array(
            static::FILE_CITIES,
            static::FILE_IP_RANGE
        ));

        $zip->close();
    }

    /**
     * Calls one of handlers by resource name
     *
     * @param $resource
     * @param OutputInterface $output
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    protected function handleResource($resource, OutputInterface $output)
    {
        if (is_null($resource)) {
            throw new FileException('Invalid file resource');
        }

        $full_name = $this->getResourceName($resource);
        $base_name = basename($full_name);

        if ($base_name != static::FILE_CITIES && $base_name != static::FILE_IP_RANGE) {
            throw new FileException('Invalid file name');
        }

        gc_enable();

        $output->writeln('Processing '. $base_name);
        $progress = new \Symfony\Component\Console\Helper\ProgressBar($output, filesize($full_name));
        $progress->setFormat('debug');
        $progress->start();

        switch ($base_name) {
            case static::FILE_CITIES:
                $this->updateCities($resource, $progress);
                break;

            case static::FILE_IP_RANGE:
                $this->updateIpRange($resource, $progress);
                break;
        }

        $progress->finish();
        $output->writeln($base_name .' handled');

        gc_disable();
    }

    /**
     * Updates cities
     *
     * @param $resource
     * @param $progress
     */
    protected function updateCities($resource, $progress)
    {
        $batchSize = 500;
        $i = 0;
        
        while(($buffer = fgets($resource, 4096)) !== false) {
            $raw_city = mb_split("\t+", mb_convert_encoding(trim($buffer), 'UTF-8', 'CP-1251'));

            if (count($raw_city) != static::FILE_CITIES_COLUMNS) {
                continue;
            }
            
            $i++;

            /** @var GeoCity $city */
            $city = $this->em->find('FenrizbesIpGeoBaseBundle:GeoCity', $raw_city[static::CITY_COLUMN_INDEX_ID]);
            if(!$city instanceof GeoCity) {
                $city = new GeoCity();
                $city->setId($raw_city[static::CITY_COLUMN_INDEX_ID]);
            }

            $city->setName($raw_city[static::CITY_COLUMN_INDEX_NAME]);
            $city->setRegion($raw_city[static::CITY_COLUMN_INDEX_REGION]);
            $city->setDistrict($raw_city[static::CITY_COLUMN_INDEX_DISTRICT]);
            $city->setLatitude($raw_city[static::CITY_COLUMN_INDEX_LATITUDE]);
            $city->setLongitude($raw_city[static::CITY_COLUMN_INDEX_LONGITUDE]);
            
            $this->em->persist($city);
            
            $metadata = $this->em->getClassMetaData(get_class($city));
            $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $this->em->flush();
            
            if($i % $batchSize == 0) {
                $this->em->flush();
                $this->em->clear();
                //$this->em->clear('Fenrizbes\IpGeoBaseBundle\Entity\GeoCity');
            }

            $progress->advance(mb_strlen($buffer));

            //$city    = null;
            $rawCity = null;
            $buffer  = null;

            //unset($city);
            unset($rawCity);
            unset($buffer);
        }
        
        $this->em->flush();
        $this->em->clear('Fenrizbes\IpGeoBaseBundle\Entity\GeoCity');
    }

    /**
     * Updates ranges
     *
     * @param $resource
     * @param $progress
     */
    protected function updateIpRange($resource, $progress)
    {
        gc_enable();
        $current_time = new \DateTime();
        
        $batchSize = 500;
        $i = 0;
        
        $progress->setRedrawFrequency(100);
        $this->em->beginTransaction();

        while (($buffer = fgets($resource, 4096)) !== false) {
            $raw_range = mb_split("\t+", trim($buffer));

            if (count($raw_range) != static::FILE_IP_RANGE_COLUMNS) {
                continue;
            }
            
            $i++;
            
            $city_id = $raw_range[static::RANGE_COLUMN_INDEX_CITY];
            
            if (!preg_match('/^\d+$/', $city_id)) {
                $city_id = null;
            }

            $sql = "SELECT * FROM geo_ip_range r WHERE r.begin >= :range_begin AND r.end <= :range_end";
            $query = $this->em->getConnection()->prepare($sql);
            $query->execute(array(
                "range_begin" => $raw_range[static::RANGE_COLUMN_INDEX_BEGIN],
                "range_end" => $raw_range[static::RANGE_COLUMN_INDEX_END],
            ));
            $range = $query->fetch();

            if($range===false){
                $sql = "INSERT INTO geo_ip_range 
                        (begin, end, geo_city_id, country_code, description, updated_at) 
                        VALUES 
                        (:begin, :end, :city_id, :country_code, :description, :updated_at)";
                $range_begin = $raw_range[static::RANGE_COLUMN_INDEX_BEGIN];
                $range_end = $raw_range[static::RANGE_COLUMN_INDEX_END];
            } else {
                $sql = "UPDATE geo_ip_range SET
                        geo_city_id=:city_id, country_code=:country_code,  
                        description=:description,  updated_at=:updated_at
                        WHERE begin=:begin AND end=:end";
                $range_begin = $range['begin'];
                $range_end = $range['end'];
            }

            $query = $this->em->getConnection()->prepare($sql);
            $query->execute(array(
                "begin" => $range_begin,
                "end" => $range_end,
                "city_id" => $city_id,
                "country_code" => $raw_range[static::RANGE_COLUMN_INDEX_COUNTRY],
                "description" => $raw_range[static::RANGE_COLUMN_INDEX_DESCRIPTION],
                "updated_at" => $current_time->format('Y-m-d H:i:s'),
            ));
            

            if($i % $batchSize == 0) {
                if($this->em->getConnection()->isTransactionActive()){
                    $this->em->commit();
                    $this->em->beginTransaction();
                }
                gc_collect_cycles();
            }

            $progress->advance(mb_strlen($buffer));

            $range    = null;
            $rawRange = null;
            $buffer   = null;

            unset($range);
            unset($rawRange);
            unset($buffer);
        }
        
        if($this->em->getConnection()->isTransactionActive()){
            $this->em->commit();
        }

        $qb = $this->em->getRepository('FenrizbesIpGeoBaseBundle:GeoIpRange')->createQueryBuilder('r');
        $qb->delete();
        $qb->where($qb->expr()->lte("r.updatedAt", $current_time));
        $qb->getQuery()->execute();
    }
}
