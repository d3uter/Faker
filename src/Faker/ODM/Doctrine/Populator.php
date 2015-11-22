<?php

namespace Faker\ODM\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;

/**
 * Service class for populating a database using the Doctrine ORM or ODM.
 * A Populator can populate several tables using ActiveRecord classes.
 */
class Populator
{
    protected $generator;
    protected $manager;
    protected $entities = array();
    protected $quantities = array();
    protected $generateId = array();

    /**
     * @param \Faker\Generator $generator
     * @param ObjectManager|null $manager
     */
    public function __construct(\Faker\Generator $generator, ObjectManager $manager = null)
    {
        $this->generator = $generator;
        $this->manager = $manager;
    }

    /**
     * Add an order for the generation of $number records for $entity.
     *
     * @param mixed $entity A Doctrine classname, or a \Faker\ORM\Doctrine\EntityPopulator instance
     * @param int   $number The number of entities to populate
     */
    public function addDocument($document, $number, $customFieldFormatters = array(), $customModifiers = array(), $generateId = false)
    {
        if (!$document instanceof \Faker\ODM\Doctrine\DocumentPopulator) {
            if (null === $this->manager) {
                throw new \InvalidArgumentException("No document manager passed to Doctrine Populator.");
            }
            $document = new \Faker\ODM\Doctrine\DocumentPopulator($this->manager->getClassMetadata($document));
        }
        
        $document->setFieldFormatters($document->guessFieldFormatters($this->generator));
        if ($customFieldFormatters) {
            $document->mergeFieldFormattersWith($customFieldFormatters);
        }
        $document->mergeModifiersWith($customModifiers);
        $this->generateId[$document->getClass()] = $generateId;

        $class = $document->getClass();
        $this->entities[$class] = $document;
        $this->quantities[$class] = $number;
    }

    /**
     * Populate the database using all the Entity classes previously added.
     *
     * @param null|EntityManager $entityManager A Doctrine connection object
     *
     * @return array A list of the inserted PKs
     */
    public function execute($entityManager = null)
    {
        if (null === $entityManager) {
            $entityManager = $this->manager;
        }
        if (null === $entityManager) {
            throw new \InvalidArgumentException("No entity manager passed to Doctrine Populator.");
        }

        $insertedEntities = array();
        foreach ($this->quantities as $class => $number) {
            $generateId = $this->generateId[$class];
            for ($i=0; $i < $number; $i++) {
                $insertedEntities[$class][]= $this->entities[$class]->execute($entityManager, $insertedEntities, $generateId);
            }
            $entityManager->flush();
        }

        return $insertedEntities;
    }
}
