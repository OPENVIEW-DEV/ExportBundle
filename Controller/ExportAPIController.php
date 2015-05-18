<?php
namespace Openview\ExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Openview\ExportBundle\Handler\ChunkExportHandler;
use Openview\ExportBundle\Entity\DataExportJob;


/**
 * Controller to manage data export's API
 */
class ExportAPIController extends Controller {




    /**
     * Check the export status and takes the necessary action (like staring export or save data)
     */
    public function checkAction()
    {
        $result = array();
        $mongoConnectionArray = array(
            'server'=>$this->container->getParameter('mongodb_server'),
            'options'=>$this->container->getParameter('mongodb_options'),
            );
        $eh = new ChunkExportHandler($this, $mongoConnectionArray);
        $exportableClass = $this->container->getParameter('exportablehandlerclass');
        $eh->setExportableHandler(new $exportableClass($this));
        $job = $eh->getCurrentJob();

        if ($job !== null) {
            $result['activejob'] = 1;
            // basing on status, decides the action to take
            switch ($job->getStatus()) {
                case DataExportJob::STATUS_READY:
                    $eh->init($job);
                    break;
                case DataExportJob::STATUS_EXPORTING:
                    $eh->exportChunk($job);
                    break;
                case DataExportJob::STATUS_SAVING:
                    $eh->save($job);
                    break;
            }
            $result['job'] = $job->toArray();
        } else {
            $result['activejob'] = 0;
        }
        // return a json with the execution status
        $serializer = $this->container->get('jms_serializer');
        $s = $serializer->serialize($result, 'json');

        return new Response($s);
    }



    /**
     * Start a new export job (if there are no others executing)
     */
    public function startAction()
    {
        $result = array();
        $eh = new ChunkExportHandler($this);
        $exportableClass = $this->container->getParameter('exportablehandlerclass');
        $eh->setExportableHandler(new $exportableClass($this));
        $activeJob = $eh->getCurrentJob();
        // create a new job if there are no other active jobs already
        if ($activeJob === null) {
            $job = new DataExportJob();
            $job->setStatus(DataExportJob::STATUS_READY);
            $job->setActive(true);
            $em = $this->getDoctrine()->getManager();
            $em->persist($job);
            $em->flush();
            $result['startedjob'] = 1;
            $result['job'] = $job->toArray();
        }
        // se ho già un job attivo
        else {
            $result['startedjob'] = 0;
        }
        // return a json with the execution status
        $serializer = $this->container->get('jms_serializer');
        $s = $serializer->serialize($result, 'json');

        return new Response($s);
    }
    
    
}

