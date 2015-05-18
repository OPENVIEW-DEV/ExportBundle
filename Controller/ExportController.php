<?php
namespace Openview\ExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Openview\ExportBundle\Handler\ChunkExportHandler;
use Openview\ExportBundle\Entity\DataExportJob;


/**
 * Controller to manage data export
 *
 */
class ExportController extends Controller {



    /**
     * Show export panel and history
     */
    public function indexAction() {
        // build jobs list
        $jobs = $this->getDoctrine()->getManager()->createQueryBuilder()
            ->select('j')
            ->from('OpenviewExportBundle:DataExportJob', 'j')
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()->getResult();
        $jobArray = array();
        foreach ($jobs as $job) {
            $jobItem['id'] = $job->getId();
            if ($job->getCreatedAt() !== null) {
                $jobItem['createdAt'] = $job->getCreatedAt();
            } else {
                $jobItem['createdAt'] = '';
            }
            $jobItem['username'] = '';
            $jobItem['totItems'] = $job->getTotItems();
            if ($job->getStatus() == DataExportJob::STATUS_COMPLETE) {
                $jobItem['exportedItems'] = $job->getExportedItems();
                if (($job->getCreatedAt() !== null) && ($job->getCompletedAt() != null)) {
                    $jobItem['length'] = $job->getCreatedAt()->diff($job->getCompletedAt())->format('%im %ss');
                } else {
                    $jobItem['length'] = '';
                }
                $jobItem['filesize'] = $this->getFilesize(ChunkExportHandler::EXPORT_PATH . $job->getFilename());
                $jobItem['filename'] = basename($job->getFilename());
            } else {
                $jobItem['exportedItems'] = 0;
                $jobItem['length'] = '';
                $jobItem['filesize'] = '';
                $jobItem['filename'] = '';
            }
            $jobItem['statusDescription'] = $job->getStatusDescription();
            $jobArray[] = $jobItem;
        }

        return $this->render('OpenviewExportBundle:Export:index.html.twig', array(
            'jobs'=>$jobArray,
        ));
    }



    /**
     * Download a job's file
     * 
     * @param integer $jobid
     */
    public function downloadAction($jobid)
    {
        // load job
        $job = $this->getDoctrine()->getManager()->getRepository('OpenviewExportBundle:DataExportJob')->find($jobid);
        if ($job) {
            if ($job->getStatus() === DataExportJob::STATUS_COMPLETE) {
                $filename = ChunkExportHandler::EXPORT_PATH . $job->getFilename();
                if (file_exists($filename)) {
                    $content = file_get_contents($filename);
                    $response = new Response();
                    $response->headers->set('Content-Type', 'application/octet-stream');
                    $response->headers->set('Content-Disposition', 'attachment;filename="' . basename($filename));
                    $response->setContent($content);

                    return $response;
                } else {
                    $this->get('session')->getFlashBag()->add('alert', 'Export file not found: ' . basename($filename));
                }
            } else {
                $this->get('session')->getFlashBag()->add('alert', 'Export job completed.');
            }
        } else {
            $this->get('session')->getFlashBag()->add('alert', 'Export job not found.');
        }

        // back to export panel
        return $this->redirect($this->generateUrl('openview_export_index'));
    }
    
    
    
    /**
     * Download of the file related to the latest job completed
     */
    public function downloadLatestAction()
    {
        // check that no job is active
        $ch = new ChunkExportHandler($this);
        $activeJob = $ch->getCurrentJob();
        if ($activeJob === null) {
            // read last completed job
            $job = $this->getDoctrine()->getManager()->createQueryBuilder()
                ->select('j')
                ->from('OpenviewExportBundle:DataExportJob', 'j')
                ->orderBy('j.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()->getSingleResult();
            if ($job) {
                if ($job->getStatus() === DataExportJob::STATUS_COMPLETE) {
                    $filename = ChunkExportHandler::EXPORT_PATH . $job->getFilename();
                    if (file_exists($filename)) {
                        $content = file_get_contents($filename);
                        $response = new Response();
                        $response->headers->set('Content-Type', 'application/octet-stream');
                        $response->headers->set('Content-Disposition', 'attachment;filename="' . basename($filename));
                        $response->setContent($content);

                        return $response;
                    } else {
                        $this->get('session')->getFlashBag()->add('alert', 'File di esportazione non trovato: ' . basename($filename));
                    }
                } else {
                    $this->get('session')->getFlashBag()->add('alert', 'Export file not found: ' . basename($filename));
                }
            } else {
                $this->get('session')->getFlashBag()->add('alert', 'Export job completed.');
            }
        } else {
            $this->get('session')->getFlashBag()->add('alert', 'Export job not found.');
        }
        
        // back to export panel
        return $this->redirect($this->generateUrl('openview_export_index'));
    }
    
    
    
    /**
     * Abort every active export jobs
     */
    public function abortAction()
    {
        $em = $this->getDoctrine()->getManager();
        $iCount = 0;
        // get active jobs
        $jobs = $em->createQueryBuilder()
            ->select('j')
            ->from('OpenviewExportBundle:DataExportJob', 'j')
            ->where('j.active = true')
            ->getQuery()->getResult();
        foreach ($jobs as $job) {
            $job->setActive(false);
            $job->setStatus(DataExportJob::STATUS_ABORTED);
            $em->persist($job);
            $iCount++;
        }
        $em->flush();
        if ($iCount > 0) {
            $this->get('session')->getFlashBag()->add('info', $iCount . ' jobs canceled.');
        } else {
            $this->get('session')->getFlashBag()->add('info', 'No job to cancel.');
        }
        
        // back to panel
        return $this->redirect($this->generateUrl('openview_export_index'));
    }



    
    /**
     * Return file size, in KB
     */
    protected function getFilesize($filename)
    {
        if (file_exists($filename)) {
            $size = floor(filesize(ChunkExportHandler::EXPORT_PATH . $filename) / 1024);
            return $size;
        }
        else {
            return '';
        }
    }
    
    
    
}

