<?php
namespace Openview\ExportBundle\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class describing a data export job
 * 
 * @ORM\Entity
 * @ORM\Table(name="ov_exportjob",
 *      indexes={
 *          @ORM\Index(name="createdat_idx", columns={"createdAt"}),
 *          @ORM\Index(name="status_idx", columns={"status"}),
 *          @ORM\Index(name="active_idx", columns={"active"})
 *      }
 * )
 */
class DataExportJob {
    /* job status constants */
    const STATUS_EMPTY = 0;             // item just created
    const STATUS_READY = 1;             // item waiting to be processed
    const STATUS_EXPORTING = 2;         // lavoro di esportazione in corso
    const STATUS_PROCESSING = 3;        // correntemente impegnato nell'esecuzione dell'esportazione
    const STATUS_SAVING = 4;            // saving data to file
    const STATUS_COMPLETE = 5;          // export complete
    const STATUS_ABORTED = 6;           // export aborted (by the user)
    const STATUS_FAILED = 7;            // export failed
    const STATUS_DELETED = 8;           // export deleted
    
    
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * Destination file name
     * @ORM\Column(type="string", nullable=true)
     */
    protected $filename;
    /**
     * Job creation datetime
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $createdAt;
    /**
     * Job start datetime
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * Job end datetime
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $completedAt;
    /**
     * Export state (waiting, started, exporting, aborted, ...)
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $status;
    /**
     * If true, an export job is running (used to filter active jobs)
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $active;
    /**
     * User that started the job
     * @ORM\Column(type="string", nullable=true)
     */
    protected $userName;
    /**
     * Tot items to be exported
     * @ORM\Column(type="integer")
     */
    protected $totItems;
    /**
     * Processed items
     * @ORM\Column(type="integer")
     */
    protected $exportedItems;
    
    
    
    public function __construct() {
        $this->filename = '';
        $this->createdAt = new \DateTime();
        $this->startedAt = null;
        $this->completedAt = null;
        $this->status = DataExportJob::STATUS_EMPTY;
        $this->user = '';
        $this->totItems = 0;
        $this->exportedItems = 0;
        $this->active = false;
    }
    
    
    public function getId() {
        return $this->id;
    }

    public function getFilename() {
        return $this->filename;
    }

    public function getCreatedAt() {
        return $this->createdAt;
    }

    public function getStartedAt() {
        return $this->startedAt;
    }

    public function getCompletedAt() {
        return $this->completedAt;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setFilename($filename) {
        $this->filename = $filename;
    }

    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
    }

    public function setStartedAt($startedAt) {
        $this->startedAt = $startedAt;
    }

    public function setCompletedAt($completedAt) {
        $this->completedAt = $completedAt;
    }

    public function setStatus($status) {
        $this->status = $status;
    }
    
    public function setUserName($userName)
    {
        $this->userName = $userName;
        return $this;
    }

    public function getUserName()
    {
        return $this->userName;
    }
    
    public function getTotItems() {
        return $this->totItems;
    }

    public function getExportedItems() {
        return $this->exportedItems;
    }

    public function setTotItems($totItems) {
        $this->totItems = $totItems;
    }

    public function setExportedItems($exportedItems) {
        $this->exportedItems = $exportedItems;
    }
    
    function getActive() {
        return $this->active;
    }

    function setActive($active) {
        $this->active = $active;
    }

    

    
    /**
     * Return status desription
     * @return string
     */
    public function getStatusDescription() {
        switch ($this->status) {
            case DataExportJob::STATUS_EMPTY:
                return 'Empty';
                break;
            case DataExportJob::STATUS_READY:
                return 'Ready';
                break;
            case DataExportJob::STATUS_EXPORTING:
            case DataExportJob::STATUS_PROCESSING:
                return 'Exporting...';
                break;
            case DataExportJob::STATUS_SAVING:
                return 'Saving file...';
                break;
            case DataExportJob::STATUS_COMPLETE:
                return 'Completed';
                break;
            case DataExportJob::STATUS_ABORTED:
                return 'Canceled';
                break;
            case DataExportJob::STATUS_FAILED:
                return 'Failed';
                break;
            case DataExportJob::STATUS_DELETED:
                return 'Deleted';
                break;
            default:
                return 'Unknown';
        }
    }
    
    
    
    /**
     * Array representation of job
     * Used to build JSON responses of APIs
     */
    public function toArray()
    {
        $ret = array();
        $ret['filename'] = $this->filename;
        $ret['createdAt'] = $this->createdAt;
        $ret['startedAt'] = $this->startedAt;
        $ret['completedAt'] = $this->completedAt;
        $ret['status'] = $this->status;
        $ret['userid'] = $this->getUserName();
        $ret['totItems'] = $this->totItems;
        $ret['exportedItems'] = $this->exportedItems;
        $ret['active'] = $this->active;
        $ret['statusDescription'] = $this->getStatusDescription();
        
        return $ret;
    }
    
    

    
}