# ExportBundle
Export your data to csv, using mongodb as support table


## Dependencies
#### Jquery
JQuery is mandatory. You should include it al least in the index.html.twig template

#### FontAwesome
This is not mandatory, it's just to add the spinner icon. You can overwrite the index.html.twig to
edit the code as you wish.
It works with both FontAwesome 4 and FontAwesome 3


## Installation
#### Include the bundle in composer.json
    "openview/export-bundle": "1.*"

#### Update AppKernel.php
    // app/AppKernel.php
    public function registerBundles()
    {
        $bundles = array(
            //...
            new Openview\ExportBundle\OpenviewExportBundle(),
        );


## Configuration
#### Include routing
    # app/config/routing.yml
    openview_export:
        resource: "@OpenviewExportBundle/Resources/config/routing.yml"
        prefix:   /

#### Enable Assetic for OpenviewExportBundle in config.yml
    # app/config/config.yml
    assetic:
        bundles:        [ OpenviewExportBundle]

#### Adapt layout
Create the file app/Resources/OpenviewExportBundle/views/layout.html.twig to fit into your base template.
You have to define two blocks: exportcontent and exportjavascripts:
    {# default layout file #}
    {% extends '::base.html.twig' %}
    
    {% block content %}
        {% block exportcontent %}{% endblock %}
    {% endblock %}
    
    {% block javascripts %}
        {{ parent() }}
        {% block exportjavascripts %}{% endblock %}
    {% endblock %}

#### Edit parameters.php
Add the name of the class that manages your environment-specific handler.

    # app/config/parameters.yml
    exportablehandlerclass: 'Acme\AppBundle\Handler\OrdersExportHandler'

#### Create the data handler
Create the handler that actually performs access to your application-specific data.
This handler must implement the ExportableHandlerInterface

    <?php
    namespace Openview\EcrfBundle\Handler;
    use Openview\ExportBundle\Model\ExportableHandlerInterface;

    class OrdersExportHandler implements ExportableHandlerInterface {
        const CHUNK_SIZE = 25;          // # of records for each export chunk

        protected $controller;
        protected $doctrine;
        protected $doctrineMongo;

        public function __construct($controller) {
            $this->controller = $controller;
            $this->doctrine = $this->controller->getDoctrine();
            $this->doctrineMongo = $this->controller->get('doctrine_mongodb');
        }

        /**
         * Returns the exportable items qty
         */
        public function getTotItems()
        {
            $q = $this->doctrine->getManager()->createQueryBuilder()
                ->select('count(c)')
                ->from('AcmeAppBundle:Order', 'c')
                ->getQuery();
            $items = $q->getSingleScalarResult();

            return $items;
        }


        /**
         * Gets a collection with a chunk of items to export
         * @param int $firstItem
         */
        public function getNextChunk($firstItem)
        {
            $q = $this->doctrine->getManager()->createQueryBuilder()
                ->select('c')
                ->from('AcmeAppBundle:Order', 'c')
                ->orderBy('c.id')
                ->setFirstResult($firstItem)
                ->setMaxResults(StudyExportHandler::CHUNK_SIZE)
                ->getQuery();
            return $q->getResult();
        }



        /**
         * Returns a single row to be put in the export table
         * The returned data is an array with a key for each field of the export collection
         *
         * Note: this is just an example, the real content depends heavily on your data model
         */
        public function getRow($client)
        {
            $row = array();

            // extract all orders for each patient. orders are in a mongodb collection
            $qb = $this->doctrine->getManager()->createQueryBuilder()
                ->select('doc')
                ->from('AcmeAppBundle:Order', 'doc')
                ->innerJOIN('doc.client', 'client')
                ->where('(client.id = :clientId)')
                ->setParameter(':clientId', $client->getId())
                ->orderBy('doc.createdAt');
            $q = $qb->getQuery();
            $docs = $q->getResult();

            // for each client, read order documents
            foreach ($docs as $doc) {
                $row['client_id'] = $client->getId();
                $row['client_name'] = $client->getName();

                // open document from mongodb
                $dynDoc = $this->doctrineMongo->getRepository('AcmeAppBundle:Order')
                        ->find($doc->getDynamicDocumentId());
                if ($dynDoc) {
                    $fields = $dynDoc->getFields();
                    foreach ($fields as $field) {
                            $row[$field->getLabel()] = $field->getValue();
                    }
                }
            }
            return $row;
        }


    }


## Notes
The data row for each exported record may be mad with different fields. This is why we based
the export on a mongodb collection. So the fields array returned by getRow() in your
implementation of ExportableHandlerInterface can be different: the export procedure will use a 
different column for each label.


## How it works
Starting an exporting job creates (via an ajax-call on start API) an instance of 
DataExportJob and persists it on its table.
Then an interval ajax call on che check API has 2 effects:
1) the export job processes a chunk of data
2) the current job status is read and displayed in the interface

