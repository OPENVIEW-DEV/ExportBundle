<?php
namespace Openview\ExportBundle\Model;


/**
 * Interface for the handler of the exportable collection
 *
 */
interface ExportableHandlerInterface
{
    public function getTotItems();
    public function getNextChunk($firstItem);
    public function getRow($item);
}
