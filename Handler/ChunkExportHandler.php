<?php
namespace Openview\ExportBundle\Handler;

use MongoClient;
use Openview\ExportBundle\Entity\DataExportJob;


/**
 * Handler to actually export data in chunks
 */
class ChunkExportHandler
{   
    const EXPORT_COLLECTION = 'OpenviewExport';
    const EXPORT_PATH = '../export/';
    
    protected $doctrine;
    protected $doctrineMongo;
    protected $controller;
    protected $mongoConnectionArray;
    protected $mongoParam;
    protected $exportableHandler;
    
    
    
    /**
     * Constructor. 
     * 
     * @param type $controller
     * @param array $mongoConnectionArray Optional if you plan not to connect MongoDB
     */
    public function __construct($controller, $mongoConnectionArray=null) {
        $this->controller = $controller;
        $this->doctrine = $this->controller->getDoctrine();
        $this->doctrineMongo = $this->controller->get('doctrine_mongodb');
        if ($mongoConnectionArray !== null) {
            $this->mongoConnectionArray = $mongoConnectionArray;
            $this->mongoParam = array(
                'server'=>str_replace('mongodb://', '', $this->mongoConnectionArray['server']),
                'db'=>$this->mongoConnectionArray['options']['db'],
                'username'=>$this->mongoConnectionArray['options']['username'],
                'password'=>$this->mongoConnectionArray['options']['password'],
            );
        }
        $this->exportableHandler = null;
    }
    
    
    function getExportableHandler() {
        return $this->exportableHandler;
    }
    function setExportableHandler($exportableHandler) {
        $this->exportableHandler = $exportableHandler;
    }

        
    
    
    /**
     * Returns the currently active job, or NULL if it does not exist
     * 
     * @return Openview\ExportBundle\Entity\DataExportJob
     */
    public function getCurrentJob()
    {
        // last of active jobs
        $em = $this->doctrine->getManager();
        $q = $this->doctrine->getManager()->createQueryBuilder()
            ->select('job')
            ->from('OpenviewExportBundle:DataExportJob', 'job')
            ->where('(job.active=true)')
            ->orderBy('job.createdAt', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getQuery();
        $jobs = $q->getResult();
        
        if ($jobs) {
            return $jobs[0];
        } else {
            return null;
        }
    }
    
    
    
    /**
     * Initializes a job just before starting esport data
     * 
     * @param Openview\ExportBundle\Entity\DataExportJob $job
     */
    public function init(DataExportJob $job)
    {
        if ($job !== null) {
            // svuota la collezione di export
            $exportCollection = $this->getExportCollection();
            $exportCollection->drop();
            
            // update job status
            $job->setActive(true);  // lo è già, ma lo aggiorno per sicurezza
            $job->setStartedAt(new \DateTime());
            $job->setTotItems($this->getTotItems());
            $job->setStatus(DataExportJob::STATUS_EXPORTING);
            
            // persist job
            $em = $this->doctrine->getManager();
            $em->persist($job);
            $em->flush();
        }
    }
    
    
    
    /**
     * Export a data chunk
     * 
     * @param Openview\ExportBundle\Entity\DataExportJob $job
     */
    public function exportChunk(DataExportJob $job)
    {
        $em = $this->doctrine->getManager();
        
        // sets job as PROCESSING while actually exporting data
        $job->setStatus(DataExportJob::STATUS_PROCESSING);
        $em->persist($job);
        $em->flush();
        
        // gets next chunk of items to be exported
        $items = $this->getNextChunk($job);
        // mongodb collection with exported chunk
        $exportCollection = $this->getExportCollection();
        
        // exports data rows
        $iProcessedRows = 0;
        foreach ($items as $item) {
            $row = $this->getRow($item);
            // add record to collection
            $exportCollection->insert($row);
            $iProcessedRows++;
        }
        
        // update job
        $job->setExportedItems($iProcessedRows + $job->getExportedItems());
        // if there's nothing else to export
        if ($job->getExportedItems() >= $job->getTotItems()) {
            $job->setStatus(DataExportJob::STATUS_SAVING);
        } 
        // if there are other records to export
        else {
            // set job again in EXPORTING
            $job->setStatus(DataExportJob::STATUS_EXPORTING);
        }
        
        // persist job
        $em->persist($job);
        $em->flush();
    }
    
    
    
    
    /**
     * Save in a .csv file  the collection with exported data
     * 
     * @param Openview\ExportBundle\Entity\DataExportJob $job
     */
    public function save(DataExportJob $job)
    {
        // open collection to read data do be saved to disc
        $exportCollection = $this->getExportCollection();
        $result = $exportCollection->find();
       
        // open file in write mode
        $filename = ChunkExportHandler::EXPORT_PATH . date('Ymd-His') . '.csv';
        $file = fopen($filename, 'w');
        
        // array with the list of every existing column
        $allFields = $this->getAllFields($result);
        
        // append to file the field names
        $s = '';
        foreach ($allFields as $key=>$field) {
            if (strlen($s) > 0) {
                $s .= ';';
            }
            $s .= $key;
        }
        fwrite($file, $s . PHP_EOL);
        
        // for each record to export
        foreach ($result as $row) {
            $output = array();
            // fill array fields
            foreach ($allFields as $key=>$field) {
                if (array_key_exists($key, $row)) {
                    $output[$key] = $row[$key];
                } else {
                    $output[$key] = null;
                }
            }
            // append row into file
            fwrite($file, implode(';', $output) . PHP_EOL);
        }
        // close file
        fclose($file);
        
        // update job
        $job->setCompletedAt(new \DateTime());
        $job->setActive(false);
        $job->setFilename($filename);
        $job->setStatus(DataExportJob::STATUS_COMPLETE);
        
        // persist job
        $em = $this->doctrine->getManager();
        $em->persist($job);
        $em->flush();
    }
    
    
    
    
    
    /**
     * Returns the # of items available to export
     * @return integer
     */
    protected function getTotItems()
    {
        if ($this->exportableHandler !== null) {
            return $this->exportableHandler->getTotItems();
        } else {
            return 0;
        }
    }
    
    
    /**
     * Returns the next chunk of items to export
     * 
     * @param DataExportJob $job
     * @return array
     */
    protected function getNextChunk(DataExportJob $job)
    {
        $firstItem = $job->getExportedItems();
        $items = $this->exportableHandler->getNextChunk($firstItem);
        
        return $items;
    }
    
    
    
    /**
     * Returns an array that represents the exported row
     * @param $item
     */
    protected function getRow($item)
    {
        $row = $this->exportableHandler->getRow($item);
        
        return $row;
    }
    
    
    
    /**
     * Ritorna la collection di MongoDB in cui si esportano i dati prima del salvataggio su file.
     * 
     * @return MongoCollection
     */
    protected function getExportCollection()
    {
        // crea connessione mongodb
        $connectionString = 'mongodb://' . 
                $this->mongoParam['username'] . ':' . $this->mongoParam['password'] . '@' .
                $this->mongoParam['server'] . '/' . $this->mongoParam['db'];
        $connection = new MongoClient($connectionString);
        // apre la collection
        $collection = $connection->selectCollection($this->mongoParam['db'], ChunkExportHandler::EXPORT_COLLECTION);
        
        return $collection;
    }
    
    
    /**
     * Build field label
     * 
     * @param type $docName
     * @param type $fieldName
     */
    protected function getFieldLabel($fieldNum, $docName, $fieldName)
    {
        $s = substr(strtolower(trim(str_replace(' ', '', $docName))), 0, 12) . '_' . 
                sprintf('%02d', $fieldNum) . '_' .
                substr(strtolower(trim(str_replace(' ', '', $fieldName))), 0, 20);
        $s = str_replace('.', '', $s);
        
        return $s;
    }
    
    
    
    /**
     * Ritorna un array con tutti i campi esistenti nell'esportazione
     * 
     * @param type $result
     * @return array
     */
    protected function getAllFields($result)
    {
        $res = array();
        // per ogni riga
        foreach ($result as $row) {
            // per ogni campo
            foreach ($row as $key=>$field) {
                // se non esiste la chiave nell'array, la crea
                $res[$key] = null;
            }
        }
        
        return $res;
    }

    
}